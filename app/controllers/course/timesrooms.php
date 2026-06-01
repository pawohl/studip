<?php
/**
 * @author  David Siegfried <david.siegfried@uni-vechta.de>
 * @license GPL2 or any later version
 * @since   3.4
 */

class Course_TimesroomsController extends AuthenticatedController
{
    /**
     * Common actions before any other action
     *
     * @param String $action Action to be executed
     * @param Array  $args Arguments passed to the action
     *
     * @throws Trails\Exception when either no course was found or the user
     *                          may not access this area
     */
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        // Try to find a valid course
        if (!Course::findCurrent()) {
            throw new Trails\Exception(404, _('Es wurde keine Veranstaltung ausgewählt!'));
        }

        if (!$GLOBALS['perm']->have_studip_perm('tutor', Course::findCurrent()->id)) {
            throw new AccessDeniedException();
        }

        // Get seminar instance
        $this->course = Course::findCurrent();

        if (Navigation::hasItem('course/admin/dates')) {
            Navigation::activateItem('course/admin/dates');
        }
        $this->locked = false;

        if (LockRules::Check($this->course->id, 'room_time')) {
            $this->locked     = true;
            $this->lock_rules = LockRules::getObjectRule($this->course->id);
            PageLayout::postInfo(
                _('Diese Seite ist für die Bearbeitung gesperrt. Sie können die Daten einsehen, jedoch nicht verändern.')
                . ($this->lock_rules['description'] ? '<br>' . formatLinks($this->lock_rules['description']) : '')
            );
        }

        $this->show = [
            'regular'     => true,
            'irregular'   => true,
            'roomRequest' => !$this->locked && Config::get()->RESOURCES_ENABLE && Config::get()->RESOURCES_ALLOW_ROOM_REQUESTS,
        ];

        PageLayout::setHelpKeyword('Basis.Veranstaltungen');

        $title = _('Verwaltung von Zeiten und Räumen');
        $title = $this->course->getFullName() . ' - ' . $title;

        PageLayout::setTitle($title);

        $parameters = [
            ':course_id' => $this->course->id,
            ':beginning' => $this->course->start_semester->beginn,
        ];
        if ($this->course->isOpenEnded()) {
            $condition_in = '`range_id` = :course_id AND `date` >= :beginning';
            $condition_out = '`range_id` = :course_id AND `date` < :beginning';
        } else {
            $condition_in = '`range_id` = :course_id AND `date` BETWEEN :beginning AND :end';
            $condition_out = '`range_id` = :course_id AND `date` NOT BETWEEN :beginning AND :end';
            $parameters[':end'] = $this->course->end_semester->vorles_ende;
        }

        $dates_in_time_range = CourseDate::countBySql($condition_in, $parameters) > 0;
        $dates_outside_of_time_range = CourseDate::countBySql($condition_out, $parameters) > 0;

        URLHelper::bindLinkParam('semester_filter', $this->semester_filter);

        if (empty($this->semester_filter)) {
            if ($dates_in_time_range && count($this->course->semesters) == 1) {
                $this->semester_filter = $this->course->start_semester->id;
            } else {
                $this->semester_filter = 'all';
            }
        }

        if ($this->course->isOpenEnded()) {
            $selectable_semesters = Semester::getAll();
        } else {
            $selectable_semesters = $this->course->semesters->toArray();
        }

        if (count($selectable_semesters) > 1 || (count($selectable_semesters) == 1 && $dates_outside_of_time_range)) {
            $selectable_semesters[] = ['name' => _('Alle Semester'), 'semester_id' => 'all'];
        }
        $this->selectable_semesters = array_reverse($selectable_semesters);

