<?php
/*
 * The controller for the personal calendar.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Peter Thienel <thienel@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since
 */

class Calendar_CalendarController extends AuthenticatedController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if (!Context::isCourse() && Navigation::hasItem('/calendar')) {
            Navigation::activateItem('/calendar');
        }
    }


    protected function buildSidebar(
        bool $schedule = false,
        string $user_id = '',
        string $group_id = ''
    ) {

        $sidebar = Sidebar::get();

        $actions = new ActionsWidget();
        $export  = new ExportWidget();
        if ($schedule) {
            //Add the semester selector widget first:
            $semester_widget = new SemesterSelectorWidget(
                $this->url_for('calendar/calendar/schedule')
            );
            $sidebar->addWidget($semester_widget);

            //Then add the actions for the action widget:
            $actions->addLink(
                _('Neuer Eintrag'),
                $this->url_for('calendar/calendar/add_schedule_entry'),
                Icon::create('add'),
                ['data-dialog' => 'size=default']
            );
        } else {
            $params = [
                'timestamp' => time()
            ];
            if ($user_id) {
                $params['user_id'] = $user_id;
            } elseif ($group_id) {
                $params['group_id'] = $group_id;
            }
            $actions->addLink(
                _('Termin anlegen'),
                $this->url_for('calendar/date/add', $params),
                Icon::create('add'),
                ['data-dialog' => 'size=auto', 'class' => 'calendar-action']
            );
        }

        if (!$GLOBALS['perm']->have_perm('admin')) {
            $actions->addLink(
                _('Veranstaltungen auswählen'),
                $this->url_for('calendar/calendar/add_courses'),
                Icon::create('seminar'),
                ['data-dialog' => 'size=medium']
            );
        }
        if (!$schedule) {
            $export->addLink(
                _('Termine exportieren'),
                $this->url_for('calendar/calendar/export', ['timestamp' => time()]),
                Icon::create('export'),
                ['data-dialog' => 'size=auto', 'class' => 'calendar-action']
            );
            $export->addLink(
                _('Termine importieren'),
                $this->url_for('calendar/calendar/import'),
                Icon::create('import'),
                ['data-dialog' => 'size=auto']
            );
            $actions->addLink(
                _('Kalender teilen'),
                $this->url_for('calendar/calendar/share_select'),
                Icon::create('share'),
                ['data-dialog' => 'size=auto']
            );
            $actions->addLink(
                _('Gruppen verwalten'),
                $this->url_for('contact/index'),
                Icon::create('group2')
            );
        }
        $export->addLink(
            _('Drucken'),
            'javascript:void(window.print());',
            Icon::create('print')
        );
        $actions->addLink(
            _('Einstellungen'),
            $this->url_for('settings/calendar'),
            Icon::create('settings'),
            ['data-dialog' => 'size=auto;reload-on-close']
        );
        $sidebar->addWidget($actions);
        $sidebar->addWidget($export);

        if (!$schedule) {
            $date = new DateSelectWidget();
            $date->setDate(\Studip\Calendar\Helper::getDefaultCalendarDate());
            $date->setCalendarControl(true);
            $sidebar->addWidget($date);
        }
    }

    protected function getUserCalendarSlotSettings() : array
    {
        return [
            'day'        => \Studip\Calendar\Helper::getCalendarSlotDuration('day'),
            'week'       => \Studip\Calendar\Helper::getCalendarSlotDuration('week'),
            'day_group'  => \Studip\Calendar\Helper::getCalendarSlotDuration('day_group'),
            'week_group' => \Studip\Calendar\Helper::getCalendarSlotDuration('week_group')
        ];
    }

    public function index_action()
    {
        PageLayout::setTitle(_('Kalender'));

        $default_date = \Studip\Calendar\Helper::getDefaultCalendarDate();

        if (Request::isPost()) {
            //In case the checkbox of the options widget is clicked, the resulting
            //POST request must be catched here and result in a redirect.
            CSRFProtection::verifyUnsafeRequest();
            if (Request::bool('show_declined')) {
                $this->redirect('calendar/calendar', ['show_declined' => '1']);
            } else {
                $this->redirect('calendar/calendar');
            }
            return;
        }

        if (!Context::isCourse() && Navigation::hasItem('/calendar/calendar')) {
            Navigation::activateItem('/calendar/calendar');
        }

        $view = Request::get('view', 'single');
        $group_view = false;
        $timeline_view = false;
        if (Config::get()->CALENDAR_GROUP_ENABLE) {
            $group_view = in_array($view, ['group', 'timeline']);
            $timeline_view = $view === 'timeline';
        }

        $calendar_owner = null;
        $selected_group = null;
        $user_id = Request::option('user_id', User::findCurrent()->id);
        $group_id = Request::option('group_id');

        if (Config::get()->CALENDAR_GROUP_ENABLE) {
            if ($group_id) {
                $selected_group = ContactGroup::find($group_id);
                if ($selected_group->owner_id !== User::findCurrent()->id) {
                    //Thou shalt not see the groups of others!
                    throw new AccessDeniedException(_('Sie dürfen diesen Kalender nicht sehen!'));
                }
                $view = $view === 'timeline' ? 'timeline' : 'group';
            } elseif ($user_id) {
                $calendar_owner = User::getCalendarOwner($user_id);
                $view = 'single';
            } else {
                //Show the calendar of the current user.
                $view = 'single';
                $calendar_owner = User::findCurrent();
            }
        } else {
            //Show the calendar of the current user.
            $view = 'single';
            $calendar_owner = User::findCurrent();
        }

        //Check for permissions:
        $read_permissions = false;
        $write_permissions = false;
        if ($calendar_owner) {
            $read_permissions  = $calendar_owner->isCalendarReadable();
            $write_permissions = $calendar_owner->isCalendarWritable();
        } elseif ($selected_group) {
            //Count on how many group member calendars the current user has read or write permissions:
            foreach ($selected_group->items as $item) {
                if ($item->user) {
                    if ($item->user->isCalendarReadable()) {
                        $read_permissions = true;
                    }
                    if ($item->user->isCalendarWritable()) {
                        $write_permissions = true;
                    }
                }
                if ($read_permissions && $write_permissions) {
                    //We only need to determine one read and one write permission to set the relevant fullcalendar
                    //properties. The action to add/edit a date determines in which calendars the current user
                    //may write into.
                    break;
                }
            }
        }
        if (!$read_permissions) {
            throw new AccessDeniedException(_('Sie dürfen diesen Kalender nicht sehen!'));
        }

        $this->buildSidebar(
            false,
            $calendar_owner ? $calendar_owner->id : '',
            $selected_group ? $selected_group->id : ''
        );

        $sidebar = Sidebar::get();

        if (Config::get()->CALENDAR_GROUP_ENABLE) {
            if ($calendar_owner && $calendar_owner->id === User::findCurrent()->id) {
                //The user is viewing their own calendar.
                $options = new OptionsWidget();
                $options->addLayoutCSSClass('calendar-action');
                $options->addCheckbox(
                    _('Abgelehnte Termine anzeigen'),
                    Request::bool('show_declined'),
                    $this->url_for('calendar/calendar', ['show_declined' => '1']),
                    $this->url_for('calendar/calendar')
                );
                $sidebar->addWidget($options);
            }

            //Check if the user has groups. If so, display a select widget to select a group.
            $groups = ContactGroup::findBySQL(
                'owner_id = :owner_id ORDER BY name ASC',
                [
                    'owner_id' => User::findCurrent()->id
                ]
            );
            if ($groups) {
                $available_groups = [];

                //Check if the user has at least read permissions for the calendar of one user of one group:
                foreach ($groups as $group) {
                    foreach ($group->items as $item) {
                        if ($item->user && $item->user->isCalendarReadable()) {
                            $available_groups[] = $group;
                            break 1;
                        }
                    }
                }
                if ($available_groups) {
                    $group_select = new SelectWidget(
                        _('Gruppe'),
                        $this->url_for('calendar/calendar/index', ['view' => 'group']),
                        'group_id'
                    );
                    $group_select->addLayoutCSSClass('calendar-action');
                    $options = [
                        '' => _('(bitte wählen)')
                    ];
                    foreach ($available_groups as $available_group) {
                        $options[$available_group->id] = $available_group->name;
                    }
                    $group_select->setOptions($options);
                    $group_select->setSelection($group_id);
                    $sidebar->addWidget($group_select);
                }
            }
            //Get all calendars where the user has access to:
            $other_users = User::findBySql(
                "INNER JOIN `contact` c
                ON `auth_user_md5`.`user_id` = c.`owner_id`
                WHERE c.`user_id` = :current_user_id
                AND c.`calendar_permissions` <> ''
                ORDER BY `auth_user_md5`.`Vorname` ASC, `auth_user_md5`.`Nachname` ASC",
                ['current_user_id' => User::findCurrent()->id]
            );
            if ($other_users) {
                $calendar_select = new SelectWidget(
                    _('Kalender auswählen'),
                    $this->url_for('calendar/calendar'),
                    'user_id'
                );
                $calendar_select->addLayoutCSSClass('calendar-action');
                $select_options = [
                    '' => _('(bitte wählen)'),
                    User::findCurrent()->id => _('Eigener Kalender')
                ];
                foreach ($other_users as $user) {
                    $select_options[$user->id] = sprintf('%1$s %2$s', $user->vorname, $user->nachname);
                }
                $calendar_select->setOptions($select_options, Request::get('user_id'));
                $sidebar->addWidget($calendar_select);
            }
        }

        if (Config::get()->CALENDAR_GROUP_ENABLE && $selected_group) {
            $views = new ViewsWidget();
            $views->setTitle(_('Kalenderansicht'));
            $views->addLink(
                _('Gruppenkalender'),
                $this->url_for('calendar/calendar', ['view' => 'group', 'group_id' => $group_id])
            )->setActive($view === 'group');
            $views->addLink(
                _('Zeitleiste'),
                $this->url_for('calendar/calendar', ['view' => 'timeline', 'group_id' => $group_id])
            )->setActive($view === 'timeline');
            $sidebar->addWidget($views);
        }

        $calendar_resources = [];
        $calendar_group_title = '';
        if ($group_view && $selected_group) {
            //All users in the selected group that have granted read permissions to the user can be shown.
            foreach ($selected_group->items as $item) {
                if ($item->user && $item->user->isCalendarReadable()) {
                    $calendar_resources[] = [
                        'id' => $item->user_id,
                        'title' => $item->user ? $item->user->getFullName() : '',
                        'parent_name' => ''
                    ];
                }
            }
            $calendar_group_title = $selected_group->name;
        }

        $fullcalendar_studip_urls = [];
        if ($write_permissions) {
            if ($calendar_owner) {
                $fullcalendar_studip_urls['add'] = $this->url_for('calendar/date/add', ['user_id' => $calendar_owner->id]);
            } elseif ($selected_group) {
                $fullcalendar_studip_urls['add'] = $this->url_for('calendar/date/add', ['group_id' => $group->id]);
            }
        }

        $calendar_settings = User::findCurrent()->getConfiguration()->CALENDAR_SETTINGS ?? [];

        //Map calendar settings to fullcalendar settings:

        $default_view = \Studip\Fullcalendar::VIEW_WEEK;
        if ($timeline_view) {
            $default_view = \Studip\Fullcalendar::GROUP_WEEK;
            if ($calendar_settings['view'] === 'day' || Request::get('defaultView') === 'day') {
                $default_view = \Studip\Fullcalendar::GROUP_DAY;
            }
        } elseif (!empty($calendar_settings['view'])) {
            if ($calendar_settings['view'] === 'day' || Request::get('defaultView') === 'day') {
                $default_view = \Studip\Fullcalendar::VIEW_DAY;
            } elseif ($calendar_settings['view'] === 'month' || Request::get('defaultView') === 'month') {
                $default_view = \Studip\Fullcalendar::VIEW_MONTH;
            }
        }

        $slot_durations = $this->getUserCalendarSlotSettings();

        //Create the fullcalendar object:

        $data_url_params = [];
        if (Request::bool('show_declined')) {
            $data_url_params['show_declined'] = '1';
        }
        if ($timeline_view) {
            $data_url_params['timeline_view'] = '1';
        }

        $available_views = [];
        if ($timeline_view) {
            $available_views = [
                \Studip\Fullcalendar::GROUP_WEEK,
                \Studip\Fullcalendar::GROUP_DAY
            ];
        } else {
            $available_views = [
                \Studip\Fullcalendar::VIEW_MONTH,
                \Studip\Fullcalendar::VIEW_WEEK,
                \Studip\Fullcalendar::VIEW_DAY
            ];
        }

        $this->fullcalendar = Studip\Fullcalendar::create(
            _('Kalender'),
            [
                'editable'      => $write_permissions,
                'selectable'    => $write_permissions,
                'studip_urls'   => $fullcalendar_studip_urls,
                'slotMinTime'   => sprintf('%02u:00', $calendar_settings['start'] ?? 8),
                'slotMaxTime'   => sprintf('%02u:00', $calendar_settings['end'] ?? 20),
                'initialDate'   => $default_date->format('Y-m-d'),
                'allDaySlot'    => true,
                'allDayText'    => '',
                'headerToolbar' => [
                    'start'   => implode(',', $available_views),
                    'center'  => 'title',
                    'end'     => 'prev,today,next'
                ],
                'weekNumbers' => true,
                'views' => [
                    \Studip\Fullcalendar::VIEW_MONTH => [
                        'eventTimeFormat' => ['hour' => 'numeric', 'minute' => '2-digit'],
                        'titleFormat'     => ['year' => 'numeric', 'month' => 'long'],
                        'displayEventEnd' => true
                    ],
                    \Studip\Fullcalendar::VIEW_WEEK => [
                        'dayHeaderFormat' => ['weekday' => 'short', 'year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit', 'omitCommas' => true],
                        'weekends'        => $calendar_settings['type_week'] === 'LONG',
                        'titleFormat'     => ['year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit'],
                        'slotDuration'    => $slot_durations['week']
                    ],
                    \Studip\Fullcalendar::VIEW_DAY => [
                        'dayHeaderFormat' => ['weekday' => 'long', 'year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit', 'omitCommas' => true],
                        'titleFormat'     => ['year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit'],
                        'slotDuration'    => $slot_durations['day']
                    ],
                    \Studip\Fullcalendar::GROUP_WEEK => [
                        'dayHeaderFormat' => ['weekday' => 'long', 'year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit', 'omitCommas' => true],
                        'titleFormat'     => ['year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit'],
                        'weekends'        => $calendar_settings['type_week'] === 'LONG',
                        'slotDuration'    => $slot_durations['week_group']
                    ],
                    \Studip\Fullcalendar::GROUP_DAY => [
                        'dayHeaderFormat' => ['weekday' => 'long', 'year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit', 'omitCommas' => true],
                        'titleFormat'     => ['year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit'],
                        'slotDuration'    => $slot_durations['day_group']
                    ]
                ],
                'initialView' => $default_view,
                'eventMinHeight' => 20,
                'eventSources' => [
                    [
                        'url' => $this->url_for(
                            (
                            $group_view
                                ? 'calendar/calendar/calendar_group_data/' . $selected_group->id
                                : 'calendar/calendar/calendar_data/user_' . $calendar_owner->id
                            ),
                            $data_url_params
                        ),
                        'method' => 'GET'
                    ]
                ],
                'resources'                 => $calendar_resources,
                'resourceAreaHeaderContent' => $calendar_group_title
            ]
        );
    }

    public function course_action($course_id)
    {
        PageLayout::setTitle(_('Veranstaltungskalender'));

        if (!$course_id || !Config::get()->CALENDAR_GROUP_ENABLE || !Config::get()->COURSE_CALENDAR_ENABLE) {
            throw new AccessDeniedException(_('Sie dürfen diesen Kalender nicht sehen!'));
        }

        $course = Course::find($course_id);
        if (!$course) {
            throw new AccessDeniedException(_('Sie dürfen diesen Kalender nicht sehen!'));
        }

        if (!$course->isVisibleForUser() || !$course->isCalendarReadable()) {
            throw new AccessDeniedException(_('Sie dürfen diesen Kalender nicht sehen!'));
        }

        if (Navigation::hasItem('/course/calendar')) {
            Navigation::activateItem('/course/calendar');
        }

        $sidebar = Sidebar::get();

        $actions = new ActionsWidget();
        $actions->addLink(
            _('Termin anlegen'),
            $this->url_for('calendar/date/add/course_' . $course->id),
            Icon::create('add'),
            ['data-dialog' => 'size=default', 'class' => 'calendar-action']
        );
        $actions->addLink(
            _('Drucken'),
            'javascript:void(window.print());',
            Icon::create('print')
        );
        $actions->addLink(
            _('Einstellungen'),
            $this->url_for('settings/calendar'),
            Icon::create('settings'),
            ['data-dialog' => 'reload-on-close']
        );
        $sidebar->addWidget($actions);

        $date = new DateSelectWidget();
        $date->setCalendarControl(true);
        $sidebar->addWidget($date);

        //Create the fullcalendar object:

        $calendar_writable = $course->isCalendarWritable();
        $calendar_settings = User::findCurrent()->getConfiguration()->CALENDAR_SETTINGS ?? [];
        $slot_settings = $this->getUserCalendarSlotSettings();

        $fullcalendar_studip_urls = [];
        if ($calendar_writable) {
            $fullcalendar_studip_urls['add'] = $this->url_for('calendar/date/add/course_' . $course->id);
        }

        $available_views = [
            'dayGridYear',
            \Studip\Fullcalendar::VIEW_MONTH,
            \Studip\Fullcalendar::VIEW_WEEK,
            \Studip\Fullcalendar::VIEW_DAY
        ];

        $this->fullcalendar = Studip\Fullcalendar::create(
            _('Veranstaltungskalender'),
            [
                'editable'    => $calendar_writable,
                'selectable'  => $calendar_writable,
                'studip_urls' => $fullcalendar_studip_urls,
                'minTime'     => sprintf('%02u:00', $calendar_settings['start'] ?? 8),
                'maxTime'     => sprintf('%02u:00', $calendar_settings['end'] ?? 20),
                'allDaySlot'  => true,
                'allDayText'  => '',
                'headerToolbar' => [
                    'start'  => implode(',', $available_views),
                    'center' => 'title',
                    'end'    => 'prev,today,next'
                ],
                'weekNumbers' => true,
                'views'       => [
                    \Studip\Fullcalendar::VIEW_MONTH => [
                        'eventTimeFormat' => ['hour' => 'numeric', 'minute' => '2-digit'],
                        'titleFormat'     => ['year' => 'numeric', 'month' => 'long'],
                        'displayEventEnd' => true
                    ],
                    \Studip\Fullcalendar::VIEW_WEEK  => [
                        'dayHeaderFormat' => [ 'weekday' => 'short', 'year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit', 'omitCommas' => true ],
                        'weekends'           => $calendar_settings['type_week'] === 'LONG',
                        'titleFormat'        => ['year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit'],
                        'slotDuration'       => $slot_settings['week']
                    ],
                    \Studip\Fullcalendar::VIEW_DAY   => [
                        'dayHeaderFormat' => [ 'weekday' => 'long', 'year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit', 'omitCommas' => true ],
                        'titleFormat'        => ['year' => 'numeric', 'month' => '2-digit', 'day' => '2-digit'],
                        'slotDuration'       => $slot_settings['day']
                    ]
                ],
                'initialView'            => \Studip\Fullcalendar::VIEW_WEEK,
                'timeGridEventMinHeight' => 20,
                'eventSources'           => [
                    [
                        'url'         => $this->url_for('calendar/calendar/calendar_data/course_' . $course->id),
                        'method'      => 'GET',
                        'extraParams' => []
                    ]
                ]
            ]
        );
    }

    public function calendar_data_action($range_and_id)
    {
        $range_and_id = explode('_', $range_and_id);
        $range = '';
        $range_id = '';
        if (!empty($range_and_id[1])) {
            $range = $range_and_id[0];
            $range_id = $range_and_id[1];
        }
        if (!$range) {
            //Show the personal calendar of the current user:
            $range = 'user';
            $range_id = User::findCurrent()->id;
        }
        $owner = null;
        if (!$range_id) {
            //Assume a user calendar. $range contains the user-ID.
            $owner = User::getCalendarOwner($range);
        } elseif ($range === 'user') {
            $owner = User::getCalendarOwner($range_id);
        } elseif ($range === 'course') {
            $owner = Course::getCalendarOwner($range_id);
        }

        if (!$owner || !$owner->isCalendarReadable()) {
            throw new AccessDeniedException(_('Sie dürfen diesen Kalender nicht sehen!'));
        }

        $begin = Request::getDateTime('start', \DateTime::RFC3339);
        $end = Request::getDateTime('end', \DateTime::RFC3339);
        if (!($begin instanceof \DateTime) || !($end instanceof \DateTime)) {
            //No time range specified.
            throw new InvalidArgumentException('Invalid parameters!');
        }

        $calendar_events = CalendarDateAssignment::getEvents(
            $begin,
            $end,
            $owner->id,
            ['PUBLIC', 'PRIVATE', 'CONFIDENTIAL'],
            Request::bool('show_declined', false)
        );

        $result = [];

        foreach ($calendar_events as $date) {
            $event = $date->toEventData(User::findCurrent()->id);
            $result[] = $event->toFullcalendarEvent();
        }

        if ($range === 'user') {
            //Include course dates of courses that shall be displayed in the calendar:
            $course_dates = CalendarCourseDate::getEvents($begin, $end, $owner->id);
            foreach ($course_dates as $course_date) {
                $event = $course_date->toEventData(User::findCurrent()->id);
                $event->background_colour = '';
                $event->text_colour = '#000000';
                $event->border_colour = '';
                $result[] = $event->toFullcalendarEvent();
            }
            //Include relevant cancelled course dates:
            $cancelled_course_dates = CalendarCourseExDate::getEvents($begin, $end, $owner->id);
            foreach ($cancelled_course_dates as $cancelled_course_date) {
                $event = $cancelled_course_date->toEventData(User::findCurrent()->id);
                $event->background_colour = '';
                $event->text_colour = '#000000';
                $event->border_colour = '';
                $result[] = $event->toFullcalendarEvent();
            }
        }
        //At this point, everything went fine. We can save the beginning as default date.
        $_SESSION['calendar_date'] = $begin->format('Y-m-d');
        $this->render_json($result);
    }

    public function calendar_group_data_action($group_id)
    {
        $begin = Request::getDateTime('start', \DateTime::RFC3339);
        $end = Request::getDateTime('end', \DateTime::RFC3339);
        $timeline_view = Request::bool('timeline_view', false);

        if (!($begin instanceof \DateTime) || !($end instanceof \DateTime)) {
            //No time range specified.
            throw new InvalidArgumentException('Invalid parameters!');
        }

        $group = null;
        $users = [];
        if ($group_id) {
            //Get the group first:
            $group = ContactGroup::find($group_id);
            if ($group->owner_id !== User::findCurrent()->id) {
                throw new AccessDeniedException();
            }
            foreach ($group->items as $item) {
                if ($item->user->isCalendarReadable()) {
                    $users[] = $item->user;
                }
            }
            if (!$users) {
                //No user has granted read access to the calendar for the current user.
                throw new AccessDeniedException(_('Sie dürfen diesen Kalender nicht sehen!'));
            }
        }

        $result = [];

        uasort($users, function(User $a, User $b) {
            $fullname_a = $a->getFullName('no_title');
            $fullname_b = $b->getFullName('no_title');
            if ($fullname_a == $fullname_b) {
                return 0;
            }
            return ($fullname_a > $fullname_b) ? 1 : -1;
        });

        foreach ($users as $user) {
            $events = CalendarDateAssignment::getEvents($begin, $end, $user->id);
            if ($events) {
                foreach ($events as $event) {
                    $data = $event->toEventData(User::findCurrent()->id);
                    if ($timeline_view) {
                        $result[] = $data->toFullcalendarEvent();
                    } else {
                        //Prevent duplicate entries:
                        $data->title = $user->getFullName('no_title');
                        if (array_key_exists($event->calendar_date_id, $result)) {
                            $result[$event->calendar_date_id]['title'] .= ', ' . $data->title;
                        } else {
                            $result[$event->calendar_date_id] = $data->toFullcalendarEvent();
                        }
                    }
                }
            }
        }
        if ($timeline_view) {
            $this->render_json($result);
        } else {
            //Clean up the array keys:
            $this->render_json(array_values($result));
        }
    }

    public function add_courses_action()
    {
        $selected_semester_pseudo_id = Request::option('semester_id');
        $this->selected_semester_id = '';
        $this->available_semester_data = [];
        $semesters = Semester::getAll();
        foreach ($semesters as $semester) {
            $this->available_semester_data[$semester['id']] = [
                'id'   => $semester['id'],
                'name' => $semester['name']
            ];
        }
        $this->available_semester_data = array_reverse($this->available_semester_data);

        if (!$selected_semester_pseudo_id) {
            $selected_semester_pseudo_id = User::findCurrent()->getConfiguration()->MY_COURSES_SELECTED_CYCLE;
            if (!Config::get()->MY_COURSES_ENABLE_ALL_SEMESTERS && $selected_semester_pseudo_id === 'all') {
                $selected_semester_pseudo_id = 'next';
            }
            if (!$selected_semester_pseudo_id) {
                $selected_semester_pseudo_id = Config::get()->MY_COURSES_DEFAULT_CYCLE;
            }
        }
        if ($selected_semester_pseudo_id === 'next') {
            $semester = Semester::findNext();
            $this->selected_semester_id = $semester->id;
        } elseif (in_array($selected_semester_pseudo_id, ['all', 'current'])) {
            $semester = Semester::findCurrent();
            $this->selected_semester_id = $semester->id;
        } elseif ($selected_semester_pseudo_id === 'last') {
            $semester = Semester::findPrevious();
            $this->selected_semester_id = $semester->id;
        } else {
            $this->selected_semester_id = $selected_semester_pseudo_id ?? '';
            if (!Semester::exists($this->selected_semester_id)) {
                $semester = Semester::findCurrent();
                $this->selected_semester_id = $semester->id;
            }
        }

        $this->selected_course_ids = SimpleCollection::createFromArray(
            CourseMember::findBySQL(
                'user_id = :user_id AND bind_calendar = 1',
                ['user_id' => User::findCurrent()->id]
            )
        )->pluck('seminar_id');

        $this->semester_data = [];
        $all_semesters = Semester::getAll();
        foreach ($all_semesters as $semester) {
            $data = [
                'id' => $semester->id,
                'name' => $semester->name,
                'courses' => []
            ];
            $this->semester_data[] = $data;
        }

        if (Request::submitted('add')) {
            CSRFProtection::verifyUnsafeRequest();

            $course_ids = Request::getArray('courses_course_ids');
            foreach ($course_ids as $course_id => $selected) {
                $course_membership = CourseMember::findOneBySQL(
                    'seminar_id = :course_id AND user_id = :user_id',
                    [
                        'course_id' => $course_id,
                        'user_id'   => User::findCurrent()->id
                    ]
                );
                if ($course_membership) {
                    $course_membership->bind_calendar = $selected ? '1' : '0';
                    $course_membership->store();
                }
            }
            PageLayout::postSuccess(_('Die Zuordnung von Veranstaltungen zum Kalender wurde aktualisiert.'));
            $this->redirect('calendar/calendar/index');
        }
    }

    public function export_action()
    {
        PageLayout::setTitle(_('Termine exportieren'));
        $this->begin = new DateTimeImmutable();
        if (Request::submitted('timestamp')) {
            $this->begin = $this->begin->setTimestamp(Request::int('timestamp'));
        }
        $this->end = $this->begin->add(new DateInterval('P1Y'));
        $this->dates_to_export = 'user';
        if (Request::submitted('export')) {
            CSRFProtection::verifyUnsafeRequest();
            $this->begin = Request::getDateTime('begin', 'd.m.Y');
            $this->end = Request::getDateTime('end', 'd.m.Y');
            if ($this->begin >= $this->end) {
                PageLayout::postError(_('Der Startzeitpunkt darf nicht nach dem Endzeitpunkt liegen!'));
                return;
            }
            $this->dates_to_export = Request::get('dates_to_export');
            if (!in_array($this->dates_to_export, ['user', 'course', 'all'])) {
                PageLayout::postError(_('Bitte wählen Sie aus, welche Termine exportiert werden sollen!'));
                return;
            }
            $this->relocate($this->url_for('calendar/calendar/export_file', [
                'begin' => $this->begin->format('d.m.Y'),
                'end' => $this->end->format('d.m.Y'),
                'dates_to_export' => $this->dates_to_export
            ]));
        }
    }

    public function export_file_action()
    {
        $begin = Request::getDateTime('begin', 'd.m.Y');
        $end = Request::getDateTime('end', 'd.m.Y');
        $dates_to_export = Request::option('dates_to_export', 'user');
        $ical = '';
        $calendar_export = new ICalendarExport();
        if ($dates_to_export === 'user') {
            $ical = $calendar_export->exportCalendarDates(User::findCurrent()->id, $begin, $end);
        } elseif ($dates_to_export === 'course') {
            $ical = $calendar_export->exportCourseDates(User::findCurrent()->id, $begin, $end);
            $ical .= $calendar_export->exportCourseExDates(User::findCurrent()->id, $begin, $end);
        } elseif ($dates_to_export === 'all') {
            $ical = $calendar_export->exportCalendarDates(User::findCurrent()->id, $begin, $end);
            $ical .= $calendar_export->exportCourseDates(User::findCurrent()->id, $begin, $end);
            $ical .= $calendar_export->exportCourseExDates(User::findCurrent()->id, $begin, $end);
        }
        $ical = $calendar_export->writeHeader() . $ical . $calendar_export->writeFooter();
        $this->response->add_header('Content-Type', 'text/calendar;charset=utf-8');
        $this->response->add_header('Content-Disposition', 'attachment; filename="studip.ics"');
        $this->response->add_header('Content-Transfer-Encoding', 'binary');
        $this->response->add_header('Pragma', 'public');
        $this->response->add_header('Cache-Control', 'private');
        $this->response->add_header('Content-Length', strlen($ical));
        $this->render_text($ical);
    }

    public function import_action() {}

    public function import_file_action()
    {
        if (Request::submitted('import')) {
            CSRFProtection::verifyUnsafeRequest();
            $range_id = Context::getId() ?? User::findCurrent()->id;
            $calendar_import = new ICalendarImport($range_id);
            $calendar_import->convertPublicToPrivate(Request::bool('import_privat', false));
            $calendar_import->import(file_get_contents($_FILES['importfile']['tmp_name']));
            $import_count = $calendar_import->getCountEvents();
            PageLayout::postSuccess(sprintf(
                ngettext(
                    'Ein Termin wurde importiert.',
                    'Es wurden %u Termine importiert.',
                    $import_count
                ),
                $import_count
            ));
            $this->redirect($this->url_for('calendar/calendar/'));
        }
    }

    public function share_select_action()
    {
        if (!Config::get()->CALENDAR_GROUP_ENABLE) {
            $this->redirect($this->url_for('calendar/calendar/publish'));
        }
    }

    public function share_action()
    {
        PageLayout::setTitle(_('Kalender teilen'));
        if (!Config::get()->CALENDAR_GROUP_ENABLE) {
            throw new FeatureDisabledException();
        }

        $calendar_contacts = Contact::findBySql(
            "JOIN `auth_user_md5` USING (`user_id`)
             WHERE `contact`.`owner_id` = :user_id
               AND `contact`.`calendar_permissions` <> ''
             ORDER BY `auth_user_md5`.`Vorname`, `auth_user_md5`.`Nachname`",
            [
                'user_id' => User::findCurrent()->id
            ]
        );
        $user_data = [];
        foreach ($calendar_contacts as $contact) {
            $user_data[$contact->user_id] = [
                'id' => $contact->user_id,
                'name' => $contact->friend->getFullName(),
                'write_permissions' => $contact->calendar_permissions === 'WRITE'
            ];
        }
        $this->selected_users_json = json_encode($user_data, JSON_FORCE_OBJECT);
        $this->searchtype = new StandardSearch('user_id', ['simple_name' => true]);

        if (Request::submitted('share')) {
            CSRFProtection::verifyUnsafeRequest();
            $selected_user_ids = Request::getArray('calendar_permissions', []);
            $write_permissions = Request::getArray('calendar_write_permissions', []);

            //Add/update contacts with calendar permissions:

            foreach ($selected_user_ids as $user_id) {
                $user = User::find($user_id);
                if (!$user) {
                    //No user? No contact!
                    continue;
                }
                $contact = Contact::findOneBySql(
                    'owner_id = :owner_id AND user_id = :user_id',
                    [
                        'owner_id' => User::findCurrent()->id,
                        'user_id' => $user_id
                    ]
                );
                if (!$contact) {
                    $contact = new Contact();
                    $contact->owner_id = User::findCurrent()->id;
                    $contact->user_id = $user->id;
                }
                if (in_array($user->id, $write_permissions)) {
                    $contact->calendar_permissions = 'WRITE';
                } else {
                    $contact->calendar_permissions = 'READ';
                }
                $contact->store();
            }

            //Revoke calendar permissions for all users that aren't in the list
            //of selected users:
            if ($selected_user_ids) {
                $stmt = DBManager::get()->prepare(
                    "UPDATE `contact` SET `calendar_permissions` = ''
                    WHERE `owner_id` = :owner_id
                    AND `user_id` NOT IN ( :user_ids )"
                );
                $stmt->execute([
                    'owner_id' => User::findCurrent()->id,
                    'user_ids' => $selected_user_ids
                ]);
            } else {
                $stmt = DBManager::get()->prepare(
                    "UPDATE `contact` SET `calendar_permissions` = ''
                    WHERE `owner_id` = :owner_id"
                );
                $stmt->execute(['owner_id' => User::findCurrent()->id]);
            }

            PageLayout::postSuccess(
                _('Die Kalenderfreigaben wurden geändert.')
            );
            $this->response->add_header('X-Dialog-Close', '1');
        }
    }

    public function publish_action()
    {
        $this->short_id = null;
        if (Request::submitted('delete_id')) {
            CSRFProtection::verifyUnsafeRequest();
            IcalExport::deleteKey(User::findCurrent()->id);
            PageLayout::postSuccess(_('Die Adresse, unter der Ihre Termine abrufbar sind, wurde gelöscht'));
        }

        if (Request::submitted('new_id')) {
            CSRFProtection::verifyUnsafeRequest();
            $this->short_id = IcalExport::setKey(User::findCurrent()->id);
            PageLayout::postSuccess(_('Eine Adresse, unter der Ihre Termine abrufbar sind, wurde erstellt.'));
        } else {
            $this->short_id = IcalExport::getKeyByUser(User::findCurrent()->id);
        }

        $text = '';
        if (Request::submitted('submit_email')) {
            $email_reg_exp = '/^([-.0-9=?A-Z_a-z{|}~])+@([-.0-9=?A-Z_a-z{|}~])+\.[a-zA-Z]{2,6}$/i';
            if (preg_match($email_reg_exp, Request::get('email')) !== 0) {
                $subject = '[' .Config::get()->UNI_NAME_CLEAN . ']' . _('Exportadresse für Ihre Termine');
                $text .= _('Diese Email wurde vom Stud.IP-System verschickt. Sie können auf diese Nachricht nicht antworten.') . "\n\n";
                $text .= _('Über diese Adresse erreichen Sie den Export für Ihre Termine:') . "\n\n";
                $text .= $GLOBALS['ABSOLUTE_URI_STUDIP'] . 'dispatch.php/ical/index/'
                    . IcalExport::getKeyByUser(User::findCurrent()->id);
                StudipMail::sendMessage(Request::get('email'), $subject, $text);
                PageLayout::postSuccess(_('Die Adresse wurde verschickt!'));
            } else {
                PageLayout::postError(_('Bitte geben Sie eine gültige Email-Adresse an.'));
            }
            $this->short_id = IcalExport::getKeyByUser(User::findCurrent()->id);
        }
        PageLayout::setTitle(_('Kalender veröffentlichen'));
    }
}
