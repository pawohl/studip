<?php

/**
 * request.php - contains Resources_RequestController
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Moritz Strohm <strohm@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @copyright   2017-2019
 * @category    Stud.IP
 * @since       4.5
 */


/**
 * Resources_RequestController contains resource request functionality.
 */
class Resources_RoomRequestController extends AuthenticatedController
{
    protected $filter;

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $this->current_user = User::findCurrent();

        $default_filters = [];
        if (in_array($action, ['resolve', 'decline'])) {
            $default_filters['get_only_request_ids'] = true;
            $default_filters['filter_request_id'] = $args[0];
        } elseif ($action === 'planning') {
            $default_filters['request_periods'] ='periodic';
        }

        $this->filter = $this->getFilters($default_filters);
        $this->available_rooms = [];
        if (in_array($action, ['overview', 'planning', 'export_list', 'resolve', 'decline'])) {
            $user_is_global_resource_autor = ResourceManager::userHasGlobalPermission($this->current_user, 'autor');
            if (!RoomManager::userHasRooms($this->current_user, 'autor', true) && !$user_is_global_resource_autor) {
                throw new AccessDeniedException(_('Ihre Berechtigungen an Räumen reichen nicht aus, um die Anfrageliste anzeigen zu können!'));
            }

            $this->available_rooms = RoomManager::getUserRooms($this->current_user, 'autor', true);
            $this->selected_room_ids = [];

            if (!Semester::find($GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE)) {
                $GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE = Semester::findCurrent()->id;
            }
        }

        $this->selected_room_ids = [];
        if (isset($this->filter['room_id'])) {
            $room = Resource::find($this->filter['room_id']);
            if (!$room) {
                PageLayout::postError(
                    _('Der gewählte Raum wurde nicht gefunden!')
                );
                return;
            }
            $room = $room->getDerivedClassInstance();
            if (!($room instanceof Room)) {
                PageLayout::postError(
                    _('Es wurde kein Raum ausgewählt!')
                );
                return;
            }
            if (!$room->userHasPermission($this->current_user, 'autor')) {
                PageLayout::postError(
                    sprintf(
                        _('Die Berechtigungen für den Raum %s sind nicht ausreichend, um die Anfrageliste anzeigen zu können!'),
                        htmlReady($room->name)
                    )
                );
                return;
            }
            $this->selected_room_ids = [$room->id];
        } elseif (isset($this->filter['group'])) {
            //Filter rooms by the selected room group:
            $clipboard = Clipboard::find($this->filter['group']);
            if (!$clipboard) {
                PageLayout::postError(
                    _('Die gewählte Raumgruppe wurde nicht gefunden!')
                );
                return;
            }
            $room_ids = $clipboard->getAllRangeIds('Room');
            if (!$room_ids) {
                PageLayout::postError(
                    _('Die gewählte Raumgruppe enthält keine Räume!')
                );
                return;
            }
            $rooms = Resource::findMany($room_ids);
            foreach ($rooms as $room) {
                $room = $room->getDerivedClassInstance();
                if ($room instanceof Room) {
                    $this->selected_room_ids[] = $room->id;
                }
            }
        } else {
            //No filter for a room or a room group set:
            //Display requests of all available rooms:
            foreach ($this->available_rooms as $room) {
                $this->selected_room_ids[] = $room->id;
            }
        }

        // if sorting according to table column was chosen, set the correct
        // sort order (ascending vs descending)
        $sort_value = Request::int('sorting');
        if ($sort_value) {
            $_SESSION[__CLASS__]['sort'] = [
                'by' => $sort_value,
                'dir' => Request::option('sort_order') === 'desc' ? 'desc' : 'asc',
            ];
        }

