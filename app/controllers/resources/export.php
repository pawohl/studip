<?php

/**
 * requests.php - contains Resources_ExportController
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Moritz Strohm <strohm@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @copyright   2017
 * @category    Stud.IP
 * @since       4.1
 */


/**
 * Resources_ExportController contains functionality for data export
 * for resources and especially rooms, buildings and locations.
 */
class Resources_ExportController extends AuthenticatedController
{
    protected function returnCsvData($data = [], $file_name = '')
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException(
                'Resources_ExportController::returnCsvData requires an array as parameter!'
            );
        }
        $this->render_csv(
            $data,
            $file_name
        );
    }

    protected function returnHtmlData($data = '')
    {
        $this->set_content_type('text/html');
        $this->render_text($data);
    }

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $this->export_type  = Request::get('export_type', 'default');
        $this->export_param = Request::get('export_param', '');

        if (Navigation::hasItem('/resources/export')) {
            Navigation::activateItem('/resources/export');
        }
    }

    public function select_booking_sources_action($range_with_id = null)
    {
        if (Navigation::hasItem('/resources/export/select_booking_sources')) {
            Navigation::activateItem('/resources/export/select_booking_sources');
        }
        if (
            !ResourceManager::userHasGlobalPermission(User::findCurrent())
            && !ResourceManager::userHasResourcePermissions(User::findCurrent(), 'user')
        ) {
            throw new AccessDeniedException();
        }

        $this->available_rooms = [];
        $this->available_clipboards = [];

        PageLayout::setTitle(_('Quellen für den Export von Buchungen auswählen'));

        $this->weekdays = ['1', '2', '3', '4', '5'];
        $this->select_rooms = Request::get('select_rooms');

        if ($this->select_rooms) {
            $this->available_rooms = RoomManager::getUserRooms(
                User::findCurrent()
            );
            if (!$this->available_rooms) {
                PageLayout::postInfo(
                    _('Es sind keine Quellen für den Export von Buchungen vorhanden!')
                );
            }
        } else {
            $this->available_clipboards = Clipboard::getClipboardsForUser(
                $GLOBALS['user']->id,
                ['Room']
            );

            if(!$this->available_clipboards) {
                PageLayout::postInfo(
                    _('Sie müssen zunächst Raumgruppen erstellen'),
                    [
                        sprintf(
                            _('Klicken Sie %shier%s, um eine Raumgruppe anzulegen.'),
                            '<a href="' . URLHelper::getLink('dispatch.php/room_management/overview/rooms') . '">',
                            '</a>')
                    ]
                );
            }
        }


        $this->begin = new DateTime();
        $this->begin->setDate(
            intval(date('Y')),
            intval(date('m')),
            1
        );
        $this->begin->setTime(0, 0, 0);
        $this->end = clone $this->begin;
        $this->end = $this->end->add(
            new DateInterval('P1M')
        )->sub(
            new DateInterval('P1D')
        );
        $this->end->setTime(23, 59, 59);

        $this->range_type = null;
        $this->range_id   = null;

        if (!$range_with_id) {
            //Check if range-ID and range_type are provided in an URL parameter:
            $this->range_type = Request::get('range_type');
            $this->range_id   = Request::get('range_id');
        }

        // All available booking types.
        $this->booking_types = [
            ResourceBooking::TYPE_NORMAL          => _('Buchung'),
            ResourceBooking::TYPE_RESERVATION     => _('Reservierung'),
            ResourceBooking::TYPE_LOCK            => _('Sperrbuchung'),
            ResourceBooking::TYPE_PLANNED => _('Geplante Buchung')
        ];
        $this->selected_booking_types = Request::intArray('bookingtypes') ?:
            Config::get()->RESOURCES_EXPORT_BOOKINGTYPES_DEFAULT;

        //Build sidebar

        $sidebar = Sidebar::get();

        $views = new ViewsWidget();
        $views->addLink(
            _('Raumgruppen auswählen'),
            $this->select_booking_sourcesURL()
        )->setActive(!$this->select_rooms);
        $views->addLink(
            _('Räume auswählen'),
            $this->select_booking_sourcesURL(
                [
                    'select_rooms' => '1'
                ]
            )
        )->setActive($this->select_rooms);
        $sidebar->addWidget($views);
    }


    public function resource_bookings_action($resource_id = null)
    {
        PageLayout::setTitle(
            _('Buchungen exportieren')
        );

        if (!$resource_id) {
            PageLayout::postError(
                _('Es wurde keine Ressource ausgewählt!')
            );
            return;
        }

        $this->resource = Resource::find($resource_id);
        if (!$this->resource) {
            PageLayout::postError(
                _('Die gewählte Ressource wurde nicht gefunden!')
            );
            return;
        }
        $this->resource = $this->resource->getDerivedClassInstance();

        PageLayout::setTitle(
            sprintf(
                '%s: Buchungen exportieren',
                $this->resource->getFullName()
            )
        );

        $this->current_user = User::findCurrent();

        if (!$this->resource->userHasPermission($this->current_user, 'user')) {
            throw new AccessDeniedException();
        }

        $this->begin = new DateTime();
        $this->end   = clone $this->begin;

        $begin_timestamp = Request::get('begin');
        $end_timestamp   = Request::get('end');
        $week_timestamp  = Request::get('timestamp');
        if ($week_timestamp) {
            //Calculate start and end of week:
            $this->begin->setTimestamp($week_timestamp);
            if ($this->begin->format('N') > 1) {
                $this->begin->sub(
                    new DateInterval('P' . ($this->begin->format('N') - 1) . 'D')
                );
            }
            $this->begin->setTime(0, 0);
            $this->end = clone $this->begin;
            $this->end = $this->end->add(
                new DateInterval('P7D')
            )->sub(
                new DateInterval('PT1S')
            );
        } elseif ($begin_timestamp && $end_timestamp) {
            $this->begin->setTimestamp($begin_timestamp);
            $this->end->setTimestamp($end_timestamp);
        } else {
            $this->begin->setDate(
                intval(date('Y')),
                intval(date('m')),
                1
            );
            $this->begin->setTime(0, 0, 0);
            $this->end = clone $this->begin;
            $this->end = $this->end->add(
                new DateInterval('P1M')
            )->sub(
                new DateInterval('P1D')
            );
            $this->end->setTime(23, 59, 59);
        }

        $this->weekdays = ['1', '2', '3', '4', '5'];

        // All available booking types.
        $this->booking_types = [
            ResourceBooking::TYPE_NORMAL          => _('Buchung'),
            ResourceBooking::TYPE_RESERVATION     => _('Reservierung'),
            ResourceBooking::TYPE_LOCK            => _('Sperrbuchung'),
            ResourceBooking::TYPE_PLANNED => _('Geplante Buchung')
        ];
        $this->selected_booking_types = Config::get()->RESOURCES_EXPORT_BOOKINGTYPES_DEFAULT;
    }


    public function bookings_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        //Get the IDs of all selected clipboards and rooms:
        $this->selected_clipboard_ids = Request::getArray('selected_clipboards');
        $this->selected_room_ids      = Request::getArray('selected_rooms');
        $this->selected_resource_ids  = Request::getArray('selected_resources');

        //Get begin and end date:
        $this->begin = Request::getDateTime(
            'begin_date',
            'd.m.Y',
            'begin_time',
            'H:i'
        );
        $this->end   = Request::getDateTime(
            'end_date',
            'd.m.Y',
            'end_time',
            'H:i'
        );

        //Get the selected weekdays:
        $this->weekdays = Request::getArray('weekdays');

        if ($this->begin >= $this->end) {
            PageLayout::postError(
                _('Der Startzeitpunkt darf nicht hinter dem Endzeitpunkt liegen!')
            );
            $this->redirect(Request::get('from'));
            return;
        }
        if (!$this->weekdays) {
            PageLayout::postError(
                _('Bitte mindestens einen Wochentag auswählen.')
            );
            $this->redirect(Request::get('from'));
            return;
        }

        //Resolve the IDs to clipboards (or rooms) and build a list of rooms.

        $this->selected_clipboards = Clipboard::findMany(
            $this->selected_clipboard_ids
        );
        $room_sql                  = "INNER JOIN resource_categories rc
            ON resources.category_id = rc.id
            WHERE
            rc.class_name IN ( :room_class_names )
            AND resources.id IN ( :room_ids )
            ORDER BY resources.name ASC";
        $this->selected_rooms      = Room::findBySql(
            $room_sql,
            [
                'room_ids' => $this->selected_room_ids,
                'room_class_names' => RoomManager::getAllRoomClassNames()
            ]
        );
        $this->selected_resources  = Resource::findMany(
            $this->selected_resource_ids
        );

        $resources = [];
        foreach ($this->selected_clipboards as $clipboard) {
            $clipboard_room_ids = $clipboard->getAllRangeIds('Room');
            $clipboard_rooms    = Room::findBySql(
                $room_sql,
                [
                    'room_ids' => $clipboard_room_ids,
                    'room_class_names' => RoomManager::getAllRoomClassNames()
                ]
            );
            $resources          = array_merge($resources, $clipboard_rooms);
        }

        $resources = array_merge($resources, $this->selected_rooms, $this->selected_resources);

        //Filter out the rooms where the user does not have sufficient permissions to
        //export bookings:
        $resources = array_filter(
            $resources,
            function (Resource $resource) {
                $resource = $resource->getDerivedClassInstance();
                if (!$resource) {
                    return false;
                }
                return $resource->userHasPermission(User::findCurrent());
            }
        );

        //Collect all bookings in the selected time range and export them.

        $booking_data = [
            [
                _('Datum'),
                _('Beginn'),
                _('Ende'),
                _('Wochentag'),
                _('Rüstzeit'),
                _('Raumname'),
                _('Buchungstyp'),
                _('LV-Nummer'),
                _('Beschreibung'),
                _('geplante Teilnehmendenzahl'),
                _('Buchende Person'),
                _('Belegende Person(en)'),
                _('Interner Kommentar')
            ]
        ];

        if (Config::get()->ENABLE_NUMBER_OF_PARTICIPANTS) {
            $booking_data[0][] = _('Anzahl der Teilnehmenden');
        }

        foreach ($resources as $resource) {
            //Retrieve the bookings in the specified time range:
            $intervals = ResourceBookingInterval::findBySql(
                "`takes_place` = 1 AND `resource_id` = :resource_id AND `begin` < :end AND `end` > :begin
                 ORDER BY `begin`, `end`",
                [
                    'resource_id' => $resource->id,
                    'begin' => $this->begin->getTimestamp(),
                    'end'   => $this->end->getTimestamp()
                ]
            );

            $types = Request::intArray('bookingtypes') ?:
                Config::get()->RESOURCES_EXPORT_BOOKINGTYPES_DEFAULT;

            //Prepare data for export:

            foreach ($intervals as $interval) {
                //Check the weekday of the interval first to avoid any other computation
                //if the weekday is not one of the selected weekdays.
                if (date('Ymd', $interval->begin) !== date('Ymd', $interval->end)) {
                    //The interval spans over multiple days. So the computation for the weekdays must
                    //take that into account.
                    $current_day = new DateTime();
                    $current_day->setTimestamp($interval->begin);
                    $current_day->setTime(0,0,0);
                    $end = new DateTime();
                    $end->setTimestamp($interval->end);
                    $interval_is_on_weekday = false;
                    while ($current_day <= $end && !$interval_is_on_weekday) {
                        $interval_is_on_weekday = in_array($current_day->format('N'), $this->weekdays);
                        $current_day = $current_day->add(new DateInterval('P1D'));
                    }
                    if (!$interval_is_on_weekday) {
                        //The interval does not take place on one of the weekdays that have been selected.
                        continue;
                    }
                } else {
                    //The interval lies on one day.
                    $interval_weekday = date('N', $interval->begin);
                    if (!in_array($interval_weekday, $this->weekdays)) {
                        //The interval is not on one of the selected weekdays.
                        continue;
                    }
                }
                $booking = $interval->booking;
                if (!$booking instanceof ResourceBooking || !in_array($booking->booking_type, $types)) {
                    continue;
                }
                $number = '';
                $description = $booking->description;
                $turnout = 0;
                $number_of_participants = '';
                if (!$booking->isSimpleBooking()) {
                    $course = $booking->assigned_course_date->course;
                    if ($course instanceof Course) {
                        $number = $course->veranstaltungsnummer;
                        $description = $course->name;
                        $turnout = $course->admission_turnout;
                        if (Config::get()->ENABLE_NUMBER_OF_PARTICIPANTS) {
                            $number_of_participants = $booking->assigned_course_date->number_of_participants;
                        }
                    }
                }
                //In case the booking spans over several days, several rows must be created:
                $interval_start = new DateTime();
                $interval_start->setTimestamp($interval->begin);
                $interval_end = new DateTime();
                $interval_end->setTimestamp($interval->end);
                $current_day = clone $interval_start;
                if ($current_day < $this->begin) {
                    //The interval starts before the exported time range.
                    //In this case, the current day starts on the same timestamp as the exported time range.
                    $current_day = $this->begin;
                }
                $current_day->setTime(0,0,0);
                while ($current_day < $interval_end && $current_day < $this->end) {
                    $interval_is_on_weekday = in_array($current_day->format('N'), $this->weekdays);
                    if (!$interval_is_on_weekday) {
                        //Go directly to the next day:
                        $current_day = $current_day->add(new DateInterval('P1D'));
                        continue;
                    }
                    $start_time_str = date('H:i', $interval->begin + $booking->preparation_time);
                    $end_time_str   = date('H:i', $interval->end);
                    if ($current_day->format('Ymd') !== $interval_start->format('Ymd')) {
                        //This is not the first day.
                        $start_time_str = '00:00';
                    }
                    if ($current_day->format('Ymd') !== $interval_end->format('Ymd')) {
                        //This is not the last day.
                        $end_time_str = '23:59';
                    }

                    $row = [
                        $current_day->format('d.m.Y'),
                        $start_time_str,
                        $end_time_str,
                        getWeekday((int)$current_day->format('w')),
                        sprintf(
                            _('%u min.'),
                            intval($booking->preparation_time / 60)
                        ),
                        $booking->resource->name,
                        (
                        $booking->booking_type == ResourceBooking::TYPE_NORMAL
                            ? _('Buchung')
                            : (
                        $booking->booking_type == ResourceBooking::TYPE_RESERVATION
                            ? _('Reservierung')
                            : (
                        $booking->booking_type == ResourceBooking::TYPE_LOCK
                            ? _('Sperrbuchung')
                            : _('sonstiges')
                        )
                        )
                        ),
                        $number,
                        $description,
                        $turnout,
                        $booking->booking_user ? $booking->booking_user->getFullName() : '',
                        implode(', ', $booking->getAssignedUsers()),
                        $booking->internal_comment
                    ];

                    if (Config::get()->ENABLE_NUMBER_OF_PARTICIPANTS) {
                        $row[] = $number_of_participants;
                    }
                    //Add the row:
                    $booking_data[] = $row;

                    //Go to the next day:
                    $current_day = $current_day->add(new DateInterval('P1D'));
                }
            }
        }

        $this->returnCsvData(
            $booking_data,
            sprintf(
                '%1$s_%2$s - %3$s-%4$s.csv',
                date('Ymd', time()),
                _('Buchungsliste'),
                $this->begin->format('Ymd_Hi'),
                $this->end->format('Ymd_Hi')
            )
        );
    }


    public function booking_plan_action($resource_id = null)
    {
        $this->resource = Resource::find($resource_id);
        if (!$this->resource) {
            PageLayout::postError(
                _('Die angegebene Ressource wurde nicht gefunden!')
            );
            return;
        }
        $this->resource = $this->resource->getDerivedClassInstance();

        $user_has_user_permissions = $this->resource->userHasPermission(
            User::findCurrent(),
            'user'
        );
        if (!$user_has_user_permissions) {
            throw new AccessDeniedException();
        }

        $week_timestamp = Request::get('timestamp', time());

        $this->date = new DateTime();
        $this->date->setTimestamp($week_timestamp);

        $week_begin = clone $this->date;
        $week_begin->sub(
            new DateInterval('P' . abs($this->date->format('N') - 1) . 'D')
        );
        $week_begin->setTime(0, 0, 0);

        $week_end = clone $week_begin;
        $week_end->add(new DateInterval('P7D'))->sub(new DateInterval('PT1S'));

        if ($this->export_type == 'csv') {
            $csv_data = [];

            $csv_data[] = [
                _('Beginn'),
                _('Ende'),
                _('Rüstzeit (Minuten)'),
                (
                $this->resource instanceof Room
                    ? _('Raum / Gebäude')
                    : _('Ressource')
                ),
                _('Buchungstyp'),
                _('Beschreibung'),
                _('Buchende Person'),
                _('Belegende Person(en)'),
                _('Interner Kommentar')
            ];

            //Get booking intervals:
            if ($this->resource instanceof Room) {
                $intervals = RoomManager::getBookingIntervalsForRoom(
                    $this->resource,
                    $week_begin,
                    $week_end,
                    [0, 2],
                    [0, 2]
                );
            } else {
                $intervals = $this->resource->getResourceBookings(
                    $week_begin,
                    $week_end,
                    [0, 2]
                );
            }

            foreach ($intervals as $interval) {
                $resource_name = '';
                if ($interval->resource) {
                    $interval_resource = $interval->resource->getDerivedClassInstance();
                    $resource_name     = $interval_resource->getFullName();
                }

                $booking_type_string = '';
                if ($interval->booking->booking_type == ResourceBooking::TYPE_NORMAL) {
                    $booking_type_string = _('Buchung');
                } elseif ($interval->booking->booking_type == ResourceBooking::TYPE_RESERVATION) {
                    $booking_type_string = _('Reservierung');
                } elseif ($interval->booking->booking_type == ResourceBooking::TYPE_LOCK) {
                    $booking_type_string = _('Sperrbuchung');
                }

                $csv_data[] = [
                    date('d.m.Y H:i', $interval->begin),
                    date('d.m.Y H:i', $interval->end),
                    round($interval->booking->preparation_time / 60),
                    $resource_name,
                    $booking_type_string,
                    $interval->booking->description,
                    (
                    $interval->booking->booking_user instanceof User
                        ? $interval->booking->booking_user->getFullName()
                        : ''
                    ),
                    $interval->booking->getAssignedUserName(),
                    $interval->booking->internal_comment
                ];
            }

            $file_name = sprintf(
                _('Belegungsplan %1$s, KW %2$d.csv'),
                $this->resource->getFullName(),
                $this->date->format('W')
            );
            $this->returnCsvData($csv_data, $file_name);
        } else {
            $this->render_nothing();
        }
    }
}