        if (!Request::isXhr()) {
            $this->setSidebar();
        } elseif (Request::isXhr() && $this->flash['update-times']) {
            $semester_id = $GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE ?? '';
            $semester = null;
            if ($this->semester_filter !== 'all') {
                $semester = Semester::find($this->semester_filter);
                if (!$semester && $semester_id) {
                    $semester = Semester::find($this->semester_id);
                }
            }

            $dates = $this->course->getAllDatesInSemester($semester, $semester);
            $this->response->add_header(
                'X-Raumzeit-Update-Times',
                json_encode([
                    'course_id' => $this->course->id,
                    'html'      => $dates->toHtml(false, true),
                ])
            );
        }
    }



    protected function bookingTooShort(int $start_time, int $end_time)
    {
        return Config::get()->RESOURCES_MIN_BOOKING_TIME &&
            (($end_time - $start_time) < Config::get()->RESOURCES_MIN_BOOKING_TIME * 60);
    }

    /**
     * Displays the times and rooms of a course
     *
     * @param mixed $course_id Id of the course (optional, defaults to
     *                         globally selected)
     */
    public function index_action()
    {
        Helpbar::get()->addPlainText(_('Rot'), _('Kein Termin hat eine Raumbuchung.'));
        Helpbar::get()->addPlainText(_('Gelb'), _('Mindestens ein Termin hat keine Raumbuchung.'));
        Helpbar::get()->addPlainText(_('Grün'), _('Alle Termine haben eine Raumbuchung.'));

        if (Request::isXhr()) {
            $this->show = [
                'regular'     => true,
                'irregular'   => true,
                'roomRequest' => true,
            ];
        }
        $this->linkAttributes   = ['fromDialog' => Request::isXhr() ? 1 : 0];
        $this->semester         = array_reverse(Semester::getAll());
        $this->current_semester = Semester::findCurrent();
        $this->cycle_dates      = [];
        $matched                = [];

        $this->cycle_room_names = [];

        foreach ($this->course->cycles as $cycle) {
            $cycle_has_multiple_rooms = false;
            foreach ($cycle->getAllDates() as $val) {
                foreach ($this->semester as $sem) {
                    if ($this->semester_filter !== 'all' && $this->semester_filter !== $sem->id) {
                        continue;
                    }

                    if ($sem->beginn <= $val->date && $sem->ende >= $val->date) {
                        if (!isset($this->cycle_dates[$cycle->metadate_id])) {
                            $this->cycle_dates[$cycle->metadate_id] = [
                                'cycle'        => $cycle,
                                'dates'        => [],
                                'room_request' => [],
                            ];
                        }
                        if (!isset($this->cycle_dates[$cycle->metadate_id]['dates'][$sem->id])) {
                            $this->cycle_dates[$cycle->metadate_id]['dates'][$sem->id] = [];
                        }
                        $this->cycle_dates[$cycle->metadate_id]['dates'][$sem->id][] = $val;
                        if ($rooms = $val->getRooms()) {
                            $first_room = reset($rooms);
                            if ($first_room) {
                                $this->cycle_dates[$cycle->metadate_id]['room_request'][] = $first_room;
                            }
                        }
                        $matched[] = $val->termin_id;
                        if ($val instanceof CourseDate) {
                            //Check if a room is booked for the date:
                            foreach ($val->room_bookings as $room_booking) {
                                if (($room_booking instanceof ResourceBooking)
                                    && !$cycle_has_multiple_rooms) {
                                    $date_room = $room_booking->resource->name;
                                    if (isset($this->cycle_room_names[$cycle->id])) {
                                        if ($date_room && $date_room != $this->cycle_room_names[$cycle->id]) {
                                            $cycle_has_multiple_rooms = true;
                                        }
                                    } elseif ($date_room) {
                                        $this->cycle_room_names[$cycle->id] = $date_room;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($cycle_has_multiple_rooms) {
                $this->cycle_room_names[$cycle->id] = _('mehrere gebuchte Räume');
            }
        }

        $dates = $this->course->getDatesWithExdates();

        $this->current_user = User::findCurrent();
        $this->user_has_permissions = ResourceManager::userHasGlobalPermission($this->current_user, 'admin');

        $check_room_requests = Config::get()->RESOURCES_ALLOW_ROOM_REQUESTS;
        $this->room_requests = RoomRequest::findBySQL(
            'course_id = :course_id
            ORDER BY course_id, metadate_id, termin_id',
            [
                'course_id' => $this->course->id
            ]
        );

        $this->global_requests = $this->course->room_requests->filter(function (RoomRequest $request) {
            return $request->closed < 2 && !$request->termin_id;
        });

        $single_dates  = [];
        $this->single_date_room_request_c = 0;
        foreach ($dates as $val) {
            foreach ($this->semester as $sem) {
                if ($this->semester_filter !== 'all' && $this->semester_filter !== $sem->id) {
                    continue;
                }

                if ($sem->beginn > $val->date || $sem->ende < $val->date || $val->metadate_id != '') {
                    continue;
                }

                if (!isset($single_dates[$sem->id])) {
                    $single_dates[$sem->id] = new SimpleCollection();
                }
                $single_dates[$sem->id]->append($val);

                $matched[] = $val->id;
                if ($check_room_requests) {
                    $this->single_date_room_request_c += ResourceRequest::countBySql(
                        "resource_requests.closed < '2' AND termin_id = :termin_id",
                        [
                            'termin_id' => $val->id
                        ]
                    );
                }
            }
        }

        if ($this->semester_filter === 'all') {
            $out_of_bounds = $dates->findBy('id', $matched, '!=');
            if (count($out_of_bounds)) {
                $single_dates['none'] = $out_of_bounds;
            }
        }

        $this->single_dates  = $single_dates;
        $this->checked_dates = [];
        if (!empty($_SESSION['_checked_dates'])) {
            $this->checked_dates = $_SESSION['_checked_dates'];
            unset($_SESSION['_checked_dates']);
        }
        if (Request::isDialog()) {
            $this->response->add_header('X-Dialog-Execute', '{"func": "STUDIP.AdminCourses.App.loadCourse", "payload": "' . $this->course->id . '"}');
        }
    }

    /**
     * Edit the start-semester of a course
     *
     * @throws Trails\Exceptions\DoubleRenderError
     */
    public function editSemester_action()
    {
        URLHelper::addLinkParam('origin', Request::option('origin', 'course_timesrooms'));
        $this->semester = array_reverse(Semester::getAll());
        $this->current_semester = Semester::findCurrent();
        if (Request::submitted('save')) {
            CSRFProtection::verifyUnsafeRequest();
            $start_semester = Semester::find(Request::get('startSemester'));
            if (Request::get('endSemester') != '-1' && Request::get('endSemester') != '0') {
                $end_semester = Semester::find(Request::get('endSemester'));
            } else {
                $end_semester = Request::int('endSemester');
            }

            $course = Course::findCurrent();

            if ($start_semester == $end_semester) {
                $end_semester = 0;
            }

            if ($end_semester != 0 && $end_semester != -1 && $start_semester->beginn >= $end_semester->beginn) {
                PageLayout::postError(_('Das Startsemester liegt nach dem Endsemester!'));
            } else {
                $old_start_weeks = !$course->isOpenEnded() ? $course->start_semester->getStartWeeks($course->end_semester) : [];
                //set the new semester array:
                if ($end_semester == -1) {
                    $course->semesters = [];
                } elseif($end_semester == 0)  {
                    $course->semesters = [$start_semester];
                } else {
                    $selected_semesters = [];
                    foreach (Semester::getAll() as $sem) {
                        if ($sem['beginn'] >= $start_semester['beginn'] && $sem['ende'] <= $end_semester['ende']) {
                            $selected_semesters[] = $sem;
                        }
                    }
                    $course->semesters = $selected_semesters;
                }

                //Set the semester selector to the first semester:
                $this->semester_filter = $start_semester->semester_id;

                $course->store();

                if (!$course->isOpenEnded()) {
                    $new_start_weeks = $course->start_semester->getStartWeeks($course->end_semester);
                    $start = $this->course->start_semester->beginn;
                    $end   = $this->course->end_semester->vorles_ende;
                    SeminarCycleDate::removeOutRangedSingleDates($start, $end, $course->id);
                    $cycles = SeminarCycleDate::findBySeminar_id($course->id);
                    foreach ($cycles as $cycle) {
                        $cycle->end_offset = $this->getNewEndOffset($cycle, $old_start_weeks, $new_start_weeks);
                        $cycle->generateNewDates();
                        $cycle->store();
                    }
                }

                $this->relocate(str_replace('_', '/', Request::option('origin')));
            }
            if (Request::isDialog()) {
                $this->response->add_header('X-Dialog-Execute', '{"func": "STUDIP.AdminCourses.App.loadCourse", "payload": "' . $this->course->id . '"}');
            }
        }
    }

    /**
     * Action to edit a single date.
     *
     * @param string $termin_id
     */
    public function editDate_action($termin_id)
    {
        PageLayout::setTitle(_('Einzeltermin bearbeiten'));
        $this->date       = CourseDate::find($termin_id) ?: CourseExDate::find($termin_id);
        $this->date_types = [];
        foreach ($GLOBALS['TERMIN_TYP'] as $id => $data) {
            $this->date_types[] = [
                'id'   => $id,
                'name' => $data['name']
            ];
        }

        $this->selected_room_ids = [];
        if (Config::get()->RESOURCES_ENABLE) {
            //Collect all room bookings:
            $booked_rooms    = [];
            $separable_rooms = [];
            if ($this->date->room_bookings) {
                foreach ($this->date->room_bookings as $booking) {
                    $room = $booking->resource;
                    if ($room instanceof Resource) {
                        $room = $room->getDerivedClassInstance();
                        if (!$room->userHasBookingRights(User::findCurrent())) {
                            PageLayout::postWarning(
                                studip_interpolate(
                                    _('Die Buchung des Raumes %{room_name} zu diesem Termin wird bei der Verlängerung des Zeitbereiches gelöscht, da sie keine Buchungsrechte an dem Raum haben!'),
                                    ['room_name' => htmlReady($room->name)]
                                )
                            );
                        }
                        //Check if the room is part of a separable room:
                        if (count($room->separable_room)) {
                            $sr = $room->separable_room[0];
                            $separable_rooms[$sr->id] = $sr;
                        }

                        $booked_rooms[strval($booking->resource->id)] = $booking->resource->getDerivedClassInstance();
                    }
                }

                //Loop over all separable rooms and check if the IDs of all of their parts
                //are present in the $booked_room_ids array:
                foreach ($separable_rooms as $separable_room) {
                    $room_part_ids = [];
                    foreach ($separable_room->parts as $part) {
                        if (!in_array($part->room_id, array_keys($booked_rooms))) {
                            //The separable room is not fully booked and can be skipped.
                            continue 2;
                        }
                        $room_part_ids[] = $part->room_id;
                    }
                    //At this point, all the parts of the separable room are booked
                    //so that the separable room can be added to the $assigned_room_ids array
                    //and its parts can be removed from the $booked_room_ids array.
                    $this->selected_room_ids[] = [
                        'id' => 'separable_room-' . $separable_room->id,
                        'label' => sprintf(
                            '%1$s (%2$s)',
                            $separable_room->name,
                            _('Teilbarer Raum'),
                        ),
                        'separable_room_id' => $separable_room->id,
                        'info_text' => sprintf('%1$s: %2$s', $separable_room->name, $separable_room->description)
                    ];
                    //Filter out the room parts from the list of booked rooms:
                    $booked_rooms = array_filter(
                        $booked_rooms,
                        function ($item) use ($room_part_ids) {
                            return !in_array($item->id, $room_part_ids);
                        }
                    );
                }

                //All the remaining entries in $booked_room_ids are separable rooms that are
                //only partially booked or ordinary rooms:
                foreach ($booked_rooms as $room) {
                    $room_data = [
                        'id'    => 'room-' . $room->id,
                        'label' => studip_interpolate(
                            _('%{room_name} (%{seats} Sitzplätze)'),
                            [
                                'room_name' => $room->getFullName(),
                                'seats'     => $room->seats
                            ]
                        )
                    ];
                    if (count($room->separable_room) > 0) {
                        $first_separable_room = $room->separable_room[0];
                        $room_data['label']   = studip_interpolate(
                            _('%{room_name} (%{seats} Sitzplätze) [Teil von %{separable_room_name}]'),
                            [
                                'room_name'           => $room->getFullName(),
                                'seats'               => $room->seats,
                                'separable_room_name' => $first_separable_room->name
                            ]
                        );
                        $room_data['separable_room_id'] = $first_separable_room->id;
                        $room_data['info_text']         = sprintf(
                            '%1$s: %2$s',
                            $first_separable_room->name,
                            $first_separable_room->description
                        );
                    }
                    $this->selected_room_ids[] = $room_data;
                }
            }
        }

        $this->available_lecturers = [];
        $this->assigned_lecturers  = [];
        $this->available_groups    = [];
        $this->assigned_groups     = [];
        $lecturers                 = $this->course->getMembersWithStatus('dozent');
        foreach ($lecturers as $lecturer) {
            $this->available_lecturers[$lecturer->user_id] = $lecturer->getUserFullname();
        }
        foreach ($this->date->dozenten as $assigned_lecturer) {
            $this->assigned_lecturers[] = $assigned_lecturer->user_id;
        }
        foreach ($this->course->statusgruppen as $group) {
            $this->available_groups[$group->id] = $group->name;
        }
        foreach ($this->date->statusgruppen as $assigned_group) {
            $this->assigned_groups[] = $assigned_group->id;
        }

        $first_booking = null;
        if (count($this->date->room_bookings) > 0) {
            $first_booking = $this->date->room_bookings[0];
        }
        $this->preparation_time = $first_booking instanceof ResourceBooking
                                ? intval(floor($first_booking->preparation_time / 60))
                                : 0;
        $this->subsequent_time  = $first_booking instanceof ResourceBooking
                                ? intval(floor($first_booking->subsequent_time / 60))
                                : 0;
        $this->max_preparation_time = intval(Config::get()->RESOURCES_MAX_PREPARATION_TIME);

        $this->allow_multiple_room_bookings = ResourceManager::userHasGlobalPermission(
            User::findCurrent(),
            Config::get()->ROOM_PERMISSIONS_FOR_MULTIPLE_BOOKINGS_PER_COURSE_DATE
        );
    }


    /**
     * Clone an existing date
     *
     * @param $termin_id
     */
    public function cloneDate_action($termin_id)
    {
        $date = CourseDate::find($termin_id);

        if ($date) {
            $termin = CourseDate::build($date);
            $termin->setId($termin->getNewId());

            $termin->dozenten = $date->dozenten;
            $termin->statusgruppen = $date->statusgruppen;
            $termin->store();

            PageLayout::postSuccess(sprintf(
                _('Der Termin "%s" wurde dupliziert.'),
                htmlReady($termin->getFullName())
            ));
        }

        $this->redirect('course/timesrooms/index');
    }


    /**
     * Save date-information
     *
     * @param $termin_id
     *
     * @throws Trails\Exceptions\DoubleRenderError
     */
    public function saveDate_action($termin_id = '')
    {
        // TODO :: TERMIN -> SINGLEDATE
        CSRFProtection::verifyUnsafeRequest();
        $termin = null;
        if ($termin_id) {
            $termin = CourseDate::find($termin_id);
        } else {
            $termin           = new CourseDate();
            $termin->range_id = $this->course->id;
        }
        $start  = Request::getDateTime('date', 'd.m.Y', 'start_time', 'H:i');
        $end    = Request::getDateTime('date', 'd.m.Y', 'end_time', 'H:i');

        $max_preparation_time = Config::get()->RESOURCES_MAX_PREPARATION_TIME;

        if ($start === false || $end === false || $start > $end) {
            if (!$termin->isNew()) {
                $date = new DateTime();
                $end  = new DateTime();
                $date->setTimestamp($termin->date);
                $end->setTimestamp($termin->end_time);
            }
            PageLayout::postError(_('Die Zeitangaben sind nicht korrekt. Bitte überprüfen Sie diese!'));
        }

        if ($termin->isNew()) {
            $termin->date     = $start->getTimestamp();
            $termin->end_time = $end->getTimestamp();
        }

        if ($this->bookingTooShort($start->getTimestamp(), $end->getTimestamp())) {
            PageLayout::postError(
                sprintf(
                    ngettext(
                        'Die minimale Dauer eines Termins von einer Minute wurde unterschritten.',
                        'Die minimale Dauer eines Termins von %u Minuten wurde unterschritten.',
                        Config::get()->RESOURCES_MIN_BOOKING_TIME
                    ),
                    Config::get()->RESOURCES_MIN_BOOKING_TIME
                )
            );
            $this->redirect('course/timesrooms/editDate/' . $termin_id, ['contentbox_open' => $termin->metadate_id]);
            return;
        }

        $time_changed = !$termin->isNew() && ($start->getTimestamp() != $termin->date || $end->getTimestamp() != $termin->end_time);
        $preparation_time = Request::int('preparation_time', 0);
        $subsequent_time  = Request::int('subsequent_time', 0);
        $preparation_time_changed = false;
        $subsequent_time_changed = false;
        $first_booking = reset($termin->room_bookings);
        if ($first_booking) {
            $preparation_time_changed = $first_booking->preparation_time !== $preparation_time * 60 ;
            $subsequent_time_changed  = $first_booking->subsequent_time !== $subsequent_time * 60 ;
        }
        if ($time_changed) {
            if ($termin->metadate_id != '') {
                //time changed for regular date. create normal singledate and cancel the regular date
                $termin_values = $termin->toArray();
                $termin_info = $termin->getFullName();

                $termin->cancelDate();
                PageLayout::postInfo(sprintf(
                    _('Der Termin %s wurde aus der Liste der regelmäßigen Termine'
                        . ' gelöscht und als unregelmäßiger Termin eingetragen, da Sie die Zeiten des Termins verändert haben,'
                        . ' sodass dieser Termin nun nicht mehr regelmäßig ist.'),
                    htmlReady($termin_info)
                ));

                $termin = new CourseDate();
                unset($termin_values['metadate_id']);
                $termin->setData($termin_values);
                $termin->date     = $start->getTimestamp();
                $termin->end_time = $end->getTimestamp();
                $termin->setId($termin->getNewId());
            } else {
                //Time changed for single date.
                $termin->date     = $start->getTimestamp();
                $termin->end_time = $end->getTimestamp();
            }
        }
        $termin->date_typ = Request::get('date_type');

        // Set assigned teachers
        $assigned_lecturers = Request::optionArray('assigned_lecturers');
        $dozenten          = $this->course->getMembersWithStatus('dozent');
        if (count($assigned_lecturers) === count($dozenten) || empty($assigned_lecturers)) {
            //The amount of lecturers of the course date is the same as the amount of lecturers of the course
            //or no lecturers are assigned to the course date.
            $termin->dozenten = [];
        } else {
            //The assigned lecturers (amount or persons) have been changed in the form.
            //In those cases, the lecturers of the course date have to be set.
            $termin->dozenten = User::findMany($assigned_lecturers);
        }

        // Set assigned groups
        $assigned_groups       = Request::optionArray('assigned_groups');
        $termin->statusgruppen = Statusgruppen::findMany($assigned_groups);

        if (Config::get()->ENABLE_NUMBER_OF_PARTICIPANTS) {
            $termin->number_of_participants = strlen(Request::get('number_of_participants')) && Request::int('number_of_participants') >= 0 ? Request::int('number_of_participants') : null;
        }

        $new_date = $termin->isNew();

        $termin->store();

        if ($new_date || $time_changed) {
            NotificationCenter::postNotification('CourseDidChangeSchedule', $this->course);
        }

        // Set Rooms
        $old_room_ids = [];
        foreach ($termin->room_bookings as $booking) {
            $old_room_ids[] = $booking->resource_id;
        }

        if ((Request::option('room') == 'room') || Request::option('room') == 'nochange') {
            $room_ids = [];
            if (Request::option('room') == 'room') {
                $room_ids = Request::getArray('room_ids');
                if ($preparation_time > $max_preparation_time || $subsequent_time > $max_preparation_time) {
                    PageLayout::postError(
                        sprintf(
                            _('Die eingegebene Rüstzeit überschreitet das erlaubte Maximum von %d Minuten!'),
                            $max_preparation_time
                        )
                    );
                }
                //Process the room-IDs: If a separable room is selected, set all its room parts as room-IDs.
                //Remove the prefix in all other cases.
                $processed_room_ids = [];
                foreach ($room_ids as $room_id) {
                    $id_parts = explode('-', $room_id);

                    if ($id_parts[0] === 'separable_room') {
                        //A separable room was selected.
                        $separable_room = SeparableRoom::find($id_parts[1]);
                        if ($separable_room) {
                            foreach ($separable_room->parts as $part) {
                                $processed_room_ids[] = $part->room_id;
                            }
                        }
                    } elseif ($id_parts[0] === 'room') {
                        //An ordinary room.
                        $processed_room_ids[] = $id_parts[1];
                    }
                }
                $room_ids = $processed_room_ids;
            } elseif (Request::option('room') == 'nochange') {
                //Use the ID of the current room as room-ID:
                $room_ids = $old_room_ids;
            }
            if ($room_ids) {
                $resources = Resource::findMany($room_ids);
                $rooms = [];
                foreach ($resources as $resource) {
                    $rooms[] = $resource->getDerivedClassInstance();
                }
                if ($time_changed || $preparation_time_changed) {
                    //Remove the old bookings first.
                    ResourceBooking::deleteBySQL(
                        '`range_id` = :date_id AND `resource_id` IN ( :room_ids )',
                        ['date_id' => $termin->id, 'room_ids' => $room_ids]
                    );
                }
                if ($room_ids !== $old_room_ids || $time_changed) {
                    if (count($room_ids) > 1) {
                        //Check if the user has sufficient permissions to book multiple rooms.
                        $min_perms = Config::get()->ROOM_PERMISSIONS_FOR_MULTIPLE_BOOKINGS_PER_COURSE_DATE;
                        if (!ResourceManager::userHasGlobalPermission(User::findCurrent(), $min_perms)) {
                            PageLayout::postError(
                                _('Ihre globalen Berechtigungen in der Raumverwaltung reichen nicht aus, um mehrere Räume für einen Termin buchen zu können.')
                            );
                            $this->relocate('course/timesrooms/index', ['contentbox_open' => $termin->metadate_id]);
                            return;
                        }
                    }
                    $unbooked_room_ids = $old_room_ids;
                    foreach ($rooms as $room) {
                        if (in_array($room->id, $old_room_ids)) {
                            $unbooked_room_ids = array_diff($unbooked_room_ids, [$room->id]);
                        }
                        $failure = false;
                        if ($room instanceof Room) {
                            try {
                                $failure = !$termin->bookRoom($room, $preparation_time, $subsequent_time);
                            } catch (ResourceBookingException|ResourceBookingOverlapException $e) {
                                $course = $e->getRange();
                                $message_links = [];

                                if ($course instanceof Course) {
                                    if ($course->isEditableByUser()) {
                                        //Link to the times/rooms page:
                                        $link = new LinkElement(
                                            _('Direkt zur Veranstaltung'),
                                            URLHelper::getURL('dispatch.php/course/timesrooms/index', ['cid' => $course->id]),
                                            Icon::create('link-intern')
                                        );
                                        $message_links[] = $link->render();
                                    } elseif ($course->isAccessibleToUser()) {
                                        //Link to the details page:
                                        $link = new LinkElement(
                                            _('Direkt zur Veranstaltung'),
                                            URLHelper::getURL('course/details/index', ['cid' => $course->id]),
                                            Icon::create('link-intern')
                                        );
                                        $message_links[] = $link->render();
                                    }
                                }
                                if ($room->userHasBookingRights(User::findCurrent())) {
                                    $room_link = new LinkElement(
                                        _('Zum Belegungsplan'),
                                        $room->getActionURL('booking_plan')
                                    );
                                    $message_links[] = $room_link->render();
                                }
                                if ($e instanceof ResourceBookingException) {
                                    PageLayout::postError(
                                        sprintf(
                                            _('Der angegebene Raum konnte für den Termin %1$s nicht gebucht werden: %2$s'),
                                            '<strong>' . htmlReady($termin->getFullName()) . '</strong>',
                                            $e->getMessage()
                                        ),
                                        $message_links
                                    );
                                } else {
                                    //$e is a ResourceBookingOverlapException
                                    if ($course instanceof Course) {
                                        PageLayout::postError(
                                            studip_interpolate(
                                                _('Der Raum %{room_name} wird an dem Termin %{date} bereits durch die Veranstaltung %{course_name} belegt.'),
                                                [
                                                    'room_name' => htmlReady($room->name),
                                                    'date' => htmlReady($termin->getFullName()),
                                                    'course_name' => htmlReady($course->name)
                                                ]
                                            ),
                                            $message_links
                                        );
                                    } else {
                                        PageLayout::postError(
                                            studip_interpolate(
                                                _('Der Raum %{room_name} wird an dem Termin %{date} bereits anderweitig belegt.'),
                                                [
                                                    'room_name' => htmlReady($room->name),
                                                    'date' => htmlReady($termin->getFullName())
                                                ]
                                            ),
                                            $message_links
                                        );
                                    }
                                }
                            }
                        }
                        if ($failure) {
                            PageLayout::postError(sprintf(
                                _('Der angegebene Raum konnte für den Termin %s nicht gebucht werden!'),
                                '<strong>' . htmlReady($termin->getFullName()) . '</strong>'
                            ));
                        }
                    }
                    //Delete the bookings of the delesected rooms:
                    ResourceBooking::deleteBySQL(
                        '`range_id` = :date_id AND `resource_id` IN ( :unbooked_room_ids )',
                        ['date_id' => $termin->id, 'unbooked_room_ids' => $unbooked_room_ids]
                    );
                } elseif ($preparation_time_changed || $subsequent_time_changed) {
                    foreach ($rooms as $room) {
                        if ($room instanceof Room) {
                            $termin->bookRoom($room, $preparation_time * 60, $subsequent_time * 60);
                        }
                    }
                }
            } else if ($old_room_ids && empty($termin->room_bookings)) {
                PageLayout::postInfo(
                    sprintf(
                        _('Die Raumbuchungen für den Termin %s wurden aufgehoben, da die neuen Zeiten außerhalb der alten liegen!'),
                        '<strong>'.htmlReady($termin->getFullName()) .'</strong>'
                    ));
            } else if (Request::get('room_id_parameter')) {
                PageLayout::postInfo(
                    _('Um Raumbuchungen durchzuführen, müssen Sie einen Raum aus dem Suchergebnis auswählen!')
                );
            }
        } elseif (Request::option('room') === 'freetext') {
            $termin->raum = Request::get('room_name');
            if ($termin->room_bookings) {
                $termin->room_bookings->each(function($b){$b->delete();});
            }
            $termin->store();
            if (!$new_date) {
                PageLayout::postSuccess(sprintf(
                    _('Der Termin %s wurde geändert, Raumbuchungen zu diesem Termin wurden entfernt und stattdessen der angegebene Freitext eingetragen!'),
                    '<strong>' . htmlReady($termin->getFullName()) . '</strong>'
                ));
            }
        } elseif (Request::option('room') == 'noroom') {
            $termin->raum = '';
            if ($termin->room_bookings) {
                $termin->room_bookings->each(function($b){$b->delete();});
            }
            $termin->store();
            if (!$new_date) {
                PageLayout::postSuccess(sprintf(
                    _('Der Termin %s wurde geändert, freie Ortsangaben und Raumbuchungen an diesem Termin wurden entfernt.'),
                    '<strong>' . htmlReady($termin) . '</strong>'
                ));
            }
        }
        if ($new_date) {
            PageLayout::postSuccess(_('Der Termin wurde gespeichert.'));
        }
        $this->redirect('course/timesrooms/index', ['contentbox_open' => $termin->metadate_id]);
    }


    /**
     * Create Single Date
     */
    public function createSingleDate_action()
    {
        PageLayout::setTitle(Course::findCurrent()->getFullName() . " - " . _('Einzeltermin anlegen'));
        $this->restoreRequest(
            words('date start_time end_time room related_teachers related_statusgruppen freeRoomText dateType fromDialog course_type'),
            $_SESSION['last_single_date'] ?? null
        );

        $this->date_types = [];
        foreach ($GLOBALS['TERMIN_TYP'] as $id => $data) {
            $this->date_types[] = [
                'id'   => $id,
                'name' => $data['name']
            ];
        }
        $lecturer_objects          = $this->course->getMembersWithStatus('dozent');
        $group_objects             = Statusgruppen::findBySeminar_id($this->course->id);
        $this->available_lecturers = [];
        $this->available_groups    = [];
        foreach ($lecturer_objects as $lecturer) {
            $this->available_lecturers[$lecturer->user_id] = $lecturer->getUserFullname();
        }
        foreach ($group_objects as $group) {
            $this->available_groups[$group->id] = $group->name;
        }

        $this->allow_multiple_room_bookings = ResourceManager::userHasGlobalPermission(
            User::findCurrent(),
            Config::get()->ROOM_PERMISSIONS_FOR_MULTIPLE_BOOKINGS_PER_COURSE_DATE
        );
    }

    /**
     * Restores a previously removed date.
     *
     * @param String $termin_id Id of the previously removed date
     */
    public function undeleteSingle_action($termin_id, $from_dates = false)
    {
        $ex_termin = CourseExDate::find($termin_id);
        $termin    = $ex_termin->unCancelDate();
        if ($termin) {
            PageLayout::postSuccess(sprintf(
                _('Der Termin %s wurde wiederhergestellt!'),
                htmlReady($termin->getFullName())
            ));
        }

        if ($from_dates) {
            $this->redirect("course/dates#date_{$termin_id}");
        } else {
            $params = [];
            if ($termin->metadate_id != '') {
                $params['contentbox_open'] = $termin->metadate_id;
            }
            $this->redirect('course/timesrooms/index', $params);
        }
    }

    /**
     * Performs a stack action defined by url parameter method.
     *
     * @param String $cycle_id Id of the cycle the action should be performed
     *                         upon
     */
    public function stack_action($cycle_id = '')
    {
        $_SESSION['_checked_dates'] = Request::optionArray('single_dates');
        $_SESSION['_checked_dates'] = $this->validateDateIds($_SESSION['_checked_dates']);
        if (count($_SESSION['_checked_dates']) === 0) {
            PageLayout::postError(_('Sie haben keine Termine ausgewählt!'));
            $this->redirect('course/timesrooms/index', ['contentbox_open' => $cycle_id]);

            return;
        }

        $this->linkAttributes = ['fromDialog' => Request::int('fromDialog') ? 1 : 0];

        switch (Request::get('method')) {
            case 'edit':
                PageLayout::setTitle(_('Termine bearbeiten'));
                $this->editStack($cycle_id);
                break;
            case 'preparecancel':
                PageLayout::setTitle(_('Termine ausfallen lassen'));
                $this->prepareCancel($cycle_id);
                break;
            case 'undelete':
                PageLayout::setTitle(_('Termine stattfinden lassen'));
                $this->unDeleteStack($cycle_id);
                break;
            case 'request':
                PageLayout::setTitle(
                    _('Anfrage auf ausgewählte Termine stellen')
                );
                $this->requestStack($cycle_id);
        }
    }


    /**
     * Edits a stack/cycle.
     *
     * @param String $cycle_id Id of the cycle to be edited.
     */
    private function editStack($cycle_id)
    {
        $this->cycle_id = $cycle_id;
        $this->teachers = $this->course->getMembersWithStatus('dozent');
        $this->gruppen  = Statusgruppen::findBySeminar_id($this->course->id);
        $checked_course_dates = CourseDate::findMany($_SESSION['_checked_dates']);

        $this->checked_dates = $_SESSION['_checked_dates'];

        $this->selected_lecturer_ids = $this->getSameFieldValue($checked_course_dates, function (CourseDate $date) {
            return $date->dozenten->pluck('user_id');
        });
        $this->selected_room_ids = $this->getSameFieldValue($checked_course_dates, function (CourseDate $date) {
            $ids = [];
            foreach ($date->room_bookings as $booking) {
                $ids[] = $booking->resource->id;
            }
            return $ids;
        });
        $this->selected_room_name = $this->getSameFieldValue($checked_course_dates, function (CourseDate $date) {
            return $date->raum ?? '';
        });

        $preparation_time = $this->getSameFieldValue($checked_course_dates, function (CourseDate $date) {
            $first_booking = null;
            if (count($date->room_bookings) > 0) {
                $first_booking = $date->room_bookings[0];
            }
            return $first_booking->preparation_time ?? 0;
        });
        $subsequent_time = $this->getSameFieldValue($checked_course_dates, function (CourseDate $date) {
            $first_booking = null;
            if (count($date->room_bookings) > 0) {
                $first_booking = $date->room_bookings[0];
            }
            return $first_booking->subsequent_time ?? 0;
        });
        $this->time_ranges = [];
        foreach ($checked_course_dates as $course_date) {
            $this->time_ranges[] = [
                'start' => $course_date->date,
                'end'   => $course_date->end_time
            ];
        }
        $this->max_preparation_time = intval(Config::get()->RESOURCES_MAX_PREPARATION_TIME);
        $this->preparation_time     = intval($preparation_time) / 60;
        $this->subsequent_time      = intval($subsequent_time) / 60;
        $this->allow_multiple_room_bookings = ResourceManager::userHasGlobalPermission(
            User::findCurrent(),
            Config::get()->ROOM_PERMISSIONS_FOR_MULTIPLE_BOOKINGS_PER_COURSE_DATE
        );
        $this->selected_room_ids = $this->getSameFieldValue($checked_course_dates, function (CourseDate $date) {
            $room_ids = [];
            foreach ($date->room_bookings as $booking) {
                $room_ids[] = $booking->resource_id;
            }
            return $room_ids;
        });
        $this->render_template('course/timesrooms/editStack');
    }

    /**
     * Checks a specific field value of the specified course dates for equality.
     * A closure defines which field of the course dates to check.
     *
     * @param CourseDate[] $dates The dates from which to extract values.
     * @param Closure $callback The closure that extracts values from a CourseDate object that is passed to it.
     * @return mixed The identical value that has been retrieved from all course dates or a value that is equal to
     *     false in case the value differs. The returned result might be a string or an array or it may be empty.
     */
    protected function getSameFieldValue(array $dates, Closure $callback)
    {
        $data = array_map($callback, $dates);

        $initial = null;
        foreach ($data as $item) {
            if ($initial === null) {
                $initial = $item;
                continue;
            }

            // Compare array by checking their sizes and different items
            if (
                is_array($initial)
                && (
                    count($initial) !== count($item)
                    || count(array_diff($initial, $item)) > 0
                )
            ) {
                return [];
            }

            // Otherwise compare items themselves
            if (!is_array($initial) && $initial != $item) {
                return '';
            }
        }

        return $initial;
    }

    /**
     * Creates a new room request for the selected dates.
     */
    protected function requestStack($cycle_id)
    {
        $appointment_ids = [];

        if (!empty($_SESSION['_checked_dates'])) {
            foreach ($_SESSION['_checked_dates'] as $appointment_id) {
                if (CourseDate::exists($appointment_id)) {
                    $appointment_ids[] = $appointment_id;
                }
            }
        }

        if (!$appointment_ids) {
            PageLayout::postError(_('Es wurden keine gültigen Termin-IDs übergeben!'));
            $this->relocate('course/timesrooms/index', ['contentbox_open' => $cycle_id]);
            return;
        }

        $this->redirect(
            'course/room_requests/new_request',
            [
                'range' => 'date-multiple',
                'range_str' => 'date-multiple',
                'range_ids' => $appointment_ids
            ]
        );
    }


    /**
     * Prepares a stack/cycle to be canceled.
     *
     * @param String $cycle_id Id of the cycle to be canceled.
     */
    private function prepareCancel($cycle_id)
    {
        $this->cycle_id = $cycle_id;
        $this->render_template('course/timesrooms/cancelStack');
    }

    /**
     * Restores a previously deleted stack/cycle.
     *
     * @param String $cycle_id Id of the cycle to be restored.
     */
    private function unDeleteStack($cycle_id = '')
    {
        if (!empty($_SESSION['_checked_dates'])) {
            foreach ($_SESSION['_checked_dates'] as $id) {
                $ex_termin = CourseExDate::find($id);
                if ($ex_termin === null) {
                    continue;
                }
                $ex_termin->content = '';
                $termin             = $ex_termin->unCancelDate();
                if ($termin !== null) {
                    PageLayout::postSuccess(sprintf(
                        _('Der Termin %s wurde wiederhergestellt!'),
                        htmlReady($termin->getFullName())
                    ));
                }
            }
        }

        $this->relocate('course/timesrooms/index', ['contentbox_open' => $cycle_id]);
    }

    /**
     * Saves a stack/cycle.
     *
     * @param String $cycle_id Id of the cycle to be saved.
     */
    public function saveStack_action($cycle_id = '')
    {
        CSRFProtection::verifyUnsafeRequest();
        switch (Request::get('method')) {
            case 'edit':
                $this->saveEditedStack();
                break;
            case 'preparecancel':
                $this->saveCanceledStack();
                break;
            case 'request':
                $this->saveRequestStack();
        }

        $this->relocate('course/timesrooms/index', ['contentbox_open' => $cycle_id]);
    }

    /**
     * Saves a canceled stack/cycle.
     *
     * @param String $cycle_id Id of the canceled cycle to be saved.
     */
    private function saveCanceledStack()
    {
        $deleted_dates = [];
        $cancel_comment = trim(Request::get('cancel_comment'));
        $cancel_send_message = Request::int('cancel_send_message');

        if (!empty($_SESSION['_checked_dates'])) {
            foreach ($_SESSION['_checked_dates'] as $id) {
                $termin = CourseDate::find($id);
                if ($termin) {
                    $deleted_dates[] = $this->deleteDate($termin, $cancel_comment);
                }
            }
        }

        if ($cancel_send_message && $cancel_comment != '' && count($deleted_dates) > 0) {
            $snd_messages = raumzeit_send_cancel_message($cancel_comment, $deleted_dates);
            if ($snd_messages > 0) {
                PageLayout::postSuccess(_('Alle Teilnehmenden wurden benachrichtigt.'));
            }
        }
    }

    /**
     * Saves an edited stack/cycle.
     *
     * @param String $cycle_id Id of the edited cycle to be saved.
     */
    private function saveEditedStack()
    {
        $persons         = Request::getArray('related_persons');
        $action          = Request::get('related_persons_action');
        $groups          = Request::getArray('related_groups');
        $group_action    = Request::get('related_groups_action');
        $lecture_changed = false;
        $groups_changed  = false;
        $singledates     = [];

        if (!empty($_SESSION['_checked_dates'])) {
            foreach ($_SESSION['_checked_dates'] as $singledate_id) {
                $singledate = CourseDate::find($singledate_id);
                if (!isset($singledate)) {
                    $singledate = CourseExDate::find($singledate_id);
                }
                $singledates[] = $singledate;
            }
        }

        // Update related persons
        if (in_array($action, words('add delete'))) {
            $course_lectures = $this->course->getMembersWithStatus('dozent');
            $persons         = User::findMany($persons);
            foreach ($singledates as $singledate) {
                if ($action === 'add') {
                    if (count($course_lectures) === count($persons)) {
                        $singledate->dozenten = [];
                    } else {
                        foreach ($persons as $person) {
                            if (!count($singledate->dozenten->findBy('id', $person->id))) {
                                $singledate->dozenten[] = $person;
                            }
                        }
                        if (count($singledate->dozenten) === count($course_lectures)) {
                            $singledate->dozenten = [];
                        }
                    }

                    $lecture_changed = true;
                }

                if ($action === 'delete') {
                    foreach ($persons as $person) {
                        $singledate->dozenten->unsetBy('id', $person->id);
                    }
                    $lecture_changed = true;
                }
                $singledate->store();
            }
        }

        if ($lecture_changed) {
            PageLayout::postSuccess(_('Die zuständigen Personen für die Termine wurden geändert.'));
        }

        if (in_array($group_action, words('add delete'))) {
            $course_groups = Statusgruppen::findBySeminar_id($this->course->id);
            $groups        = Statusgruppen::findMany($groups);
            foreach ($singledates as $singledate) {
                if ($group_action === 'add') {
                    if (count($course_groups) === count($groups)) {
                        $singledate->statusgruppen = [];
                    } else {

                        foreach ($groups as $group) {
                            if (!count($singledate->statusgruppen->findBy('id', $group->id))) {
                                $singledate->statusgruppen[] = $group;
                            }
                        }
                        if (count($singledate->statusgruppen) === count($course_groups)) {
                            $singledate->statusgruppen = [];
                        }
                    }
                    $groups_changed = true;
                }
                if ($group_action === 'delete') {
                    foreach ($groups as $group) {
                        $singledate->statusgruppen->unsetByPk($group->id);
                    }
                    $groups_changed = true;
                }
                $singledate->store();
            }
        }

        if ($groups_changed) {
            PageLayout::postSuccess(_('Zugewiesene Gruppen für die Termine wurden geändert.'));
        }

        if (in_array(Request::get('room'), ['room', 'freetext', 'noroom']) || Request::get('course_type')) {
            $success_cases = 0;
            $success_messages = [];
            $error_messages = [];
            $room_ids = Request::getArray('room_ids');
            $rooms = [];
            //Collect all rooms and distinguish between real rooms and separable rooms:
            foreach ($room_ids as $room_id) {
                $id_parts = explode('-', $room_id);
                if (count($id_parts) != 2) {
                    //Invalid ID.
                    continue;
                }
                if ($id_parts[0] === 'room') {
                    //The ID belongs to a real room.
                    $room = Room::find($id_parts[1]);
                    if ($room) {
                        $rooms[$room->id] = $room;
                    }
                } elseif ($id_parts[0] === 'separable_room') {
                    //The ID belongs to a separable room: Get all the room parts.
                    $separable_room = SeparableRoom::find($id_parts[1]);
                    foreach ($separable_room->parts as $part) {
                        if ($part->room instanceof Room) {
                            $rooms[$part->room->id] = $part->room;
                        }
                    }
                }
            }

            foreach ($singledates as $singledate) {
                if ($singledate instanceof CourseExDate) {
                    continue;
                }
                if (Request::get('room') === 'room') {
                    $preparation_time     = Request::int('preparation_time', 0);
                    $subsequent_time      = Request::int('subsequent_time', 0);
                    $max_preparation_time = intval(Config::get()->RESOURCES_MAX_PREPARATION_TIME);
                    if ($preparation_time > $max_preparation_time) {
                        $error_messages[] = sprintf(
                            studip_interpolate(
                                _('%{date}: Die eingegebene Rüstzeit überschreitet das erlaubte Maximum von %d Minuten!'),
                                ['date' => htmlReady($singledate->getFullName())]
                            ),
                            $max_preparation_time
                        );
                        continue;
                    }
                    if (!empty($room_ids)) {
                        //Delete all existing bookings for the date:
                        ResourceBooking::deleteBySQL('`range_id` = :date_id',['date_id' => $singledate->id]);
                        foreach ($rooms as $room) {
                            $failure = false;
                            try {
                                $failure = !$singledate->bookRoom($room, $preparation_time, $subsequent_time);
                            } catch (ResourceBookingException $e) {
                                $error_messages[] = sprintf(
                                    _('Der angegebene Raum konnte für den Termin %1$s nicht gebucht werden: %2$s'),
                                    '<strong>' . htmlReady($singledate->getFullName()) . '</strong>',
                                    $e->getMessage()
                                );
                            } catch (ResourceBookingOverlapException $e) {
                                $course = $e->getRange();
                                if ($course instanceof Course) {
                                    $error_messages[] = studip_interpolate(
                                        _('Der Raum %{room_name} wird an dem Termin %{date} bereits durch die Veranstaltung %{course_name} belegt.'),
                                        [
                                            'room_name' => htmlReady($room->name),
                                            'date' => htmlReady($singledate->getFullName()),
                                            'course_name' => htmlReady($course->name)
                                        ]
                                    );
                                } else {
                                    $error_messages[] = studip_interpolate(
                                        _('Der Raum %{room_name} wird an dem Termin %{date} bereits anderweitig belegt.'),
                                        [
                                            'room_name' => htmlReady($room->name),
                                            'date' => htmlReady($singledate->getFullName())
                                        ]
                                    );
                                }
                            }
                            if ($failure) {
                                $error_messages[] = sprintf(
                                    _('Der angegebene Raum konnte für den Termin %s nicht gebucht werden!'),
                                    '<strong>' . htmlReady($singledate->getFullName()) . '</strong>'
                                );
                            } else {
                                $success_cases++;
                            }
                        }
                    } elseif (Request::get('room') === 'room') {
                        //No room has been selected despite having the room selector set to book at least one room.
                        PageLayout::postInfo(_('Um eine Raumbuchung durchzuführen, müssen Sie einen Raum aus dem Suchergebnis auswählen!'));
                    }
                } elseif (Request::get('room') === 'freetext') {
                    $singledate->raum = Request::get('room_name');
                    $singledate->store();
                    if (!empty($singledate->room_bookings)) {
                        $singledate->room_bookings->each(function($b){$b->delete();});
                    }
                    $success_messages[] = sprintf(
                        _('Der Termin %s wurde geändert, etwaige Raumbuchungen wurden entfernt und stattdessen der angegebene Freitext eingetragen!'),
                        '<strong>' . htmlReady($singledate->getFullName()) . '</strong>'
                    );
                } elseif (Request::get('room') == 'noroom') {
                    $singledate->raum = '';
                    $singledate->store();
                    if (!empty($singledate->room_bookings)) {
                        $singledate->room_bookings->each(function($b){$b->delete();});
                    }
                    $success_messages[] = sprintf(
                        _('Der Termin %s wurde geändert, etwaige freie Ortsangaben und Raumbuchungen wurden entfernt.'),
                        '<strong>' . htmlReady($singledate) . '</strong>'
                    );
                }

                if (Request::get('course_type') != '') {
                    $singledate->date_typ = Request::get('course_type');
                    $singledate->store();
                    $success_messages[] = sprintf(
                        _('Die Art des Termins %s wurde geändert.'),
                        '<strong>' . htmlReady($singledate) . '</strong>'
                    );
                }
            }
            if ($success_cases > 0 || count($success_messages) > 0) {
                if (!$error_messages) {
                    // Everything went well.
                    PageLayout::postSuccess(_('Die Änderungen wurden gespeichert.'), $success_messages);
                } else {
                    // Not everything went well.
                    PageLayout::postWarning(_('Es konnten nicht alle Termine geändert werden.'), $success_messages);
                }
            }
            if ($error_messages) {
                PageLayout::postError(
                    _('Die folgenden Fehler traten auf:'),
                    $error_messages
                );
            }
        }
    }


    /**
     * The data saving part of the action to create one request
     * for multiple appointments.
     */
    public function saveRequestStack()
    {
        //The properties[] array is set by $rp->definition->toHtmlInput.
        //The default name for toHtmlInput input elements is:
        //properties[$property->id].
        $set_property_values = Request::getArray('properties');
        $set_properties = [];
        foreach ($set_property_values as $id => $value) {
            if (!ResourcePropertyDefinition::exists($id)) {
                continue;
            }
            $property = new ResourceRequestProperty();
            $property->property_id = $id;
            $property->state = $value;
            $set_properties[] = $property;
        }

        $appointments = [];
        if (!empty($_SESSION['_checked_dates'])) {
            foreach ($_SESSION['_checked_dates'] as $appointment_id) {
                $appointment = CourseDate::find($appointment_id);
                if ($appointment) {
                    $appointments[] = $appointment;
                }
            }
        }

        if (!$appointments) {
            PageLayout::postError(_('Es wurden keine gültigen Termine übergeben!'));
            return;
        }

        $request = new RoomRequest();
        $request->course_id = $this->course->id;
        $request->user_id = $GLOBALS['user']->id;
        $request->comment = Request::get('comment');
        $request->closed = '0';
        if (!$request->store()) {
            PageLayout::postError(_('Fehler beim Speichern der Anfrage!'));
            return;
        }

        //Now we store the requested properties:
        $successfully_stored = 0;
        foreach ($set_properties as $property) {
            $property->request_id = $request->id;
            if ($property->store()) {
                $successfully_stored++;
            }
        }

        if ($set_properties && $successfully_stored < count($set_properties)) {
            PageLayout::postError(_('Es wurden nicht alle zur Anfrage gehörenden Eigenschaften gespeichert!'));
        }

        //Finally we can create ResourceRequestAppointment
        //objects for each appointment:

        $successfully_stored = 0;
        foreach ($appointments as $appointment) {
            $rra = new ResourceRequestAppointment();
            $rra->request_id = $request->id;
            $rra->appointment_id = $appointment->id;
            if ($rra->store()) {
                $successfully_stored++;
            }
        }

        if (($successfully_stored < count($appointments)) && count($appointments)) {
            PageLayout::postError(_('Es wurden nicht alle zur Anfrage gehörenden Terminzuordnungen gespeichert!'));
            return;
        }

        PageLayout::postSuccess(_('Die Raumanfrage wurde gespeichert!'));
    }


    /**
     * Creates a cycle.
     *
     * @param String $cycle_id Id of the cycle to be created (optional)
     */
    public function createCycle_action($cycle_id = null)
    {
        PageLayout::setTitle(Course::findCurrent()->getFullname() . " - " . _('Regelmäßige Termine anlegen'));
        $this->restoreRequest(
            words('day start_time end_time description cycle startWeek teacher_sws fromDialog course_type')
        );

        $this->linkAttributes = ['fromDialog' => Request::bool('fromDialog')];

        $this->cycle = new SeminarCycleDate($cycle_id);

        if ($this->cycle->isNew()) {
            $this->has_bookings = false;
        } else {
            $ids = $this->cycle->dates->pluck('termin_id');

            $count = ResourceBooking::countBySQL(
                'range_id IN ( :range_ids )',
                [
                    'range_ids' => ($ids ?: '')
                ]
            );
            $this->has_bookings = $count > 0;
        }

        if ($this->course->isOpenEnded()) { // course with endless lifespan
            $end_semester = array_values(array_filter(Semester::getAll(), function ($s) {return $s->past === false;}));
        } else { // course over one or more semester
            $end_semester = $this->course->semesters->getArrayCopy();
        }
        $this->start_weeks = [];
        $this->end_semester_weeks = [];
        if (!empty($end_semester)) {
            $this->start_weeks = $end_semester[0]->getStartWeeks($end_semester[count($end_semester) - 1]);

            foreach ($end_semester as $sem) {

                $weeks = $sem->getStartWeeks();

                foreach ($this->start_weeks as $key => $week) {
                    if (mb_strpos($week, mb_substr($weeks[0], -15)) !== false) {
                        $this->end_semester_weeks['start'][] = [
                            'value' => $key,
                            'label' => sprintf(_('Anfang %s'), $sem->name)
                        ];
                    }
                    if (mb_strpos($week, mb_substr($weeks[count($weeks) - 1], -15)) !== false) {
                        $this->end_semester_weeks['ende'][] = [
                            'value' => $key,
                            'label' => sprintf(_('Ende %s'), $sem->name)
                        ];
                    }
                    foreach ($weeks as $val) {
                        if (mb_strpos($week, mb_substr($val, -15)) !== false) {
                            $this->clean_weeks[(string) $sem->name][$key] = $val;
                        }
                    }
                }
            }

            if (count($end_semester) > 1) {
                $this->end_semester_weeks['ende'][] = ['value' => -1, 'label' => _('Alle Semester')];
            }
        }
        $this->types = $this->cycle->dates->pluck('date_typ');;
    }

    /**
     * Saves a cycle
     */
    public function saveCycle_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $start = strtotime(Request::get('start_time'));
        $end   = strtotime(Request::get('end_time'));

        if ($start === false || $end === false || $start > $end
        || !$this->validate_datetime(Request::get('start_time'))
        || !$this->validate_datetime(Request::get('end_time'))) {
            $this->storeRequest();
            PageLayout::postError(_('Die Zeitangaben sind nicht korrekt. Bitte überprüfen Sie diese!'));
            $this->redirect('course/timesrooms/createCycle');

            return;
        } elseif (Request::int('startWeek') > Request::int('endWeek') && Request::int('endWeek') != -1) {
            $this->storeRequest();
            PageLayout::postError(_('Die Endwoche liegt vor der Startwoche. Bitte überprüfen Sie diese Angabe!'));
            $this->redirect('course/timesrooms/createCycle');

            return;
        }
        if ($this->bookingTooShort($start, $end)) {
            PageLayout::postError(
                sprintf(
                    ngettext(
                        'Die minimale Dauer eines Termins von einer Minute wurde unterschritten.',
                        'Die minimale Dauer eines Termins von %u Minuten wurde unterschritten.',
                        Config::get()->RESOURCES_MIN_BOOKING_TIME
                    ),
                    Config::get()->RESOURCES_MIN_BOOKING_TIME
                )
            );
            $this->redirect('course/timesrooms/createCycle');
            return;
        }

        $cycle              = new SeminarCycleDate();
        $cycle->seminar_id  = $this->course->id;
        $cycle->weekday     = Request::int('day');
        $cycle->description = Request::get('description');
        $cycle->sws         = round(Request::float('teacher_sws'), 1);
        $cycle->cycle       = Request::int('cycle');
        $cycle->week_offset = Request::int('startWeek');
        $cycle->end_offset  = Request::int('endWeek');
        $cycle->start_time  = date('H:i:00', $start);
        $cycle->end_time    = date('H:i:00', $end);

        if ($cycle->end_offset == -1) {
            $cycle->end_offset = NULL;
        }

        if ($cycle->store()) {
            if(Request::int('course_type')) {
                $cycle->setSingleDateType(Request::int('course_type'));
            }

            $cycle_info = $cycle->toString();
            NotificationCenter::postNotification('CourseDidChangeSchedule', $this->course);

            PageLayout::postSuccess(sprintf(
                _('Die regelmäßige Veranstaltungszeit %s wurde hinzugefügt!'),
                htmlReady($cycle_info))
            );
            $this->relocate('course/timesrooms/index');
        } else {
            $this->storeRequest();
            PageLayout::postError(
                _('Die regelmäßige Veranstaltungszeit konnte nicht hinzugefügt werden! Bitte überprüfen Sie Ihre Eingabe.')
            );
            $this->redirect('course/timesrooms/createCycle');
        }
    }

    /**
     * Edits a cycle
     *
     * @param String $cycle_id Id of the cycle to be edited
     */
    public function editCycle_action($cycle_id)
    {
        $cycle = SeminarCycleDate::find($cycle_id);
        $start = strtotime(Request::get('start_time'));
        $end = strtotime(Request::get('end_time'));

        // Prepare Request for saving Request
        if ($start === false || $end === false || $start > $end) {
            PageLayout::postError(_('Die Zeitangaben sind nicht korrekt. Bitte überprüfen Sie diese!'));
        } else {
            $cycle->start_time = date('H:i:00', $start);
            $cycle->end_time = date('H:i:00', $end);
        }

        //Check the duration:
        if ($this->bookingTooShort($start, $end)) {
            PageLayout::postError(
                sprintf(
                    ngettext(
                        'Die minimale Dauer eines Termins von einer Minute wurde unterschritten.',
                        'Die minimale Dauer eines Termins von %u Minuten wurde unterschritten.',
                        Config::get()->RESOURCES_MIN_BOOKING_TIME
                    ),
                    Config::get()->RESOURCES_MIN_BOOKING_TIME
                )
            );
            $this->redirect('course/timesrooms/createCycle/' . $cycle_id);
            return;
        }

        $cycle->weekday = Request::int('day');
        $cycle->description = Request::get('description');
        $cycle->sws = Request::get('teacher_sws');
        $cycle->cycle = Request::get('cycle');
        $cycle->week_offset = Request::int('startWeek');
        $cycle->end_offset = Request::int('endWeek');

        if ($cycle->end_offset == -1) {
            $cycle->end_offset = NULL;
        }

        $changed_dates = 0;

        if (Request::get('course_type') && Request::get('course_type') !== 'default') {
            $changed_dates = $cycle->setSingleDateType(Request::int('course_type'));
        } elseif ($cycle->isDirty()) {
            $changed_dates = count($cycle->dates);
        }

        if ($changed_dates > 0 || $cycle->isDirty()) {

            $cycle->chdate = time();
            $cycle->store();

            if ($changed_dates > 0 && Request::get('course_type') !== 'default') {
                PageLayout::postSuccess(sprintf(ngettext(
                _('Die Art des Termins wurde bei 1 Termin geändert'),
                _('Die Art des Termins wurde bei %u Terminen geändert'),
                $changed_dates), $changed_dates));
            } else {
                PageLayout::postSuccess(_('Änderungen gespeichert!'));
            }
        } else {
            PageLayout::postInfo(_('Es wurden keine Änderungen vorgenommen.'));
        }
        $this->relocate('course/timesrooms/index');
    }


    /**
     * Deletes a cycle
     *
     * @param String $cycle_id Id of the cycle to be deleted
     */
    public function deleteCycle_action($cycle_id)
    {
        CSRFProtection::verifyUnsafeRequest();

        $cycle = SeminarCycleDate::find($cycle_id);
        if ($cycle === null) {
            $message = sprintf(_('Es gibt keinen regelmäßigen Eintrag "%s".'), $cycle_id);
            PageLayout::postError($message);
        } else {
            $cycle_string = $cycle->toString();
            if ($cycle->delete()) {
                PageLayout::postSuccess(sprintf(
                    _('Der regelmäßige Eintrag "%s" wurde gelöscht.'),
                    '<strong>' . htmlReady($cycle_string) . '</strong>'
                ));
            }
        }

        $this->redirect('course/timesrooms/index');
    }

    /**
     * Add information to canceled / holiday date
     *
     * @param String $termin_id Id of the date
     */
    public function cancel_action($termin_id)
    {
        PageLayout::setTitle(_('Kommentar hinzufügen'));
        $this->termin = CourseDate::find($termin_id) ?: CourseExDate::find($termin_id);
    }

    /**
     * Saves a comment for a given date.
     *
     * @param String $termin_id Id of the date
     */
    public function saveComment_action($termin_id)
    {
        $termin = CourseExDate::find($termin_id);

        if (is_null($termin)) {
            $termin = CourseDate::find($termin_id);
        }
        if (Request::get('cancel_comment') != $termin->content) {
            $termin->content = Request::get('cancel_comment');
            if ($termin->store()) {
                PageLayout::postSuccess(sprintf(
                    _('Der Kommentar des gelöschten Termins %s wurde geändert.'),
                    htmlReady($termin->getFullname())
                ));
            } else {
                PageLayout::postInfo(sprintf(
                    _('Der gelöschte Termin %s wurde nicht verändert.'),
                    htmlReady($termin->getFullname())
                ));
            }
        } else {
            PageLayout::postInfo(sprintf(
                _('Der gelöschte Termin %s wurde nicht verändert.'),
                htmlReady($termin->getFullname())
            ));
        }
        if (Request::int('cancel_send_message')) {
            $snd_messages = raumzeit_send_cancel_message(Request::get('cancel_comment'), $termin);
            if ($snd_messages > 0) {
                PageLayout::postInfo(_('Alle Teilnehmenden wurden benachrichtigt.'));
            }
        }
        $this->redirect('course/timesrooms/index', ['contentbox_open' => $termin->metadate_id]);
    }

    /**
     * Creates the sidebar
     */
    private function setSidebar()
    {
        if (!$this->locked) {
            $actions = new ActionsWidget();
            $actions->addLink(
                sprintf(
                    _('Semester ändern (%s)'),
                    $this->course->getFullName('sem-duration-name')
                ),
                $this->url_for('course/timesrooms/editSemester'),
                Icon::create('date')
            )->asDialog('size=400');
            Sidebar::Get()->addWidget($actions);
        }

        $widget = new SelectWidget(_('Semesterfilter'), $this->url_for('course/timesrooms/index'), 'semester_filter');
        foreach ($this->selectable_semesters as $item) {
            $element = new SelectElement(
                $item['semester_id'],
                $item['name'],
                $item['semester_id'] == $this->semester_filter
            );
            $widget->addElement($element);
        }
        Sidebar::Get()->addWidget($widget);


        if ($GLOBALS['perm']->have_studip_perm('admin', $this->course->id)) {
            $widget = new CourseManagementSelectWidget();
            Sidebar::get()->addWidget($widget);
        }
    }

    /**
     * Calculates new end_offset value for given SeminarCycleDate Object
     *
     * @param object of SeminarCycleDate
     * @param array
     * @param array
     *
     * @return int
     */
    public function getNewEndOffset($cycle, $old_start_weeks, $new_start_weeks)
    {
        // if end_offset is null (endless lifespan) it should stay null
        if (is_null($cycle->end_offset)) {
            return null;
        }
        $old_offset_string = $old_start_weeks[$cycle->end_offset];
        $new_offset_value  = 0;

        foreach ($new_start_weeks as $value => $label) {
            if (mb_strpos($label, mb_substr($old_offset_string, -15)) !== false) {
                $new_offset_value = $value;
            }
        }
        if ($new_offset_value == 0) {
            return count($new_start_weeks) - 1;
        }

        return $new_offset_value;
    }

    /**
     * Deletes a date.
     *
     * @param CourseDate $termin CourseDate of the date
     * @param String $cancel_comment cancel mesessage (if non empty)
     *
     * @return CourseDate|CourseExDate deleted date
     */
    private function deleteDate($termin, $cancel_comment)
    {
        $seminar_id = $termin->range_id;
        $termin_room = $termin->getRoomNames();
        $termin_date = $termin->getFullName();
        $has_topics  = $termin->topics->count();

        if ($cancel_comment) {
            $termin->content = $cancel_comment;
        }

        //cancel cycledate entry
        if ($termin->metadate_id || $cancel_comment) {
            $termin = $termin->cancelDate();
            StudipLog::log(
                'SEM_DELETE_SINGLEDATE',
                $termin->id,
                $seminar_id,
                'Cycle_id: ' . $termin->metadate_id,
                $termin_date
            );
        } elseif ($termin->delete()) {
            StudipLog::log(
                "SEM_DELETE_SINGLEDATE",
                $termin->id,
                $seminar_id,
                'appointment cancelled',
                $termin_date
            );
        }

        if ($has_topics) {
            PageLayout::postSuccess(
                sprintf(
                    _('Dem Termin %s war ein Thema zugeordnet. Sie können das Thema im Ablaufplan einem anderen Termin (z.B. einem Ausweichtermin) zuordnen.'),
                    htmlReady($termin_date)
                ),
                [
                    sprintf(
                        '<a href="%s">%s</a>',
                        URLHelper::getLink('dispatch.php/course/topics'),
                        _('Zum Ablaufplan')
                    )
                ]
            );
        }
        if ($termin_room) {
            PageLayout::postSuccess(
                studip_interpolate(
                    _('Der Termin %{date} wurde gelöscht! Die Raumbuchungen für die folgenden Räume wurden gelöscht: %{room_names}'),
                    [
                        'date'       => htmlReady($termin_date),
                        'room_names' => htmlReady($termin_room)
                    ]
                )
            );
        } else {
            PageLayout::postSuccess(sprintf(_('Der Termin %s wurde gelöscht!'), htmlReady($termin_date)));
        }

        return $termin;
    }


    /**
     * Redirects to another location.
     *
     * @param String $to New location
     */
    public function redirect($to)
    {
        $arguments = func_get_args();
        $url       = call_user_func_array('parent::url_for', $arguments);

        if (Request::isXhr()) {
            $index_url = $this->action_url('index');

            if (mb_strpos($url, $index_url) !== false) {
                $this->flash['update-times'] = $this->course->id;
            }
        }

        parent::redirect($url);
    }

    /**
     * Stores a request into trails' flash object
     */
    private function storeRequest()
    {
        $this->flash['request'] = Request::getInstance();
    }

    /**
     * Restores a previously stored request from trails' flash object
     */
    private function restoreRequest(array $fields, $request = null)
    {
        $request = $this->flash['request'] ?? $request;

        if ($request) {
            foreach ($fields as $field) {
                Request::set($field, $request[$field]);
            }
        }
    }

    /**
     * Relocates to another location if not from dialog
     *
     * @param String $to New location
     */
    public function relocate($to)
    {
        if (Request::int('fromDialog')) {
            $this->redirect(...func_get_args());
        } else {
            parent::relocate(...func_get_args());
        }
    }

    private function validateDateIds(array $date_ids): array
    {
        if (count($date_ids) === 0) {
            return [];
        }

        $valid = [];

        CourseDate::findEachBySQL(
            function (CourseDate $date) use (&$valid) {
                if ($date->range_id === $this->course->id) {
                    $valid[] = $date->id;
                }
            },
            'range_id = ? AND termin_id IN (?)',
            [$this->course->id, $date_ids]
        );

        CourseExDate::findEachBySQL(
            function (CourseExDate $date) use (&$valid) {
                if ($date->range_id === $this->course->id) {
                    $valid[] = $date->id;
                }
            },
            'range_id = ? AND termin_id IN (?)',
            [$this->course->id, $date_ids]
        );

        // Using array_intersect() preserves order of date ids
        return array_intersect($date_ids, $valid);
    }
}