        $this->entries_per_page = Config::get()->ENTRIES_PER_PAGE;
    }

    /**
     * @return array
     */
    protected function getFilteredRoomRequests()
    {
        $sql = '';
        if (!empty($this->filter['request_status']) && $this->filter['request_status'] === 'closed') {
            $sql .= "resource_requests.closed IN ('1', '2') ";
        } elseif (!empty($this->filter['request_status']) && $this->filter['request_status'] === 'denied') {
            $sql .= "resource_requests.closed = '3' ";
        } else {
            $sql .= "resource_requests.closed < '1' ";
        }
        $sql .= "AND (resource_id IN ( :room_ids) ";
        $sql_params = [
            'room_ids' => $this->selected_room_ids
        ];
        if (empty($this->filter['specific_requests'])) {
            $sql .= "OR resource_id IS NULL or resource_id = ''";
        }
        $sql .= ") ";
        if (!empty($this->filter['own_requests'])) {
            $sql .= " AND resource_requests.user_id = :current_user_id ";
            $sql_params['current_user_id'] = $this->current_user->id;
        }

        if (!empty($this->filter['request_periods']) && $this->filter['request_periods'] == 'periodic') {
            // get rid of requests for single dates AND requests for multiple single dates
            // also check if there exists cycle dates in case it is a request for the whole seminar
            $sql .= " AND resource_requests.termin_id = ''
            AND NOT EXISTS
            (
                SELECT * FROM resource_request_appointments
                WHERE resource_request_appointments.request_id = resource_requests.id
            )
            AND EXISTS (
                SELECT * FROM seminar_cycle_dates
                WHERE seminar_cycle_dates.seminar_id = resource_requests.course_id
            )";
        }
        if (!empty($this->filter['request_periods']) && $this->filter['request_periods'] == 'aperiodic') {
            $sql .= " AND (
                resource_requests.termin_id <> ''
                OR EXISTS
                (
                    SELECT * FROM resource_request_appointments
                    WHERE resource_request_appointments.request_id = resource_requests.id
                )
                OR NOT EXISTS (
                    SELECT * FROM seminar_cycle_dates
                    WHERE seminar_cycle_dates.seminar_id = resource_requests.course_id
                )
            )";
        }


        $institute_id = $this->filter['institute'] ?? null;
        if ($institute_id) {
            $common_seminar_sql = 'resource_requests.course_id IN (
                SELECT seminar_id FROM seminare
                WHERE %1$s
                )';
            $include_children = false;
            if (preg_match('/_withinst$/', $institute_id)) {
                $include_children = true;
                $institute_id = explode('_', $institute_id)[0];
            }
            $institute = Institute::find($institute_id);
            if ($institute instanceof Institute) {
                $institute_ids = [$institute->id];
                if ($institute->isFaculty() && $include_children) {
                    //Get the requests from courses from the faculty
                    //and its institutes.
                    foreach ($institute->sub_institutes as $sub_inst) {
                        $institute_ids[] = $sub_inst->id;
                    }
                }

                if ($sql) {
                    $sql .= ' AND ';
                }
                $sql .= sprintf(
                    $common_seminar_sql,
                    'seminare.institut_id IN ( :institute_ids )'
                );
                $sql_params['institute_ids'] = $institute_ids;
            }
        } else if (!ResourceManager::userHasGlobalPermission($this->current_user, 'admin')) {
            // inst admins only get requests for their rooms or courses of their institutes
            $sql .= " AND (resource_id != '' OR course_id IN (SELECT seminar_id FROM seminare WHERE institut_id IN (:institute_ids)))";
            $sql_params['institute_ids'] = array_column(Institute::getMyInstitutes(), 'Institut_id');
        }

        if (
            isset($this->filter['marked'])
            && $this->filter['marked'] < ResourceRequest::MARKING_STATES
        ) {
            if ($sql) {
                $sql .= ' AND ';
            }
            if ($this->filter['marked'] == 0) {
                $sql .= "resource_requests.marked = '0' ";
            } else {
                $sql .= "(resource_requests.marked > '0' && resource_requests.marked < :max_marked) ";
                $sql_params['max_marked'] = ResourceRequest::MARKING_STATES;
            }
        }

        $semester_id = $this->filter['semester'];
        if ($semester_id) {
            // Split the ID that might be suffixed at this point:
            $id_parts = explode('_', $semester_id);
            $semester_id = $id_parts[0];
            $semester = Semester::find($semester_id);
            if ($semester instanceof Semester) {
                $start_ts = $semester->beginn;
                $end_ts   = $semester->ende;

                if (count($id_parts) > 1) {
                    if ($id_parts[1] === 'lecture') {
                        $start_ts = $semester->vorles_beginn;
                        $end_ts   = $semester->vorles_ende;
                    } elseif ($id_parts[1] === 'vacation') {
                        // The time range is from the end of the lecture period of the selected semester
                        // until the start of the lecture period of the next semester (if any).
                        $next_semester = Semester::findByTimestamp($semester->ende + 1);
                        if ($next_semester) {
                            $start_ts = $semester->vorles_ende;
                            $end_ts   = $next_semester->vorles_beginn;
                        } else {
                            // The time range is from the end of the selected semester until "eternity"
                            // (or a reasonable facsimile thereof).
                            $start_ts = $semester->vorles_ende;
                            $end_ts   = PHP_INT_MAX;
                        }
                    }
                }
                if ($sql) {
                    $sql .= ' AND ';
                }
                $sql .= "(
                (resource_requests.termin_id <> '' AND EXISTS (SELECT * FROM termine WHERE termine.termin_id=resource_requests.termin_id AND termine.date BETWEEN :begin AND :semester_end))
                    OR
                    (resource_requests.metadate_id <> '' AND EXISTS (SELECT * FROM termine WHERE termine.metadate_id=resource_requests.metadate_id AND termine.date BETWEEN :begin AND :semester_end))
                    OR
                    (resource_requests.termin_id = '' AND resource_requests.metadate_id = '' AND (
                        EXISTS (SELECT * FROM termine JOIN resource_request_appointments ON termine.termin_id = appointment_id WHERE request_id = resource_requests.id AND termine.date BETWEEN :begin AND :semester_end)
                        OR
                        NOT EXISTS (SELECT * FROM resource_request_appointments WHERE request_id = resource_requests.id) AND EXISTS (SELECT * FROM termine WHERE termine.range_id=resource_requests.course_id AND termine.date BETWEEN :begin AND :semester_end)
                    ))
                     ";

                if (empty($this->filter['request_periods'])) {
                    $sql .= ' OR (
                        CAST(resource_requests.begin AS SIGNED) - CAST(resource_requests.preparation_time AS SIGNED) < :semester_end
                        AND CAST(resource_requests.end AS SIGNED) + CAST(resource_requests.subsequent_time AS SIGNED) > :begin
                        )';
                }
                $sql .= ') ';
                $sql_params['begin'] = max($start_ts, time());
                $sql_params['semester_end'] = $end_ts;
            }
        }
        if (!empty($this->filter['course_type'])) {
            $course_type = explode('_', $this->filter['course_type']);
            if (empty($course_type[1])) {
                $course_types = array_keys(SemClass::getClasses()[$course_type[0]]->getSemTypes());
            } else {
                $course_types = [$course_type[1]];
            }
            $sql .= " AND EXISTS (SELECT * FROM seminare
                    WHERE resource_requests.course_id=seminare.seminar_id
                    AND seminare.status IN(:course_types)) ";
            $sql_params[':course_types'] = $course_types;
        }

        if (!$sql) {
            //No filtering done
            $sql = 'TRUE ';
        }

        $sql .= " GROUP BY resource_requests.id ";

        // if table should be sorted by marking state
        if (isset($_SESSION[__CLASS__]['sort'])) {
            switch ($_SESSION[__CLASS__]['sort']['by']) {
                case 1:
                    $sql .= " ORDER BY resource_requests.marked ";
                    break;
                case 10:
                    $sql .= " ORDER BY resource_requests.chdate ";
                    break;
                default:
                    $sql .= " ORDER BY mkdate ";
            }
            $sql .= $_SESSION[__CLASS__]['sort']['dir'] === 'desc' ? 'DESC' : 'ASC';
        }

        $requests = RoomRequest::findBySql($sql, $sql_params);
        $result = [];
        if (!empty($this->filter['dow'])) {
            $week_days = [$this->filter['dow']];
            foreach ($requests as $request) {
                $time_intervals = $request->getTimeIntervals(true);

                //Check for each time interval if it lies in at least one
                //of the specified week days.
                foreach ($time_intervals as $time_interval) {
                    $interval_weekdays = []; //The weekdays of the time interval.
                    $weekday_begin = date('N', $time_interval['begin']);
                    $weekday_end = date('N', $time_interval['end']);
                    $interval_weekdays[] = $weekday_begin;
                    if (($weekday_end - $weekday_begin) != 0) {
                        $interval_weekdays[] = $weekday_end;
                        $current_day = $weekday_begin;
                        //In case the end lies on a day after the begin
                        //or even in another week, we must loop until
                        //we reached the weekday of the end timestamp.
                        while ($current_day != $weekday_end) {
                            $interval_weekdays[] = $current_day;
                            if ($current_day == 7) {
                                $current_day = 1;
                            } else {
                                $current_day++;
                            }
                        }
                    }
                    $interval_weekdays = array_unique($interval_weekdays);
                    //We have all relevant weekdays and can now check
                    //if the time interval lies in one of the relevant weekdays:
                    foreach ($interval_weekdays as $iwd) {
                        if (in_array($iwd, $week_days)) {
                            //Add the request to the result set. By using the
                            //request-ID as key, we can make sure one request
                            //is only added once to the result set.
                            $result[$request->id] = $request;
                            //Continue to the next request:
                            continue 2;
                        }
                    }
                }
            }
            if (!empty($this->filter['get_only_request_ids'])) {
                return array_keys($result);
            }
        } else {
            $result = $requests;
            if (!empty($this->filter['get_only_request_ids'])) {
                return SimpleCollection::createFromArray($requests)->pluck('id');
            }
        }
        // sort requests according to display table columns not in the resource request db table
        if (
            isset($_SESSION[__CLASS__]['sort'])
            && $_SESSION[__CLASS__]['sort']['by'] != 1
            && $_SESSION[__CLASS__]['sort']['by'] != 10
        ) {
            $result = $this->sort_request_table($result, $_SESSION[__CLASS__]['sort']['by'], $_SESSION[__CLASS__]['sort']['dir']);
        }

        return $result;
    }

    /**
    * Sorts the resource requests according to columns not belonging to the
    * resource requests db table.
    *
    * @param array $requests array of ResourceRequest objects
    * @param int $sort_variable property according to which the requests should be sorted
    *               values 1 and 10 are database columns (marked state and chdate) and already dealt with
                    2 = lecture number
                    3 = lecture name
                    4 = dozent name
                    5 = room name
                    6 = available seats
                    7 = requesting person
                    8 = type of date
                    9 = priority
    * @param string $order ascending ('asc') or descending ('desc') order
    *
    * @return array sorted array of resource requests
    */
    protected function sort_request_table($requests, int $sort_variable, string $order)
    {
        usort(
            $requests,
            function (ResourceRequest $a, ResourceRequest $b) use ($sort_variable) {
                $rangeObjA = $a->getRangeObject();
                $rangeObjB = $b->getRangeObject();

                switch ($sort_variable) {
                    case 2: // lecture number
                        return strcmp($rangeObjA->veranstaltungsnummer, $rangeObjB->veranstaltungsnummer);
                    case 3: // lecture name
                        return strcmp($this->getFullNameForSort($rangeObjA), $this->getFullNameForSort($rangeObjB));
                    case 4: // dozent name
                        $a_dozent_strings = '';
                        foreach ($rangeObjA->getMembersWithStatus('dozent') as $dozent) {
                            $a_dozent_strings .= $this->getFullNameForSort($dozent->user);
                        }

                        $b_dozent_strings = '';
                        foreach ($rangeObjB->getMembersWithStatus('dozent') as $dozent) {
                            $b_dozent_strings .= $this->getFullNameForSort($dozent->user);
                        }

                        return strcmp($a_dozent_strings, $b_dozent_strings);
                    case 5: // room name
                        return strcmp($a->resource->name, $b->resource->name);
                    case 6: // available seats
                        return $a->getProperty('seats') - $b->getProperty('seats');
                    case 7: // requesting person
                        return strcmp($this->getFullNameForSort($a->user), $this->getFullNameForSort($b->user));
                    case 8: // type
                        return strcmp(
                            $a->getTypeString(true) . $a->getStartDate()->format('YnjHis'),
                            $b->getTypeString(true) . $b->getStartDate()->format('YnjHis')
                        );
                    case 9: // priority
                        return $a->getPriority() - $b->getPriority();
                }

                return 0;
            }
        );

        if ($order === 'desc') {
            $requests = array_reverse($requests);
        }

        return $requests;
    }

    private function getFullNameForSort(Range $range): string
    {
        if ($range instanceof User) {
            return $range->getFullName('no_title_rev');
        }

        return $range->getFullName('name');
    }

    protected function getRoomAvailability(Room $room, $time_intervals = [])
    {
        $availability = [];

        foreach ($time_intervals as $interval) {
            $begin = new DateTime();
            $end = new DateTime();
            $begin->setTimestamp($interval['begin']);
            $end->setTimestamp($interval['end']);
            $availability[] = $room->isAvailable($begin, $end, !empty($interval['booking_id']) ? [$interval['booking_id']] : []);
        }

        return $availability;
    }


    /**
     * Shows all requests. By default, only open requests are shown.
     */
    public function overview_action(int $page = 0)
    {
        if (Navigation::hasItem('/resources/planning/requests_overview')) {
            Navigation::activateItem('/resources/planning/requests_overview');
        }
        PageLayout::setTitle(_('Anfragenliste'));

        $this->setupSidebar('overview');

        $sidebar = Sidebar::get();

        $relevant_export_filters = [
            'institut_id'               => 'institute',
            'semester_id'               => 'semester',
            'course_type'               => 'course_type',
            'group'                     => 'group',
            'room_id'                   => 'room_id',
            'marked'                    => 'marked',
            'request_periods'           => 'request_periods',
            'toggle_specific_requests'  => 'specific_requests',
            'dow'                       => 'dow'
        ];
        $export_url_params = [];
        foreach ($relevant_export_filters as $param => $filter_name) {
            if (isset($this->filter[$filter_name])) {
                $export_url_params[$param] = $this->filter[$filter_name];
            }
        }
        $export = new ExportWidget();
        $export->addLink(
            _('Gefilterte Anfragen'),
            $this->export_listURL($export_url_params),
            Icon::create('export')
        );
        $export->addLink(
            _('Alle Anfragen'),
            $this->export_listURL(),
            Icon::create('export')
        );
        $sidebar->addWidget($export);

        $requests = $this->getFilteredRoomRequests();
        $this->count_requests = count($requests);
        $requests = array_slice(
            $requests,
            $this->entries_per_page * ($page),
            $this->entries_per_page
        );

        $this->pagination = Pagination::create(
            $this->count_requests,
            $page,
            $this->entries_per_page
        );
        $this->requests = $requests;
        $this->page = $page;
        $this->sort_var = $_SESSION[__CLASS__]['sort']['by'] ?? '';
        $this->sort_order = $_SESSION[__CLASS__]['sort']['dir'] ?? '';

        $this->request_status = $this->filter['request_status'] ?? '';
    }

    public function index_action($request_id = null)
    {
        $this->request = ResourceRequest::find($request_id);
        if (!$this->request) {
            PageLayout::postError(
                _('Die angegebene Anfrage wurde nicht gefunden!')
            );
            return;
        }
        $this->request = $this->request->getDerivedClassInstance();
        $this->resource = $this->request->resource;
        if (!$this->resource) {
            PageLayout::postError(
                _('Die angegebene Ressource wurde nicht gefunden!')
            );
            return;
        }
        $this->resource = $this->resource->getDerivedClassInstance();

        PageLayout::setTitle(
            sprintf(
                _('%s: Details zur Anfrage'),
                $this->resource->getFullName()
            )
        );
    }

    /**
     * This action handles resource requests that are not bound
     * to a course or another Stud.IP object.
     */
    public function add_action($resource_id = null)
    {
        if (!Config::get()->RESOURCES_ALLOW_ROOM_REQUESTS) {
            throw new AccessDeniedException(
                _('Das Erstellen von Raumanfragen ist nicht erlaubt!')
            );
        }
        $this->resource = null;
        $this->request = null;
        $this->show_form = false;
        $this->resource = Resource::find($resource_id);
        if (!$this->resource) {
            PageLayout::postError(
                _('Die angegebene Ressource wurde nicht gefunden!')
            );
            return;
        }
        $this->resource = $this->resource->getDerivedClassInstance();
        PageLayout::setTitle(
            sprintf(
                _('%s: Neue Anfrage erstellen'),
                $this->resource->getFullName()
            )
        );

        if (!$this->resource->userHasRequestRights($this->current_user)) {
            throw new AccessDeniedException();
        }
        $this->form_action_link = $this->link_for('resources/room_request/add/' . $this->resource->id);
        if (!($this->resource instanceof Room)) {
            PageLayout::postError(
                _('Die angegebene Ressource ist kein Raum!')
            );
            return;
        }
        if (!$this->resource->requestable) {
            PageLayout::postError(
                _('Die angegebene Ressource kann nicht angefragt werden!')
            );
            return;
        }


        //Check if the resource is a room and if the room is part of a
        //separable room.
        if ($this->resource instanceof Room) {
            $separable_room = SeparableRoom::findByRoomPart($this->resource);
            if ($separable_room instanceof SeparableRoom) {
                //We must display a warning. But first we check,
                //if we can get the other room parts so that
                //we can make a more informative message.

                $other_room_parts = $separable_room->findOtherRoomParts(
                    [$this->resource]
                );

                if ($other_room_parts) {

                    $other_room_links = [];
                    foreach ($other_room_parts as $room_part) {
                        $other_room_links[] = sprintf(
                            '<a target="_blank" href="%1$s">%2$s</a>',
                            $room_part->getActionLink('show'),
                            htmlReady($room_part->name)
                        );
                    }
                    PageLayout::postInfo(
                        sprintf(
                            _('Der Raum %1$s ist ein Teilraum des Raumes %2$s. Weitere Teilräume sind:'),
                            htmlReady($this->resource->name),
                            htmlReady($separable_room->name)
                        ),
                        $other_room_links
                    );
                } else {
                    PageLayout::postInfo(
                        sprintf(
                            _('Der Raum %1$s ist ein Teilraum des Raumes %2$s.'),
                            htmlReady($this->resource->name),
                            htmlReady($separable_room->name)
                        )
                    );
                }
            }
        }

        $begin = new DateTime();
        $end = new DateTime();

        //Check if a begin and end timestamp are specified:
        if (!Request::submitted('save') && Request::submitted('begin')
            && Request::submitted('end')) {
            $begin->setTimestamp(Request::get('begin'));
            $end->setTimestamp(Request::get('end'));
        } else {
            //Assume a date is requested for tomorrow.
            //Round the current time to hours and substract
            //two hours from $begin.
            $begin->add(new DateInterval('P1D'));
            $begin->setTime(
                $begin->format('H'),
                0
            );
            $end = clone($begin);
            $begin->sub(new DateInterval('PT2H'));
        }

        $config = Config::get();
        $this->begin_date_str = $begin->format('d.m.Y');
        $this->begin_time_str = $begin->format('H:i');
        $this->end_date_str = $end->format('d.m.Y');
        $this->end_time_str = $end->format('H:i');
        $this->preparation_time = 0;
        $this->subsequent_time  = 0;
        $this->max_preparation_time = $config->RESOURCES_MAX_PREPARATION_TIME;
        $this->comment = '';
        $this->booking_text = '';

        $this->show_form = true;

        if (Request::submitted('save')) {
            //Get the requested time range and check if the resource
            //is available in that time range. If so, create a new
            //resource request (or a room request if the resource
            //is a room).

            $this->begin_date_str = Request::get('begin_date');
            $this->begin_time_str = Request::get('begin_time');
            $this->end_date_str = Request::get('end_date');
            $this->end_time_str = Request::get('end_time');
            $this->preparation_time = Request::int('preparation_time', 0);
            $this->subsequent_time  = Request::int('subsequent_time', 0);

            if (!$this->begin_date_str) {
                PageLayout::postError(
                    _('Es wurde kein Startdatum angegeben!')
                );
                return;
            }
            if (!$this->begin_time_str) {
                PageLayout::postError(
                    _('Es wurde kein Startzeitpunkt angegeben!')
                );
                return;
            }
            if (!$this->end_date_str) {
                PageLayout::postError(
                    _('Es wurde kein Enddatum angegeben!')
                );
                return;
            }
            if (!$this->end_time_str) {
                PageLayout::postError(
                    _('Es wurde kein Endzeitpunkt angegeben!')
                );
                return;
            }
            if ($this->preparation_time > $this->max_preparation_time || $this->subsequent_time > $this->max_preparation_time) {
                PageLayout::postError(
                    sprintf(
                        _('Die eingegebene Rüstzeit überschreitet das erlaubte Maximum von %d Minuten!'),
                        $this->max_preparation_time
                    )
                );
            }

            //Comment is optional.
            $this->comment      = Request::get('comment');
            $this->booking_text = Request::get('booking_text');

            //Convert the date and time strings to DateTime objects:

            $begin_date_arr = explode('.', $this->begin_date_str);
            $begin_time_arr = explode(':', $this->begin_time_str);
            $end_date_arr = explode('.', $this->end_date_str);
            $end_time_arr = explode(':', $this->end_time_str);

            $new_begin = new DateTime();
            $new_begin->setDate(
                $begin_date_arr[2],
                $begin_date_arr[1],
                $begin_date_arr[0]
            );
            $new_begin->setTime(
                $begin_time_arr[0],
                $begin_time_arr[1],
                $begin_time_arr[2] ?? 0
            );
            $new_end = new DateTime();
            $new_end->setDate(
                $end_date_arr[2],
                $end_date_arr[1],
                $end_date_arr[0]
            );
            $new_end->setTime(
                $end_time_arr[0],
                $end_time_arr[1],
                $end_time_arr[2] ?? 0
            );

            try {
                //All checks are done in Resource::createSimpleRequest.
                $request = $this->resource->createSimpleRequest(
                    $this->current_user,
                    $new_begin,
                    $new_end,
                    $this->comment,
                    $this->preparation_time * 60,
                    $this->subsequent_time * 60,
                    $this->booking_text
                );

                if ($request) {
                    $this->show_form = false;
                    PageLayout::postSuccess(
                        _('Die Anfrage wurde gespeichert.')
                    );
                }
            } catch (Exception $e) {
                PageLayout::postError($e->getMessage());
            }
            //All other exceptions are not caught since they are not thrown
            //in Resource::createSimpleRequest.
        }
    }


    public function edit_action($request_id = null)
    {
        $this->resource = null;
        $this->request = null;
        $this->show_form = false;

        $this->request = ResourceRequest::find($request_id);
        if (!$this->request) {
            PageLayout::postError(
                _('Die angegebene Anfrage wurde nicht gefunden!')
            );
            return;
        }
        $this->resource = $this->request->resource;
        if (!$this->resource) {
            PageLayout::postError(
                _('Die angegebene Ressource wurde nicht gefunden!')
            );
            return;
        }
        $this->resource = $this->resource->getDerivedClassInstance();

        PageLayout::setTitle(
            sprintf(
                _('%s: Neue Anfrage erstellen'),
                $this->resource->getFullName()
            )
        );
        $this->form_action_link = $this->link_for(
            'resources/room_request/edit/' . $this->request->id
        );

        if (!($this->resource instanceof Room)) {
            PageLayout::postError(
                _('Die angegebene Ressource ist kein Raum!')
            );
            return;
        }

        //Since all Stud.IP users are allowed to create requests,
        //there is no restriction for creating requests.
        $user_may_edit_request = $this->resource->userHasPermission(
                $this->current_user,
                'autor'
            ) || $this->request->user_id === $this->current_user->id;

        if (!$user_may_edit_request) {
            throw new AccessDeniedException();
        }

        //Check if the resource is a room and if the room is part of a
        //separable room.
        if ($this->resource instanceof Room) {
            $separable_room = SeparableRoom::findByRoomPart($this->resource);
            if ($separable_room instanceof SeparableRoom) {
                //We must display a warning. But first we check,
                //if we can get the other room parts so that
                //we can make a more informative message.

                $other_room_parts = $separable_room->findOtherRoomParts(
                    [$this->resource]
                );

                if ($other_room_parts) {

                    $other_room_links = [];
                    foreach ($other_room_parts as $room_part) {
                        $other_room_links[] = sprintf(
                            '<a target="_blank" href="%1$s">%2$s</a>',
                            $room_part->getActionLink('show'),
                            htmlReady($room_part->name)
                        );
                    }
                    PageLayout::postInfo(
                        sprintf(
                            _('Der Raum %1$s ist ein Teilraum des Raumes %2$s. Weitere Teilräume sind:'),
                            htmlReady($this->resource->name),
                            htmlReady($separable_room->name)
                        ),
                        $other_room_links
                    );
                } else {
                    PageLayout::postInfo(
                        sprintf(
                            _('Der Raum %1$s ist ein Teilraum des Raumes %2$s.'),
                            htmlReady($this->resource->name),
                            htmlReady($separable_room->name)
                        )
                    );
                }
            }
        }

        $begin = new DateTime();
        $end = new DateTime();

        //Get begin and end date from the request:
        $begin->setTimestamp($this->request->begin);
        $end->setTimestamp($this->request->end);

        $config = Config::get();
        $this->begin_date_str       = $begin->format('d.m.Y');
        $this->begin_time_str       = $begin->format('H:i');
        $this->end_date_str         = $end->format('d.m.Y');
        $this->end_time_str         = $end->format('H:i');
        $this->preparation_time     = $this->request->preparation_time / 60;
        $this->subsequent_time      = $this->request->subsequent_time / 60;
        $this->max_preparation_time = $config->RESOURCES_MAX_PREPARATION_TIME;
        $this->comment              = $this->request->comment;
        $this->booking_text         = $this->request->booking_text;

        $this->show_form = true;

        if (Request::submitted('save')) {
            //Get the requested time range and check if the resource
            //is available in that time range. If so, create a new
            //resource request (or a room request if the resource
            //is a room).

            $this->begin_date_str   = Request::get('begin_date');
            $this->begin_time_str   = Request::get('begin_time');
            $this->end_date_str     = Request::get('end_date');
            $this->end_time_str     = Request::get('end_time');
            $this->preparation_time = Request::int('preparation_time', 0);
            $this->subsequent_time  = Request::int('subsequent_time', 0);

            if (!$this->begin_date_str) {
                PageLayout::postError(
                    _('Es wurde kein Startdatum angegeben!')
                );
                return;
            }
            if (!$this->begin_time_str) {
                PageLayout::postError(
                    _('Es wurde kein Startzeitpunkt angegeben!')
                );
                return;
            }
            if (!$this->end_date_str) {
                PageLayout::postError(
                    _('Es wurde kein Enddatum angegeben!')
                );
                return;
            }
            if (!$this->end_time_str) {
                PageLayout::postError(
                    _('Es wurde kein Endzeitpunkt angegeben!')
                );
                return;
            }
            if ($this->preparation_time > $this->max_preparation_time || $this->subsequent_time > $this->max_preparation_time) {
                PageLayout::postError(
                    sprintf(
                        _('Die eingegebene Rüstzeit überschreitet das erlaubte Maximum von %d Minuten!'),
                        $this->max_preparation_time
                    )
                );
            }

            //Comment is optional.
            $this->comment      = Request::get('comment');
            $this->booking_text = Request::get('booking_text');

            //Convert the date and time strings to DateTime objects:

            $begin_date_arr = explode('.', $this->begin_date_str);
            $begin_time_arr = explode(':', $this->begin_time_str);
            $end_date_arr = explode('.', $this->end_date_str);
            $end_time_arr = explode(':', $this->end_time_str);

            $new_begin = new DateTime();
            $new_begin->setDate(
                $begin_date_arr[2],
                $begin_date_arr[1],
                $begin_date_arr[0]
            );
            $new_begin->setTime(
                $begin_time_arr[0],
                $begin_time_arr[1],
                $begin_time_arr[2] ?? 0
            );
            $new_end = new DateTime();
            $new_end->setDate(
                $end_date_arr[2],
                $end_date_arr[1],
                $end_date_arr[0]
            );
            $new_end->setTime(
                $end_time_arr[0],
                $end_time_arr[1],
                $end_time_arr[2] ?? 0
            );

            $this->request->begin            = $new_begin->getTimestamp();
            $this->request->end              = $new_end->getTimestamp();
            $this->request->comment          = $this->comment;
            $this->request->booking_text     = $this->booking_text;
            $this->request->preparation_time = $this->preparation_time * 60;
            $this->request->subsequent_time  = $this->subsequent_time * 60;

            if ($this->request->isDirty()) {
                $successfully_stored = $this->request->store();
            } else {
                $successfully_stored = true;
            }
            if ($successfully_stored) {
                $this->show_form = false;
                PageLayout::postSuccess(
                    _('Die Anfrage wurde gespeichert.')
                );
            } else {
                PageLayout::postError(
                    _('Die Anfrage konnte nicht gespeichert werden.')
                );
            }
        }
    }


    public function delete_action($request_id = null)
    {
        $this->request = ResourceRequest::find($request_id);
        if (!$this->request) {
            PageLayout::postError(
                _('Die angegebene Anfrage wurde nicht gefunden!')
            );
            return;
        }
        $this->resource = $this->request->resource;

        PageLayout::setTitle(
            sprintf(
                _('%s: Anfrage löschen'),
                $this->resource->getFullName()
            )
        );

        $user_may_delete_request = ResourceManager::userHasGlobalPermission(
                $this->current_user,
                'autor'
            ) || $this->request->user_id == $this->current_user->id;

        if (!$user_may_delete_request) {
            throw new AccessDeniedException();
        }

        $this->show_form = true;

        if (Request::submitted('delete')) {
            CSRFProtection::verifyUnsafeRequest();

            if ($this->request->delete()) {
                $this->show_form = false;
                PageLayout::postSuccess(
                    _('Die Anfrage wurde gelöscht!')
                );
            } else {
                PageLayout::postError(
                    _('Fehler beim Löschen der Anfrage!')
                );
            }
        }
    }


    protected function calculateRoomAvailabilityData(Room $room)
    {
        //The time intervals are read from $this->request_time_intervals.
        $this->metadate_availability_share[$room->id] = [];
        $this->room_availability[$room->id] = [];
        $this->room_availability_share[$room->id] = 1.0;
        $this->amount_of_dates[$room->id] = 0;
        $this->unavailable_dates[$room->id] = 0;
        $this->unavailable_metadate_dates[$room->id] = [];
        $this->amount_of_metadate_dates[$room->id] = [];

        foreach ($this->request_time_intervals as $metadate_id => $data) {
            $this->unavailable_metadate_dates[$room->id][$metadate_id] = 0;
            $this->amount_of_metadate_dates[$room->id][$metadate_id] = count($data['intervals']);
            $this->amount_of_dates[$room->id] += $this->amount_of_metadate_dates[$room->id][$metadate_id];
            if ($data['metadate'] instanceof SeminarCycleDate && !$this->expand_metadates) {
                $metadate_availability = $this->getRoomAvailability(
                    $room,
                    $data['intervals']
                );

                $metadate_available = true;
                foreach ($metadate_availability as $available) {
                    if (!$available) {
                        $this->unavailable_dates[$room->id]++;
                        $metadate_available = false;
                        $this->unavailable_metadate_dates[$room->id][$metadate_id]++;
                    }
                }
                $this->room_availability[$room->id][$metadate_id] = [$metadate_available];
            } else {
                $metadate_availability = [];
                foreach ($data['intervals'] as $interval) {
                    $interval_available = $this->getRoomAvailability(
                        $room,
                        [$interval]
                    );
                    if (!$interval_available[0]) {
                        $this->unavailable_dates[$room->id]++;
                        $this->unavailable_metadate_dates[$room->id][$metadate_id]++;
                    }
                    $metadate_availability[] = $interval_available[0];
                }
                $this->room_availability[$room->id][$metadate_id] = $metadate_availability;
            }

            if ($this->amount_of_metadate_dates[$room->id][$metadate_id] == 0) {
                $this->metadate_availability_share[$room->id][$metadate_id] = 0.0;
            } else {
                $this->metadate_availability_share[$room->id][$metadate_id] =
                    ($this->amount_of_metadate_dates[$room->id][$metadate_id] - $this->unavailable_metadate_dates[$room->id][$metadate_id])
                    / $this->amount_of_metadate_dates[$room->id][$metadate_id];
            }
        }
        if ($this->amount_of_dates[$room->id] == 0) {
            $this->room_availability_share[$room->id] = 0.0;
        } else {
            $this->room_availability_share[$room->id] =
                ($this->amount_of_dates[$room->id] - $this->unavailable_dates[$room->id]) / $this->amount_of_dates[$room->id];
        }
    }


    /**
     * This action displays information about a request
     * that are relevant before resolving it.
     * The view of this action redirects to resources/booking/add
     * when one wishes to book a room.
     */
    public function resolve_action($request_id = null)
    {
        $this->show_info = false;
        $this->config = Config::get();
        $this->request = ResourceRequest::find($request_id);
        if (!$this->request) {
            PageLayout::postError(
                _('Die angegebene Anfrage wurde nicht gefunden!')
            );
            return;
        }
        $user_has_permission = false;
        $this->user_is_global_autor = ResourceManager::userHasGlobalPermission(
            $this->current_user,
            'autor'
        );

        if (!Request::submitted('single-request')) {
            $request_ids = $this->getFilteredRoomRequests();
            if ($request_ids && count($request_ids) > 1) {
                $this->setRequestForPagination($request_ids);
            }
        }
        //$this->current_user is set in the before_filter.

        $this->request_resource = null;
        if ($this->request->resource instanceof Resource) {
            $this->request_resource = $this->request->resource->getDerivedClassInstance();
        }

        if ($this->request_resource instanceof Resource) {
            //The user must have permanent autor permissions
            //for the selected room:
            $user_has_permission = $this->request_resource->userHasPermission(
                $this->current_user,
                'autor',
                [],
                true
            );
            PageLayout::setTitle(
                sprintf(
                    _('%s: Anfrage auflösen'),
                    $this->request_resource->getFullName()
                )
            );
        } else {
            $user_has_permission = $GLOBALS['perm']->have_studip_perm('tutor', $this->request->course_id);
            PageLayout::setTitle(
                _('Anfrage auflösen')
            );
        }
        if (!$user_has_permission && !$this->user_is_global_autor) {
            throw new AccessDeniedException();
        }

        $this->show_info = true;
        $this->booked_room_infos = [];
        $this->request_semester_string = '';
        if ($this->request->closed > 0) {
            if ($this->request->closed == '3') {
                PageLayout::setTitle(_('Abgelehnte Anfrage'));
                PageLayout::postInfo(
                    _('Die Anfrage wurde abgelehnt!')
                );
            } else {
                PageLayout::postInfo(
                    _('Die Anfrage wurde bereits aufgelöst!')
                );
            }
            $this->show_form = false;
            return;
        }

        $this->notification_settings = 'creator';
        if ($this->request->reply_recipients === ResourceRequest::REPLY_LECTURER) {
            $this->notification_settings = 'creator_and_lecturers';
        }
        $this->reply_comment = $this->request->reply_comment;

        $this->expand_metadates = (Request::submitted('expand_metadates') || Request::option('force_expand_metadates')) && !Request::submitted('fold_metadates');
        $this->show_expand_metadates_button = false;

        $this->request_time_intervals = [];

        if ($this->expand_metadates) {
            //Get all single dates directly, ordered by date.
            $this->request_time_intervals = [
                '' => [
                    'metadate'  => null,
                    'intervals' => $this->request->getTimeIntervals(true, true, false)
                ]
            ];
        } else {
            //Get dates grouped by metadates.
            $this->request_time_intervals = $this->request->getGroupedTimeIntervals(true, false);
        }

        $request_start_semester = $this->request->getStartSemester();
        $request_end_semester = $this->request->getEndSemester();
        if ($request_start_semester && $request_end_semester && $request_start_semester->id != $request_end_semester->id && $request_end_semester->id) {
            $this->request_semester_string = sprintf(
                '%1$s - %2$s',
                $request_start_semester->name,
                $request_end_semester->name
            );
        } else {
            $this->request_semester_string = $request_start_semester ? $request_start_semester->name : '';
        }

        $this->metadate_availability_share = [];
        $this->room_availability = [];
        $this->room_underload = [];
        $this->requested_room_fully_available = true;
        $this->room_availability_share = [];
        $this->amount_of_dates = [];
        $this->unavailable_dates = [];
        $this->amount_of_metadate_dates = [];
        $this->unavailable_metadate_dates = [];
        $this->selected_rooms = [];

        //Load all previously selected rooms where at least one date has been
        //assigned to to the list of alternative rooms and place them
        //at the top of the list.
        if (Request::isPost()) {
            $this->selected_rooms = Request::getArray('selected_rooms');
        }

        $this->visible_dates = 0;
        //Calculate the visible dates and check if the expand_metadates button must be shown:
        foreach ($this->request_time_intervals as $data) {
            if ($data['metadate'] instanceof SeminarCycleDate && !$this->expand_metadates) {
                //There is at least one metadate in the grouped set
                //of time intervals. The expand button must be shown.
                $this->show_expand_metadates_button = true;
                $this->visible_dates++;
            } else {
                $this->visible_dates += count($data['intervals']);
            }
        }

        $selected_room = $this->request_resource;

        if ($selected_room instanceof Room) {
            $this->calculateRoomAvailabilityData($selected_room);
            if ($this->room_availability_share[$selected_room->id] < 1.0) {
                $this->requested_room_fully_available = false;
            }
            foreach ($this->request_time_intervals as $metadate_id => $data) {
                if ($data['metadate'] instanceof SeminarCycleDate && !$this->expand_metadates) {
                    $all_dates_same_room = true;
                    //Check if all dates are booked for the same room:
                    foreach ($data['intervals'] as $interval) {
                        if (!in_array($selected_room->id, $interval['booked_rooms'])) {
                            $all_dates_same_room = false;
                            break;
                        }
                    }
                    if ($all_dates_same_room && $this->room_availability[$selected_room->id][$metadate_id][0] && !$this->selected_rooms) {
                        $this->selected_rooms['SeminarCycleDate_' . $metadate_id] = $selected_room->id;
                    }
                }
            }
            if ($this->request->getProperty('seats') > 0) {
                $this->room_underload[$selected_room->id] =
                    round(((int)$selected_room->seats / (int)$this->request->getProperty('seats')) * 100);
            }
        } else {
            //If no room is selected, it cannot be declared fully available.
            $this->requested_room_fully_available = false;
        }

        //Load the room groups of the current user:

        $this->clipboards = Clipboard::getClipboardsForUser($this->current_user->id, ['Room']);

        $this->selected_clipboard_id = Request::int('selected_clipboard_id');
        if (!$this->selected_clipboard_id) {
            if (count($this->clipboards) > 0) {
                $this->selected_clipboard_id = $this->clipboards[0]->id;
            }
        }

        $this->alternatives_selection = 'room_search';
        if (Request::get('alternatives_selection')) {
            CSRFProtection::verifyUnsafeRequest();
            $this->selected_rooms = Request::getArray('selected_rooms');
            $this->alternatives_selection = Request::get('alternatives_selection');
        }

        $this->show_form = true;
        $room_search_type = new RoomSearch();
        $room_search_type->setAcceptedPermissionLevels(['autor', 'tutor', 'admin']);
        $room_search_type->setAdditionalDisplayProperties(['seats']);
        $this->room_search = new QuickSearch('searched_room_id', $room_search_type);
        $this->alternative_rooms = [];

        $previously_selected_room_ids = [];
        if ($this->selected_rooms) {
            $previously_selected_room_ids = array_unique($this->selected_rooms);
            if ($this->request->resource instanceof Resource) {
                $previously_selected_rooms = Resource::findBySql(
                    'id IN ( :room_ids ) AND id <> :request_room_id',
                    [
                        'room_ids'        => $previously_selected_room_ids,
                        'request_room_id' => $this->request->resource_id
                    ]
                );
            } else {
                $previously_selected_rooms = Resource::findMany($previously_selected_room_ids);
            }
            foreach ($previously_selected_rooms as $room) {
                $room = $room->getDerivedClassInstance();
                if ($room instanceof Room) {
                    if ($room->userHasPermission($this->current_user, 'autor', [])) {
                        $this->alternative_rooms[] = $room;
                    }
                }
            }
        }

        if (!($selected_room instanceof Room) && !Request::submitted('alternatives_selection')) {
            if (!$this->config->RESOURCES_DIRECT_ROOM_REQUESTS_ONLY) {
                //Use the property search as default alternative selection.
                $this->alternatives_selection = 'request';
            } else {
                //Use the room search instead.
                $this->alternatives_selection = 'room_search';
            }
        }

        if (!$this->requested_room_fully_available || Request::submitted('select_alternatives')) {
            if ($this->alternatives_selection == 'clipboard') {
                $room_group_rooms = [];
                //Get the selected clipboard:
                $clipboard = Clipboard::find($this->selected_clipboard_id);
                if ($clipboard instanceof Clipboard) {
                    //Get the rooms of the selected clipboard
                    $room_ids = $clipboard->getAllRangeIds('Room');

                    //Remove the requested room's ID from the result set
                    //to avoid duplicate rows:
                    $requested_room_index = array_search($this->request->resource_id, $room_ids);
                    if ($requested_room_index !== false) {
                        unset($room_ids[$requested_room_index]);
                    }
                    foreach ($previously_selected_room_ids as $psr_id) {
                        $psr_index = array_search($psr_id, $room_ids);
                        if ($psr_index !== false) {
                            unset($room_ids[$psr_index]);
                        }
                    }
                    $resources = Resource::findMany($room_ids, 'ORDER BY name');
                    foreach ($resources as $resource) {
                        //We must filter each room so that only rooms with
                        //permanent autor permissions are included
                        //in the list of alternative rooms:
                        $resource = $resource->getDerivedClassInstance();
                        if ($resource instanceof Room) {
                            if ($resource->userHasPermission($this->current_user, 'autor', [])) {
                                $room_group_rooms[] = $resource;
                            }
                        }
                    }
                }
                $this->alternative_rooms = array_merge(
                    $this->alternative_rooms,
                    $room_group_rooms
                );
            } elseif ($this->alternatives_selection == 'room_search') {
                $room_id = Request::get('searched_room_id');
                if ($this->request->resource_id != $room_id && !in_array($room_id, $previously_selected_room_ids)) {
                    $room = Resource::find($room_id);
                    if ($room) {
                        $room = $room->getDerivedClassInstance();
                        if ($room instanceof Room) {
                            if ($room->userHasPermission($this->current_user, 'autor', [])) {
                                $this->alternative_rooms[] = $room;
                                $this->room_search->defaultValue($room->id, $room->name);
                            }
                        }
                    }
                }
            } elseif ($this->alternatives_selection == 'my_rooms') {
                //Get all rooms where the user has permanent autor permissions:
                $my_rooms = RoomManager::getUserRooms(
                    $this->current_user,
                    'autor',
                    true,
                    null,
                    'resources.id NOT IN ( :room_ids ) ',
                    ['room_ids' => array_merge([$this->request->resource_id], $previously_selected_room_ids)]
                );
                $this->alternative_rooms = array_merge($this->alternative_rooms, $my_rooms);
            } elseif ($this->alternatives_selection == 'request' && !$this->config->RESOURCES_DIRECT_ROOM_REQUESTS_ONLY) {
                //Find rooms by the request's properties:
                $request_rooms = RoomManager::findRoomsByRequest(
                    $this->request,
                    $previously_selected_room_ids
                );
                $this->alternative_rooms = array_merge($this->alternative_rooms, $request_rooms);
            }
        }

        // add all booked rooms as well
        $booked_rooms = [];
        foreach($this->request_time_intervals as $key => $data) {
            foreach ($data['intervals'] as $timeslot) {
                if (!empty($timeslot['booked_rooms'])) {
                    $rooms = Room::findMany($timeslot['booked_rooms']);
                    if ($rooms) {
                        $booked_rooms = array_merge($booked_rooms, $rooms);
                    }
                }
            }
        }

        if (!empty($booked_rooms)) {
            $this->alternative_rooms = array_merge($booked_rooms, $this->alternative_rooms);
        }

        // deduplicate array
        $deduplicated = [];

        foreach ($this->alternative_rooms as $room) {
            if (
                (!$this->request_resource || $room->id !== $this->request_resource->id)
                && !isset($deduplicated[$room->id])
            ) {
                $deduplicated[$room->id] = $room;
            }
        }

        $this->alternative_rooms = $deduplicated;

        foreach ($this->alternative_rooms as $room) {
            $this->calculateRoomAvailabilityData($room);

            if ($this->request->getProperty('seats') > 0) {
                $this->room_underload[$room->id] =
                    round(((int)$room->seats / (int)$this->request->getProperty('seats')) * 100);
            }
        }

        $this->show_form = true;

        $force_resolve = Request::submitted('force_resolve');
        $resolve = Request::submitted('resolve') || $force_resolve;
        $this->show_force_resolve_button = false;
        $save_only = Request::submitted('save_only');

        $this->booked_room_infos = [];

        if ($resolve || $save_only) {
            CSRFProtection::verifyUnsafeRequest();
            $this->selected_rooms = array_filter(Request::getArray('selected_rooms'));
            $this->notification_settings = Request::get('notification_settings');
            $this->reply_comment = Request::get('reply_comment');

            $this->request->reply_comment = $this->reply_comment;
            if ($this->request->isDirty()) {
                $this->request->store();
            }
            if (!$this->selected_rooms) {
                PageLayout::postError(
                    _('Es wurde kein Raum ausgewählt!')
                );
                return;
            }

            if (count($this->selected_rooms) < $this->visible_dates && !$force_resolve && !$save_only) {
                PageLayout::postWarning(
                    _('Es wurden nicht für alle Termine der Anfrage Räume ausgewählt! Soll die Anfrage wirklich aufgelöst werden?')
                );
                $this->show_force_resolve_button = true;
                return;
            }

            $errors = [];
            $warnings = [];
            $bookings = [];

            //We must collect information about which rooms are booked
            //for which course date in the following loop.
            //We use this information to delete all bookings for the
            //course dates that are not in the array.
            $booked_rooms_ids_for_dates = [];

            foreach ($this->selected_rooms as $range_str => $room_id) {
                //Get room and check room permissions:
                $room = Resource::find($room_id);
                if (!$room) {
                    PageLayout::postError(
                        sprintf(
                            _('Es wurde kein Raum ausgewählt!'),
                            htmlReady($room_id)
                        )
                    );
                    return;
                }
                $room = $room->getDerivedClassInstance();
                if (!($room instanceof Room)) {
                    PageLayout::postError(
                        sprintf(
                            _('Die Ressource mit der ID %s ist kein Raum!'),
                            htmlReady($room_id)
                        )
                    );
                    return;
                }

                if (!$room->userHasPermission($this->current_user, 'autor')) {
                    PageLayout::postError(
                        sprintf(
                            _('Unzureichende Berechtigungen zum Buchen des Raumes %s!'),
                            htmlReady($room->name)
                        )
                    );
                    return;
                }

                $booking = null;
                //Get the range object:
                $range_data = explode('_', $range_str);
                if ($range_data[0] == 'CourseDate') {
                    $course_date = CourseDate::find($range_data[1]);
                    if (!($course_date instanceof CourseDate)) {
                        PageLayout::postError(
                            sprintf(
                                _('Der Veranstaltungstermin mit der ID %s wurde nicht gefunden!'),
                                htmlReady($range_data[1])
                            )
                        );
                        return;
                    }

                    $room_ids = [];
                    foreach ($course_date->room_bookings as $booking) {
                        $room_ids[] = $booking->resource_id;
                    }

                    if (!array_key_exists($course_date->id, $booked_rooms_ids_for_dates)) {
                        $booked_rooms_ids_for_dates[$course_date->id] = [];
                    }
                    if (in_array($room_id, $room_ids)) {
                        //The current room is already booked for the course
                        //date and shall remain booked for it.
                        $booked_rooms_ids_for_dates[$course_date->id][] = $room_id;
                    }

                    if (empty($room_ids) || !in_array($room_id, $room_ids)) {
                        try {
                            $booking = $room->createBooking(
                                $this->current_user,
                                $course_date->id,
                                [
                                    [
                                        'begin' => $course_date->date,
                                        'end'   => $course_date->end_time
                                    ]
                                ],
                                null,
                                0,
                                $course_date->end_time,
                                $this->request->preparation_time,
                                '',
                                '',
                                ResourceBooking::TYPE_NORMAL,
                                false,
                                '',
                                $this->request->subsequent_time
                            );
                            if ($booking instanceof ResourceBooking) {
                                $bookings[] = $booking;

                                //Note the booking in the array for booked rooms:
                                $booked_rooms_ids_for_dates[$course_date->id][] = $room_id;

                                if (!empty($this->booked_room_infos[$room->id])) {
                                    if ($this->booked_room_infos[$room->id]['first_booking_date'] > $booking->begin) {
                                        $this->booked_room_infos[$room->id]['first_booking_date'] = $booking->begin;
                                    }
                                } else {
                                    $this->booked_room_infos[$room->id] = [
                                        'room' => $room,
                                        'first_booking_date' => $booking->begin
                                    ];
                                }
                            }
                        } catch (Exception $e) {
                            $errors[] = $e->getMessage();
                            continue;
                        }
                    }
                } elseif ($range_data[0] == 'SeminarCycleDate') {
                    //Get the dates of the metadate and create a booking for
                    //each of them.
                    $metadate = SeminarCycleDate::find($range_data[1]);
                    if (!($metadate instanceof SeminarCycleDate)) {
                        PageLayout::postError(
                            sprintf(
                                _('Die Terminserie mit der ID %s wurde nicht gefunden!'),
                                htmlReady($range_data[1])
                            )
                        );
                        return;
                    }
                    if ($metadate->dates) {
                        $overlap_messages = [];
                        //Past dates are not shown in the resolve dialog
                        //(see getGroupedTimeIntervals above), so they must
                        //not be booked either. Otherwise dates that kept an
                        //old time after the regular time had been changed
                        //cause overlaps the admin cannot see.
                        $now = time();
                        $future_dates = $metadate->dates->filter(
                            fn (CourseDate $date): bool => $date->end_time >= $now
                        );
                        foreach ($future_dates as $date) {
                            $room_ids = [];
                            foreach ($date->room_bookings as $booking) {
                                $room_ids[] = $booking->resource_id;
                            }

                            if (!array_key_exists($date->id, $booked_rooms_ids_for_dates)) {
                                $booked_rooms_ids_for_dates[$date->id] = [];
                            }
                            if (in_array($room_id, $room_ids)) {
                                //The current room is already booked for the course
                                //date and shall remain booked for it.
                                $booked_rooms_ids_for_dates[$date->id][] = $room_id;
                            }

                            if (empty($room_ids) || !in_array($room_id, $room_ids)) {
                                try {
                                    $booking = $room->createBooking(
                                        $this->current_user,
                                        $date->id,
                                        [
                                            [
                                                'begin' => $date->date,
                                                'end'   => $date->end_time
                                            ]
                                        ],
                                        null,
                                        0,
                                        $date->end_time,
                                        $this->request->preparation_time,
                                        '',
                                        '',
                                        ResourceBooking::TYPE_NORMAL,
                                        false,
                                        '',
                                        $this->request->subsequent_time
                                    );
                                    if ($booking instanceof ResourceBooking) {
                                        $bookings[] = $booking;

                                        //Note the booking in the array for booked rooms:
                                        $booked_rooms_ids_for_dates[$date->id][] = $room_id;
                                    }
                                } catch (ResourceBookingOverlapException | ResourceBookingException $e) {
                                    $overlap_messages[] = $e->getMessage();
                                } catch (Exception $e) {
                                    $errors[] = $e->getMessage();
                                    continue;
                                }
                            }
                        }
                        if (count($overlap_messages) == count($future_dates)) {
                            //The booking could not be saved at all.
                            $errors = array_merge($errors, $overlap_messages);
                        } else {
                            $warnings = array_merge($warnings, $overlap_messages);
                        }
                    }
                } elseif ($range_data[0] == 'User') {
                    $user = User::find($range_data[1]);
                    if (!$user) {
                        PageLayout::postError(
                            sprintf(
                                _('Die Person mit der ID %s wurde nicht gefunden!'),
                                htmlReady($range_data[1])
                            )
                        );
                        return;
                    }
                    try {
                        $booking = $room->createBooking(
                            $this->current_user,
                            $user->id,
                            [
                                [
                                    'begin' => $this->request->begin,
                                    'end'   => $this->request->end
                                ]
                            ],
                            null,
                            0,
                            null,
                            $this->request->preparation_time,
                            $this->request->booking_text ?? '',
                            '',
                            ResourceBooking::TYPE_NORMAL,
                            false,
                            '',
                            $this->request->subsequent_time
                        );
                        if ($booking instanceof ResourceBooking) {
                            $bookings[] = $booking;
                        }
                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                        continue;
                    }
                } else {
                    PageLayout::postError(
                        sprintf(
                            _('Der Termin mit der ID %s ist mit einem unpassenden Stud.IP-Objekt verknüpft!'),
                            htmlReady($range_data[1])
                        )
                    );
                    return;
                }
            }

            if ($errors) {
                //Delete all bookings that have been made:
                foreach ($bookings as $booking) {
                    $booking->delete();
                }
                PageLayout::postError(
                    _('Es traten Fehler beim Auflösen der Anfrage auf!'),
                    $errors
                );
            } else if ($warnings && !$force_resolve && !$save_only) {
                //Nothing is booked until the resolving is confirmed.
                //The confirmation (force_resolve) creates the bookings again.
                foreach ($bookings as $booking) {
                    $booking->delete();
                }
                PageLayout::postWarning(
                    _('Es wurden nicht für alle Termine der Anfrage Räume ausgewählt! Soll die Anfrage wirklich aufgelöst werden?'),
                    $warnings
                );
                $this->show_force_resolve_button = true;
            } else {
                //No errors: We can delete old bookings that have been removed
                foreach ($booked_rooms_ids_for_dates as $date_id => $room_ids) {
                    ResourceBooking::deleteBySQL(
                        '`range_id` = :date_id AND `resource_id` NOT IN ( :room_ids )',
                        ['date_id' => $date_id, 'room_ids' => $room_ids]
                    );
                }
                if (!$save_only) {
                    //Close the request.
                    $success = $this->request->closeRequest(
                        $this->notification_settings == 'creator_and_lecturers',
                        $bookings
                    );
    
                    if ($success) {
                        $this->show_form = false;
                        PageLayout::postSuccess(
                            _('Die Anfrage wurde aufgelöst!')
                        );
                    } else {
                        PageLayout::postWarning(
                            _('Die Anfrage wurde aufgelöst, konnte aber nicht geschlossen werden!')
                        );
                    }
                }
            }

            if ($this->booked_room_infos) {
                //Sort the array:
                uasort(
                    $this->booked_room_infos,
                    function ($a, $b) {
                        if ($a['room']->name > $b['room']->name) {
                            return 1;
                        } elseif ($a['room']->name < $b['room']->name) {
                            return -1;
                        } else {
                            return 0;
                        }
                    }
                );
            }
        }

        if ($save_only) {
            // redirect to reload all infos and showing the most current ones
            $this->redirect('resources/room_request/resolve/' . $request_id);
        } elseif (Request::isDialog() && Context::get()) {
            $this->response->add_header('X-Dialog-Execute', '{"func": "STUDIP.AdminCourses.App.loadCourse", "payload": "' . Context::get()->id . '"}');
        }
    }

    public function decline_action($request_id = null)
    {
        $this->request = ResourceRequest::find($request_id);
        if (!$this->request) {
            PageLayout::postError(
                _('Die angegebene Anfrage wurde nicht gefunden!')
            );
            return;
        }
        $this->delete_mode = Request::get('delete');
        $request_ids = $this->getFilteredRoomRequests();
        if ($request_ids && count($request_ids) > 1) {
            $this->setRequestForPagination($request_ids);
        }
        if ($this->request->resource) {
            $user_has_permission = $this->request->resource->userHasPermission(
                $this->current_user,
                'tutor'
            );
            if ($this->delete_mode) {
                PageLayout::setTitle(
                    sprintf(
                        _('%s: Anfrage löschen'),
                        $this->request->resource->getFullName()
                    )
                );
            } else {
                PageLayout::setTitle(
                    sprintf(
                        _('%s: Anfrage ablehnen'),
                        $this->request->resource->getFullName()
                    )
                );
            }
        } else {
            $user_has_permission = ResourceManager::userHasGlobalPermission(
                $this->current_user,
                'tutor'
            );
            if ($this->delete_mode) {
                PageLayout::setTitle(_('Anfrage löschen'));
            } else {
                PageLayout::setTitle(_('Anfrage ablehnen'));
            }
        }
        if (!$user_has_permission) {
            throw new AccessDeniedException();
        }

        if (($this->request->closed >= 3) && !$this->delete_mode) {
            PageLayout::postInfo(
                _('Die angegebene Anfrage wurde bereits abgelehnt!')
            );
            return;
        }

        $this->show_form = true;

        if (Request::submitted('confirm')) {
            CSRFProtection::verifyUnsafeRequest();

            if ($this->delete_mode) {
                if ($this->request->delete()) {
                    $this->show_form = false;
                    PageLayout::postSuccess(_('Die Anfrage wurde gelöscht!'));
                } else {
                    PageLayout::postError(_('Fehler beim Löschen der Anfrage!'));
                }
            } else {
                $this->reply_comment = Request::get('reply_comment');
                $this->request->reply_comment = $this->reply_comment;
                $this->request->closed = '3';
                if ($this->request->isDirty()) {
                    if ($this->request->store()) {
                        $this->show_form = false;
                        PageLayout::postSuccess(
                            _('Die Anfrage wurde abgelehnt!')
                        );
                    } else {
                        PageLayout::postError(
                            _('Fehler beim Ablehnen der Anfrage!')
                        );
                    }
                }
            }
        }
    }

    protected function setRequestForPagination(array $request_ids)
    {
        $pos = array_search($this->filter['filter_request_id'], $request_ids);
        $max = count($request_ids);
        if ($pos === 0) {
            $prev_pos = $max - 1;
            $next_pos = $pos + 1;
        } else {
            $prev_pos = $pos - 1;
            $next_pos = $pos + 1;

            if ($next_pos === $max) {
                $next_pos = 0;
            }
        }
        $this->prev_request = $request_ids[$prev_pos] ?? null;
        $this->next_request = $request_ids[$next_pos] ?? null;
    }

    protected function getSingleDateDataForExportRow(CourseDate $date)
    {
        return [
            'date_amount'    => '1',
            'day_of_week'    => getWeekday(date('w', $date->date)),
            'time_string'    => sprintf(
                '%1$s - %2$s',
                date('H:i', $date->date),
                date('H:i', $date->end_time)
            ),
            'first_date_str' => date('d.m.Y', $date->date)
        ];
    }


    protected function getMetadateDataForExportRow(SeminarCycleDate $metadate)
    {
        $data = [
            'date_amount' => count($metadate->dates),
            'day_of_week' => getWeekday($metadate->weekday),
            'time_string' => sprintf(
                '%1$s:%2$02d - %3$s:%4$02d',
                $metadate->start_hour,
                $metadate->start_minute,
                $metadate->end_hour,
                $metadate->end_minute
            )
        ];
        $first_date = $metadate->dates[0];
        if ($first_date instanceof CourseDate) {
            $data['first_date_str'] = date('d.m.Y', $first_date->date);
        }
        return $data;
    }


    public function export_list_action()
    {
        $requests = $this->getFilteredRoomRequests();

        $table_head = [
            [
                _('Anfragende Person'),
                _('Fakultät'),
                _('Institut'),
                _('Veranstaltungsnummer'),
                _('Veranstaltungstitel'),
                _('Lehrende Person(en)'),
                _('Angefragter Raum'),
                _('Erwartete Teilnehmerzahl'),
                _('Sitzplätze'),
                _('Wochentag'),
                _('Uhrzeit'),
                _('Rüstzeit vor der Buchung'),
                _('Rüstzeit nach der Buchung'),
                _('Anzahl der Termine'),
                _('Art der Anfrage'),
                _('Datum des ersten Termins'),
                _('Datum der Anfrage'),
                _('Letzte Änderungen'),
                _('Markierung'),
                _('Priorität'),
                _('Bemerkungen')
            ]
        ];

        $table_body = [];
        foreach ($requests as $request) {
            $request = $request->getDerivedClassInstance();
            if (!$request instanceof RoomRequest) {
                continue;
            }
            $faculty_name = '';
            $institute_name = '';
            $lecturer_names = [];
            $course_number = '';
            $course_name = '';
            $room_name = '';
            $room_seats = '';
            if ($request->resource instanceof Resource) {
                $room = $request->resource->getDerivedClassInstance();
                if ($room instanceof Room) {
                    $room_name = $room->name;
                    $room_seats = $room->seats;
                }
            }
            if ($request->course instanceof Course) {
                $institutes = $request->course->institutes;
                foreach ($institutes as $institute) {
                    if ($institute instanceof Institute) {
                        if ($institute->isFaculty()) {
                            if ($faculty_name) {
                                $faculty_name .= "\n";
                            }
                            $faculty_name .= $institute->name;
                        } else {
                            if ($institute_name) {
                                $institute_name .= "\n";
                            }
                            $institute_name .= $institute->name;
                            if ($institute->faculty instanceof Institute) {
                                if ($faculty_name) {
                                    $faculty_name .= "\n";
                                }
                                $faculty_name .= $institute->faculty->name;
                            }
                        }
                    }
                }
                $course_name = $request->course->name;
                $course_number = $request->course->veranstaltungsnummer;
                $lecturers = $request->course->getMembersWithStatus('dozent');
                foreach ($lecturers as $lecturer) {
                    $lecturer_names[] = $lecturer->user->getFullName('no_title_rev');
                }
            }

            $date_data = [];
            $request_type = $request->getType();
            if ($request_type == 'course') {
                //Produce one row for each metadate and each single date.
                $metadates = $request->course->cycles;
                $single_dates = $request->course->dates;
                foreach ($metadates as $metadate) {
                    if ($metadate instanceof SeminarCycleDate) {
                        $date_data[] = $this->getMetadateDataForExportRow($metadate);
                    }
                }
                foreach ($single_dates as $single_date) {
                    if ($single_date instanceof CourseDate) {
                        if (!$single_date->cycle) {
                            $date_data[] = $this->getSingleDateDataForExportRow($single_date);
                        }
                    }
                }
            } elseif ($request_type == 'cycle' && $request->cycle instanceof SeminarCycleDate) {
                //Produce one row for the metadate.
                $date_data[] = $this->getMetadateDataForExportRow($request->cycle);
            } elseif ($request_type == 'date' && $request->date instanceof CourseDate) {
                //Produce one row for the single date.
                $date_data[] = $this->getSingleDateDataForExportRow($request->date);
            } else {
                //It is a simple request.
                //Produce one row for each date of the request.
                $time_intervals = $request->getTimeIntervals();
                foreach ($time_intervals as $interval) {
                    $first_date_str = date('d.m.Y', $interval['begin']);
                    $day_of_week = getWeekday(date('w', $interval['begin']));
                    $time_string = sprintf(
                        '%1$s - %2$s',
                        date('H:i', $interval['begin']),
                        date('H:i', $interval['end'])
                    );
                    $date_data[] = [
                        'date_amount'    => '1',
                        'day_of_week'    => $day_of_week,
                        'time_string'    => $time_string,
                        'first_date_str' => $first_date_str
                    ];
                }
            }

            //Build table rows:
            foreach ($date_data as $date_row) {
                $table_body[] = [
                    (                                  //Anfragende Person
                    $request->user instanceof User
                        ? $request->user->getFullName()
                        : ''
                    ),
                    $faculty_name,                     //Fakultät
                    $institute_name,                   //Institut
                    $course_number,                    //VA-Nummer
                    $course_name,                      //VA-Titel
                    implode(', ', $lecturer_names),    //Lehrende Personen
                    $room_name,                        //Angefragter Raum
                    $request->getProperty('seats'),    //Erwartete Teilnehmerzahl
                    $room_seats,                       //Sitzplätze
                    $date_row['day_of_week'],          //Wochentag
                    $date_row['time_string'],          //Uhrzeit
                    ($request->preparation_time / 60), //Rüstzeit vor der Buchung
                    ($request->subsequent_time / 60),  //Rüstzeit nach der Buchung
                    $date_row['date_amount'],          //Anzahl Termine
                    $request->getTypeString(true),     //Art der Anfrage
                    $date_row['first_date_str'],       //Datum des ersten Termins
                    date('d.m.Y', $request->mkdate),   //Datum der Anfrage
                    date('d.m.Y', $request->chdate),   //Letzte Änderungen
                    $request->marked,                  //Markierung
                    $request->getPriority(),           //Priorität
                    $request->comment                  //Bemerkungen
                ];
            }
        }
        $file_name = sprintf(
            _('Raumanfragen-%s') . '.csv',
            date('Y-m-d')
        );
        $this->render_csv(
            array_merge($table_head, $table_body),
            FileManager::cleanFileName($file_name)
        );
    }

    public function rerequest_booking_action($booking_id)
    {
        PageLayout::setTitle(_('Buchung in Anfrage wandeln'));

        $booking = ResourceBooking::find($booking_id);
        $cdate = CourseDate::find($booking->range_id);

        if (!$cdate) {
            $this->response->add_header('X-Dialog-Close', 1);
            $this->render_nothing();
            return;
        }

        if (Request::submitted('delete_confirm')) {
            CSRFProtection::verifyUnsafeRequest();

            if (!$booking->resource->userHasPermission($this->current_user, 'tutor') && !$GLOBALS['perm']->have_perm('root')) {
                //The user must not delete this booking!
                throw new AccessDeniedException();
            }

            $cycle = $cdate->cycle;
            $resource = Resource::find($booking->resource_id);
            $request = ResourceRequest::findByMetadate($cycle->metadate_id);
            if ($request && $request->closed > 0) {
                $request->closed = 0;
            } else {
                $request = new ResourceRequest();
                $request->course_id = $cycle->seminar_id;
                $request->termin_id = '';
                $request->metadate_id = $cycle->metadate_id;
                $request->user_id = $booking->booking_user_id;
                $request->resource_id = $booking->resource_id;
                $request->category_id = $resource->category_id;
                $request->setProperty('seats', $resource->getProperty('seats'));
            }

            if ($request->store()) {
                $booking_deleted = false;
                foreach ($cycle->getAllDates() as $bcdate) {
                    $bcdate_booking = ResourceBooking::findOneBySQL('range_id=?', [$bcdate->id]);
                    if ($bcdate_booking && $bcdate_booking->resource_id == $booking->resource_id) {
                        $booking_deleted = boolVal($bcdate_booking->delete());
                    }
                }

                if ($booking_deleted) {
                    PageLayout::postSuccess(
                        _('Die Buchung wurde in eine Anfrage umgewandelt!')
                    );
                } else {
                    PageLayout::postError(
                        _('Die Buchung konnte nicht gelöscht werden!')
                    );
                    $request->delete();
                }
            } else {
                PageLayout::postError(
                    _('Die Buchung konnte nicht in eine Anfrage umgewandelt werden!')
                );
            }

            $this->relocate($this->url_for("resources/room_request/planning"));
        }

        $this->booking = $booking;

        $this->user_has_user_perms = $this->booking->resource->userHasPermission(
                $this->current_user,
                'user'
            ) || $GLOBALS['perm']->have_perm('root');

        if (!$this->user_has_user_perms) {
            $resource = $this->booking->resource->getDerivedClassInstance();
            //The user has no permissions on the resource.
            //But if it is a room resource, we must check if the booking plan
            //is public. In such a case, viewing of the booking can be granted.
            if (!($resource->bookingPlanVisibleForUser($this->current_user))) {
                throw new AccessDeniedException();
            }
        }

    }

    public function quickbook_action($request_id, $room_id, $range_str)
    {
        $this->request = ResourceRequest::find($request_id);
        $this->selected_rooms = Request::getArray('selected_rooms');
        $this->notification_settings = Request::get('notification_settings');
        $this->show_force_resolve_button = true;

        $errors = [];
        $bookings = [];

        //Get room and check room permissions:
        $room = Resource::find($room_id);
        if (!$room) {
            PageLayout::postError(
                sprintf(
                    _('Es wurde kein Raum ausgewählt!'),
                    htmlReady($room_id)
                )
            );
            return;
        }
        $room = $room->getDerivedClassInstance();
        if (!($room instanceof Room)) {
            PageLayout::postError(
                sprintf(
                    _('Die Ressource mit der ID %s ist kein Raum!'),
                    htmlReady($room_id)
                )
            );
            return;
        }

        if (!$room->userHasPermission($this->current_user, 'autor')) {
            PageLayout::postError(
                sprintf(
                    _('Unzureichende Berechtigungen zum Buchen des Raumes %s!'),
                    htmlReady($room->name)
                )
            );
            return;
        }

        $booking = null;
        //Get the range object:
        $range_data = explode('_', $range_str);
        if ($range_data[0] == 'CourseDate') {
            $course_date = CourseDate::find($range_data[1]);
            if (!($course_date instanceof CourseDate)) {
                PageLayout::postError(
                    sprintf(
                        _('Der Veranstaltungstermin mit der ID %s wurde nicht gefunden!'),
                        htmlReady($range_data[1])
                    )
                );
                return;
            }
            $range_name = $course_date->course->getFullName();
            try {
                $booking = $room->createBooking(
                    $this->current_user,
                    $course_date->id,
                    [
                        [
                            'begin' => $course_date->date,
                            'end'   => $course_date->end_time
                        ]
                    ],
                    null,
                    0,
                    $course_date->end_time,
                    $this->request->preparation_time,
                    '',
                    '',
                    ResourceBooking::TYPE_NORMAL,
                    false,
                    '',
                    $this->request->subsequent_time
                );
                if ($booking instanceof ResourceBooking) {
                    $bookings[] = $booking;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($range_data[0] == 'SeminarCycleDate') {
            //Get the dates of the metadate and create a booking for
            //each of them.
            $metadate = SeminarCycleDate::find($range_data[1]);
            if (!($metadate instanceof SeminarCycleDate)) {
                PageLayout::postError(
                    sprintf(
                        _('Die Terminserie mit der ID %s wurde nicht gefunden!'),
                        htmlReady($range_data[1])
                    )
                );
                return;
            }
            $range_name = $metadate->course->getFullName();
            if ($metadate->dates) {
                //Like in resolve_action: past dates are not booked.
                $now = time();
                foreach ($metadate->dates as $date) {
                    if ($date->end_time < $now) {
                        continue;
                    }
                    try {
                        $booking = $room->createBooking(
                            $this->current_user,
                            $date->id,
                            [
                                [
                                    'begin' => $date->date,
                                    'end'   => $date->end_time
                                ]
                            ],
                            null,
                            0,
                            $date->end_time,
                            $this->request->preparation_time,
                            '',
                            '',
                            ResourceBooking::TYPE_NORMAL,
                            false,
                            '',
                            $this->request->subsequent_time
                        );
                        if ($booking instanceof ResourceBooking) {
                            $bookings[] = $booking;
                        }
                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                        continue;
                    }
                }
            }
        } elseif ($range_data[0] == 'User') {
            $user = User::find($range_data[1]);
            if (!($user instanceof User)) {
                PageLayout::postError(
                    sprintf(
                        _('Die Person mit der ID %s wurde nicht gefunden!'),
                        htmlReady($range_data[1])
                    )
                );
                return;
            }
            $range_name = $user->getFullName();
            try {
                $booking = $room->createBooking(
                    $this->current_user,
                    $user->id,
                    [
                        [
                            'begin' => $this->request->begin,
                            'end'   => $this->request->end
                        ]
                    ],
                    null,
                    0,
                    null,
                    $this->request->preparation_time,
                    '',
                    '',
                    ResourceBooking::TYPE_NORMAL,
                    false,
                    '',
                    $this->request->subsequent_time
                );
                if ($booking instanceof ResourceBooking) {
                    $bookings[] = $booking;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        } else {
            PageLayout::postError(
                sprintf(
                    _('Der Termin mit der ID %s ist mit einem unpassenden Stud.IP-Objekt verknüpft!'),
                    htmlReady($range_data[1])
                )
            );
            return;
        }

        if ($errors) {
            //Delete all bookings that have been made:
            foreach ($bookings as $booking) {
                $booking->delete();
            }
            PageLayout::postError(
                sprintf(
                    _('Es traten Fehler beim Auflösen der Anfrage auf für %s!'),
                    htmlReady($range_name)
                ),
                $errors
            );
        } else {
            //No errors: We can close the request.
            $success = $this->request->closeRequest(
                $this->notification_settings == 'creator_and_lecturers',
                $bookings
            );

            if ($success) {
                $this->show_form = false;
                PageLayout::postSuccess(
                    sprintf(_('Die Anfrage für %s wurde aufgelöst!'), htmlReady($range_name))
                );
            } else {
                PageLayout::postWarning(
                    sprintf(
                        _('Die Anfrage für %s wurde aufgelöst, konnte aber nicht geschlossen werden!'),
                        htmlReady($range_name)
                    )
                );
            }
        }

        if (Request::isAjax()) {
            $this->render_nothing();
        } else {
            $this->relocate($this->url_for("resources/room_request/planning"));
        }
    }

    public function planning_action()
    {
        if (Navigation::hasItem('/resources/planning/requests_planning')) {
            Navigation::activateItem('/resources/planning/requests_planning');
        }

        PageLayout::setTitle(_('Anfragenplan'));
        PageLayout::allowFullscreenMode();

        $this->setupSidebar('planning');

        $this->requests = $this->getFilteredRoomRequests();

        if (!empty($this->filter['room_id'])) {
            $this->resource = Resource::find($this->filter['room_id']);
            if (!$this->resource) {
                PageLayout::postError(
                    _('Die angegebene Ressource wurde nicht gefunden!')
                );
                return;
            }

            URLHelper::addLinkParam('resource_id', $this->resource->id);
            $this->resource = $this->resource->getDerivedClassInstance();
            $this->privileged = $this->resource->userHasPermission($this->current_user, 'autor');

            $this->semester_name        = '';
            $this->semester_range_start = 0;
            $this->semester_range_end   = 0;
            if ($this->filter['semester']) {
                $id_parts       = explode('_', $this->filter['semester']);
                $semester_id    = $id_parts[0];
                $this->semester = Semester::find($semester_id);
                if ($this->semester) {
                    if (count($id_parts) > 1) {
                        if ($id_parts[1] === 'lecture') {
                            $this->semester_name        = studip_interpolate(
                                _('Semester %{semester_name} (Vorlesungszeit)'),
                                ['semester_name' => $this->semester->name]
                            );
                            $this->semester_range_start = $this->semester->vorles_beginn;
                            $this->semester_range_end   = $this->semester->vorles_ende;
                        } elseif ($id_parts[1] === 'vacation') {
                            $this->semester_name        = studip_interpolate(
                                _('Vorlesungsfrei nach %{semester_name}'),
                                ['semester_name' => $this->semester->name]
                            );
                            $this->semester_range_start = $this->semester->vorles_ende;
                            $next_semester = Semester::findByTimestamp($this->semester->ende + 1);
                            if ($next_semester) {
                                $this->semester_range_end = $next_semester->vorles_beginn;
                            } else {
                                //The selected semester is the last available semester.
                                $this->semester_range_end = PHP_INT_MAX;
                            }
                        }
                    } else {
                        $this->semester_name        = studip_interpolate(
                            _('Semester %{semester_name}'),
                            ['semester_name' => $this->semester->name]
                        );
                        $this->semester_range_start = $this->semester->beginn;
                        $this->semester_range_end   = $this->semester->ende;
                    }
                }
            } else {
                $this->semester = Semester::findCurrent();
                if ($this->semester) {
                    $this->semester_name        = studip_interpolate(
                        _('Semester %{semester_name}'),
                        ['semester_name' => $this->semester->name]
                    );
                    $this->semester_range_start = $this->semester->beginn;
                    $this->semester_range_end   = $this->semester->ende;
                }
            }

            $booking_colour = ColourValue::find('Resources.BookingPlan.Booking.Bg');
            $course_booking_colour = ColourValue::find('Resources.BookingPlan.CourseBooking.Bg');
            $lock_colour = ColourValue::find('Resources.BookingPlan.Lock.Bg');
            $preparation_colour = ColourValue::find('Resources.BookingPlan.PreparationTime.Bg');
            $reservation_colour = ColourValue::find('Resources.BookingPlan.Reservation.Bg');
            $request_colour = ColourValue::find('Resources.BookingPlan.Request.Bg');
            $this->table_keys = [
                [
                    'colour' => (string)$booking_colour,
                    'text'   => _('Manuelle Buchung')
                ],
                [
                    'colour' => (string)$course_booking_colour,
                    'text'   => _('Veranstaltungsbezogene Buchung')
                ],
                [
                    'colour' => (string)$lock_colour,
                    'text'   => _('Sperrbuchung')
                ],
                [
                    'colour' => (string)$preparation_colour,
                    'text'   => _('Rüstzeit')
                ],
                [
                    'colour' => (string)$reservation_colour,
                    'text'   => _('Reservierung')
                ],
            ];
            $this->event_color = $request_colour;
        }
    }

    private function getFilters(array $defaults = []): array
    {
        $user_config = User::findCurrent()->getConfiguration();

        $filters = [
            'get_only_request_ids' => false,
            'semester'             => $user_config->MY_COURSES_SELECTED_CYCLE,
            'institute'            => $user_config->MY_INSTITUTES_DEFAULT,
        ];

        if (!$filters['semester']) {
            //Set the current semester if no semester is preselected:
            $semester = Semester::findCurrent();
            if ($semester) {
                $filters['semester'] = $semester->id;
            }
        }

        if ($filters['institute'] === 'all') {
            unset($filters['institute']);
        }

        return array_merge(
            $filters,
            $defaults,
            $_SESSION[__CLASS__]['filter'] ?? []
        );
    }

    public function filter_action(string $key, ?string $value = null): void
    {
        $config_filters = [
            'semester'  => 'MY_COURSES_SELECTED_CYCLE',
            'institute' => 'MY_INSTITUTES_DEFAULT',
        ];

        if ($key === 'from_request') {
            $key = $value;
            $value = Request::option($key);
        }

        if (strlen($value) === 0) {
            $value = null;
        }

        if (isset($config_filters[$key])) {
            User::findCurrent()->getConfiguration()->store(
                $config_filters[$key],
                $value
            );
        } elseif ($value === null && isset($_SESSION[__CLASS__]['filter'][$key])) {
            unset($_SESSION[__CLASS__]['filter'][$key]);
        } else {
            $_SESSION[__CLASS__]['filter'][$key] = $value;
        }

        $from = Request::option('from', 'overview');
        $this->redirect($this->action_url($from));
    }

    public function reset_filter_action(string $return_to): void
    {
        if (!$this->has_action($return_to)) {
            throw new InvalidArgumentException('Invalid return_to path');
        }

        unset($_SESSION[__CLASS__]['filter']);

        $this->redirect($this->action_url($return_to));
    }

    private function setupSidebar(string $action): void
    {
        $from_params = $action === 'overview' ? [] : ['from' => $action];

        $sidebar = Sidebar::get();

        if (!empty($_SESSION[__CLASS__]['filter'])) {
            $filter_reset_widget = new ActionsWidget();
            $filter_reset_widget->addLink(
                _('Filter zurücksetzen'),
                $this->reset_filterURL($action),
                Icon::create('decline')
            );
            $sidebar->addWidget($filter_reset_widget);
        }

        $institute_selector = new InstituteSelectWidget(
            $this->filterURL('from_request', 'institute', $from_params),
            'institute',
            'get'
        );
        $institute_selector->includeAllOption();
        $institute_selector->setSelectedElementIds($this->filter['institute'] ?? []);
        $sidebar->addWidget($institute_selector);

        $semester_selector = new SemesterSelectorWidget(
            $this->filterURL('from_request', 'semester', $from_params),
            'semester',
            'get'
        );
        $semester_selector->makeLecturePeriodSelectable();
        $semester_selector->makeVacationPeriodSelectable();
        $semester_selector->setSelection($this->filter['semester']);
        if ($action === 'overview') {
            $semester_selector->setRange(time(), PHP_INT_MAX);
        }
        $sidebar->addWidget($semester_selector);

        if ($action === 'overview') {
            $request_status_selector = new SelectWidget(
                _('Status der Anfrage'),
                $this->filterURL('from_request', 'request_status', $from_params),
                'request_status'
            );
            $request_status_selector->setOptions([
                '' => _('offen'),
                'closed' => _('bearbeitet'),
                'denied' => _('abgelehnt'),
            ], $this->filter['request_status'] ?? null);
            $sidebar->addWidget($request_status_selector);
        }

        $list = new SelectWidget(
            _('Veranstaltungstypfilter'),
            $this->filterURL('from_request', 'course_type', $from_params),
            'course_type'
        );
        $list->addElement(
            new SelectElement(
                '',
                _('Alle'),
                empty($this->filter['course_type'])
            ),
            'course-type-all'
        );

        foreach (SemClass::getClasses() as $class_id => $class) {
            if ($class['studygroup_mode']) {
                continue;
            }

            $element = new SelectElement(
                $class_id,
                $class['name'],
                isset($this->filter['course_type']) && $this->filter['course_type'] === (string) $class_id
            );
            $list->addElement(
                $element->setAsHeader(),
                'course-type-' . $class_id
            );

            foreach ($class->getSemTypes() as $id => $result) {
                $element = new SelectElement(
                    $class_id . '_' . $id,
                    $result['name'],
                    isset($this->filter['course_type']) && ($this->filter['course_type'] === $class_id . '_' . $id)
                );
                $list->addElement(
                    $element->setIndentLevel(1),
                    'course-type-' . $class_id . '_' . $id
                );
            }
        }
        $sidebar->addWidget($list, 'filter-course-type');

        if ($action === 'overview') {
            $widget = new SelectWidget(
                _('Raumgruppen'),
                $this->filterURL('from_request', 'group', $from_params),
                'group'
            );
            $widget->addElement(
                new SelectElement(
                    '',
                    _('Alle'),
                    empty($this->filter['group'])
                ),
                'clip-all'
            );
            foreach (Clipboard::getClipboardsForUser(User::findCurrent()->id, ['Room']) as $clip) {
                $widget->addElement(
                    new SelectElement(
                        $clip->id,
                        $clip->name,
                        isset($this->filter['group']) && $this->filter['group'] == $clip->id
                    ),
                    'clip-' . $clip->id
                );
            }
            $sidebar->addWidget($widget);
        }

        $widget = new SelectWidget(
            _('Räume'),
            $this->filterURL('from_request', 'room_id', $from_params),
            'room_id'
        );
        $widget->addElement(
            new SelectElement(
                '',
                _('Bitte wählen'),
                empty($this->filter['room_id'])
            )
        );
        foreach ($this->available_rooms as $room) {
            $widget->addElement(
                new SelectElement(
                    $room->id,
                    $room->name,
                    !empty($this->filter['room_id']) && $room->id == $this->filter['room_id']
                )
            );
        }
        $sidebar->addWidget($widget);



        $widget = new OptionsWidget(_('Filter'));
        $widget->addRadioButton(
            _('Alle Anfragen'),
            $this->filterURL('marked', $from_params),
            !isset($this->filter['marked'])
        );
        $widget->addRadioButton(
            _('Nur markierte Anfragen'),
            $this->filterURL('marked', 1, $from_params),
            isset($this->filter['marked']) && $this->filter['marked'] == 1
        );
        $widget->addRadioButton(
            _('Nur unmarkierte Anfragen'),
            $this->filterURL('marked', 0, $from_params),
            isset($this->filter['marked']) && $this->filter['marked'] == 0
        );
        $widget->addElement(new WidgetElement('<br>'));

        if ($action === 'overview') {
            $widget->addRadioButton(
                _('Alle Termine'),
                $this->filterURL('request_periods', $from_params),
                !isset($this->filter['request_periods'])
            );
            $widget->addRadioButton(
                _('Nur regelmäßige Termine'),
                $this->filterURL('request_periods', 'periodic', $from_params),
                isset($this->filter['request_periods']) && $this->filter['request_periods'] == 'periodic'
            );
            $widget->addRadioButton(
                _('Nur unregelmäßige Termine'),
                $this->filterURL('request_periods', 'aperiodic', $from_params),
                isset($this->filter['request_periods']) && $this->filter['request_periods'] == 'aperiodic'
            );
            $widget->addElement(new WidgetElement('<br>'));
        }

        $widget->addCheckbox(
            _('Nur mit Raumangabe'),
            !empty($this->filter['specific_requests']),
            $this->filterURL('specific_requests', empty($this->filter['specific_requests']) ?: '', $from_params)
        );
        $widget->addCheckbox(
            _('Eigene Anfragen anzeigen'),
            !empty($this->filter['own_requests']),
            $this->filterURL('own_requests', empty($this->filter['own_requests']) ?: '', $from_params)
        );
        $sidebar->addWidget($widget);

        if ($action === 'overview') {
            $dow_selector = new SelectWidget(
                _('Wochentag'),
                $this->filterURL('from_request', 'dow', $from_params),
                'dow'
            );
            $dow_selector->addElement(
                new SelectElement(
                    '',
                    _('Alle'),
                    empty($this->filter['dow'])
                ),
                'dow-all'
            );
            foreach (range(1, 7) as $day) {
                $dow_selector->addElement(
                    new SelectElement(
                        $day,
                        strftime('%A', strtotime('this monday +' . ($day - 1) . ' day')),
                        isset($this->filter['dow']) && $this->filter['dow'] == $day
                    ),
                    'dow-' . $day
                );
            }
            $sidebar->addWidget($dow_selector, 'filter-dow');
        }
    }
}
