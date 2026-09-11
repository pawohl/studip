<?php

/**
 * schedule.php - Calender schedule controller
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Moritz Strohm <strohm@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     calender
 * @since       6.0
 */

class Calendar_ScheduleController extends AuthenticatedController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if (!Context::isCourse() && Navigation::hasItem('/calendar')) {
            Navigation::activateItem('/calendar');
        }
    }

    public function index_action()
    {
        PageLayout::setTitle(_('Stundenplan'));

        if (Navigation::hasItem('/calendar/schedule')) {
            Navigation::activateItem('/calendar/schedule');
        }

        $show_hidden = Request::bool('show_hidden', false);

        //Handle the selected semester and create a Fullcalendar instance.

        $this->semester = null;
        if (Request::submitted('semester_id')) {
            $this->semester = Semester::find(Request::option('semester_id'));
            if ($this->semester) {
                //Store the new semester-ID in the session:
                $_SESSION['schedule_semester_id'] = $this->semester->id;
            }
        }
        if (!$this->semester) {
            //Load the semester from the session:
            $semester_id = $_SESSION['schedule_semester_id'] ?? '';
            if ($semester_id) {
                $this->semester = Semester::find($semester_id);
            } else {
                $this->semester = Semester::findCurrent();
            }
        }

        if ($this->semester) {
            PageLayout::setTitle(
                studip_interpolate(
                    _('Stundenplan %{semester}'),
                    ['semester' => $this->semester->name]
                )
            );
        }

        //Build the sidebar:

        $sidebar = Sidebar::get();

        //Add the semester selector widget first:
        $semester_widget = new SemesterSelectorWidget(
            $this->indexURL(['show_hidden' => $show_hidden ?: null])
        );
        $semester_widget->setSelection($this->semester->id ?? '');
        $sidebar->addWidget($semester_widget);

        //Then add the actions for the action widget:
        $actions = new ActionsWidget();
        $actions->addLink(
            _('Neuer Termin'),
            $this->url_for('calendar/schedule/entry/add'),
            Icon::create('add'),
            ['data-dialog' => 'size=auto']
        );
        if ($show_hidden) {
            $actions->addLink(
                _('Ausgeblendete Veranstaltungen verstecken'),
                $this->indexURL(['semester_id' => Request::get('semester_id')]),
                Icon::create('visibility-visible')
            )->asButton();
        } else {
            $actions->addLink(
                _('Ausgeblendete Veranstaltungen anzeigen'),
                $this->indexURL([
                    'show_hidden' => true,
                    'semester_id' => Request::get('semester_id'),
                ]),
                Icon::create('visibility-invisible')
            )->asButton();
        }

        $actions->addLink(
            _('Drucken'),
            '#',
            Icon::create('print'),
            ['onclick' => 'window.print(); return false;']
        );
        $actions->addLink(
            _('Einstellungen'),
            $this->url_for('calendar/schedule/settings'),
            Icon::create('settings'),
            ['data-dialog' => 'size=auto;reload-on-close']
        );
        $sidebar->addWidget($actions);

        $schedule_settings = UserConfig::get(User::findCurrent()->id)->getValue('SCHEDULE_SETTINGS');
        $size = $schedule_settings['size'] ?? 'medium';
        if (Request::submitted('size')) {
            $size = Request::option('size');
            if (in_array($size, ['small', 'medium', 'large'])) {
                //Set the new size in the schedule settings:
                $schedule_settings['size'] = $size;
                UserConfig::get(User::findCurrent()->id)->store('SCHEDULE_SETTINGS', $schedule_settings);
            } else {
                $size = 'medium';
            }
        }
        $views = new ViewsWidget();
        $views->setTitle(_('Größe'));
        $views->addLink(
            _('Klein'),
            $this->url_for('calendar/schedule/index', ['size' => 'small'])
        )->setActive($size === 'small');
        $views->addLink(
            _('Mittel'),
            $this->url_for('calendar/schedule/index', ['size' => 'medium'])
        )->setActive($size === 'medium');
        $views->addLink(
            _('Groß'),
            $this->url_for('calendar/schedule/index', ['size' => 'large'])
        )->setActive($size === 'large');
        $sidebar->addWidget($views);

        $fullcalendar = \Studip\Calendar\Helper::getScheduleFullcalendar(
            $this->semester->id ?? '',
            Request::bool('show_hidden', false)
        );
        $fullcalendar->setResponsiveDefaultView('timeGridDay');
        $this->fullcalendar = $fullcalendar->render();
    }

    public function data_action()
    {
        //Fullcalendar sets the time range in which to put the course dates
        //of the semester. Therefore, start and end are handled in here.
        //The range is not necessary a full week: If fullcalendar starts in the day
        //view (like in the mobile view), the start of the range may lie in the middle
        //of the week.
        $begin = Request::getDateTime('start', \DateTimeInterface::RFC3339);
        $end   = Request::getDateTime('end', \DateTimeInterface::RFC3339);
        if (!($begin instanceof \DateTime) || !($end instanceof \DateTime)) {
            //No time range specified.
            throw new InvalidArgumentException('Invalid parameters!');
        }

        $result = [];

        $semester_id = Request::option('semester_id');
        $semester = Semester::find($semester_id);
        $show_hidden = Request::bool('show_hidden', false);

        if ($semester) {
            //Get all regular course dates for that semester
            //whose day of week lies in the range of the fullcalendar time range:
            $days_of_week = [];
            for ($dow = clone $begin; $dow <= $end; $dow = $dow->add(new DateInterval('P1D'))) {
                $days_of_week[] = $dow->format('N');
            }
            $query = "SELECT scd.*
                      FROM (
                        -- Ermittle alle VA der Person
                        SELECT DISTINCT seminar_id
                        FROM (
                          SELECT su.Seminar_id AS seminar_id
                          FROM seminar_user su
                          WHERE su.user_id = :user_id

                          UNION ALL

                          SELECT sc.course_id
                          FROM schedule_courses sc
                          WHERE sc.user_id = :user_id
                        ) AS tmp
                      ) AS u
                      -- JOIN-Reihenfolge forcieren (erst klein, dann groß)
                      STRAIGHT_JOIN `seminar_cycle_dates` AS scd
                        -- Den richtigen Index erzwingen
                        FORCE INDEX (`seminar_id`)
                        USING (`seminar_id`)
                      WHERE `weekday` IN (:days_of_week)
                        AND EXISTS (
                          SELECT 1
                          FROM `termine` t
                          WHERE t.`metadate_id` = scd.`metadate_id`
                            AND t.`date` <= :end
                            AND t.`end_time` >= :begin
                        )";
            $cycle_dates = DBManager::get()->fetchAll(
                $query,
                [
                    ':days_of_week' => $days_of_week,
                    ':user_id'      => User::findCurrent()->id,
                    ':begin'        => $semester->beginn,
                    ':end'          => $semester->ende
                ],
                fn(array $row): SeminarCycleDate => SeminarCycleDate::buildExisting($row)
            );

            foreach ($cycle_dates as $cycle_date) {
                //Calculate a fake begin and end that lies in the week
                //fullcalendar has specified. We need the start of the current week for that.
                $fake_begin = clone $begin;
                if ($fake_begin->format('N') > 1) {
                    //The beginning of the time range does not lie on a monday.
                    $monday_offset = (int)$fake_begin->format('N') - 1;
                    $fake_begin->sub(new DateInterval('P' . $monday_offset . 'D'));
                }
                $fake_end = clone $fake_begin;
                if ($cycle_date->weekday > 1) {
                    $fake_begin = $fake_begin->add(new DateInterval('P' . ($cycle_date->weekday - 1) . 'D'));
                    $fake_end = $fake_end->add(new DateInterval('P' . ($cycle_date->weekday - 1) . 'D'));
                }
                $start_time_parts = explode(':', $cycle_date->start_time);
                $end_time_parts = explode(':', $cycle_date->end_time);
                $fake_begin->setTime(
                    $start_time_parts[0],
                    $start_time_parts[1],
                    $start_time_parts[2]
                );
                $fake_end->setTime(
                    $end_time_parts[0],
                    $end_time_parts[1],
                    $end_time_parts[2]
                );

                $schedule_course = ScheduleCourseDate::findOneBySQL(
                    '`metadate_id` = :cycle_date_id AND `user_id` = :user_id',
                    [
                        'cycle_date_id' => $cycle_date->id,
                        'user_id'       => $GLOBALS['user']->id
                    ]
                );
                $is_hidden = $schedule_course && !$schedule_course->visible;
                if (!$show_hidden && $is_hidden) {
                    //The regular date belongs to a course that has been hidden in the schedule.
                    //The flag to include hidden courses is not set which means that the regular
                    //date shall not be included.
                    continue;
                }

                //Get the course colour:
                $course_membership = CourseMember::findOneBySQL(
                    '`seminar_id` = :course_id AND `user_id` = :user_id',
                    [
                        'course_id' => $cycle_date->seminar_id,
                        'user_id' => $GLOBALS['user']->id
                    ]
                );

                $event_classes = ['schedule', 'course'];
                $event_title   = $cycle_date->course->getFullName('number-name');

                if ($course_membership) {
                    $event_classes[] = sprintf('course-color-%u', $course_membership->gruppe);

                    $lecturer_names = array_map(
                        fn($lecturer) => $lecturer->user->nachname,
                        CourseMember::findByCourseAndStatus($course_membership->seminar_id, 'dozent')
                    );
                    sort($lecturer_names);
                    $event_title = studip_interpolate(
                        '%{course_name} (%{lecturer_names})',
                        [
                            'course_name'    => $cycle_date->course->getFullName(),
                            'lecturer_names' => implode(', ', $lecturer_names)
                        ]
                    );
                } elseif ($schedule_course) {
                    $event_classes[] = 'marked-course';
                    $event_title = studip_interpolate(
                        _('%{course_name} (vorgemerkt)'),
                        ['course_name' => $cycle_date->course->getFullName()]
                    );
                }
                // Add the room, if available:
                $room_name = $cycle_date->getMostBookedRoom()?->getFullName()
                          ?? $cycle_date->getMostUsedFreetextRoomName();
                if ($room_name) {
                    $event_title .= "\n" . $room_name;
                }
                if ($cycle_date->description) {
                    $event_title .= sprintf(' (%s)', $cycle_date->description);
                }

                $event_icon = 'seminar';
                if ($schedule_course && !$course_membership) {
                    $event_icon = 'tag';
                } elseif ($show_hidden && $is_hidden) {
                    $event_icon = 'visibility-invisible';
                    $event_classes[] = 'hidden-course';
                }

                $event = new \Studip\Calendar\EventData(
                    $fake_begin,
                    $fake_end,
                    $event_title,
                    $event_classes,
                    '',
                    '',
                    false,
                    'SeminarCycleDate',
                    $cycle_date->id,
                    '',
                    '',
                    'course',
                    $cycle_date->seminar_id,
                    [
                        'show' => $this->url_for('calendar/schedule/course_info/' . $cycle_date->id)
                    ],
                    [],
                    Icon::create($event_icon ?: '', Icon::ROLE_INFO)->asImagePath()
                );

                $result[] = $event->toFullcalendarEvent();
            }
        }

        //Add all schedule entries to the result set:
        $weekly_dates = ScheduleEntry::findByUser_id($GLOBALS['user']->id);
        foreach ($weekly_dates as $date) {
            $event_data = $date->toEventData($GLOBALS['user']->id);
            $result[] = $event_data->toFullcalendarEvent();
        }

        $this->render_json($result);
    }

    /**
     * Handles moving an entry in the calendar.
     *
     * @param string $entry_id
     *
     * @return void
     */
    public function move_entry_action(string $entry_id)
    {
        if (!$entry_id) {
            $this->response->set_status(400, 'No entry-ID provided.');
            $this->render_nothing();
            return;
        }
        $entry = ScheduleEntry::find($entry_id);
        if (!$entry) {
            $this->response->set_status(404, 'Entry not found.');
            $this->render_nothing();
            return;
        }
        //Check if the current user owns this entry:
        if ($entry->user_id !== $GLOBALS['user']->id) {
            throw new AccessDeniedException();
        }

        $start = Request::getDateTime('start', DateTimeInterface::RFC3339_EXTENDED);
        $end   = Request::getDateTime('end', DateTimeInterface::RFC3339_EXTENDED);

        if (!$start || !$end) {
            $this->response->set_status(400, 'Invalid date format.');
            $this->render_nothing();
            return;
        }

        $start->setTimezone(new DateTimeZone('Europe/Berlin'));
        $end->setTimezone(new DateTimeZone('Europe/Berlin'));

        $entry->start_time = $start->format('Hi');
        $entry->end_time   = $end->format('Hi');
        $entry->dow        = $start->format('N');
        if (!$entry->store()) {
            $this->response->set_status(500, 'Cannot store entry.');
            $this->render_nothing();
            return;
        }

        $entry_event = $entry->toEventData($GLOBALS['user']->id);
        $this->render_json($entry_event->toFullcalendarEvent());
    }

    /**
     * This action handles adding and editing schedule entries.
     *
     * @param string $entry_id The ID of the entry to be modified. In case the ID is set to "add", a new entry
     *     will be created. In all other cases, an existing entry will be loaded.
     */
    public function entry_action(string $entry_id)
    {
        $this->entry = null;
        if ($entry_id === 'add') {
            //Add mode
            $this->entry = new ScheduleEntry();
            $this->entry->user_id = $GLOBALS['user']->id;
            if (!Request::submitted('save')) {
                //Provide good default values:
                $this->entry->colour_id = 1;
                if (Request::submitted('start')) {
                    //Fullcalendar provides date and time in RFC3339_EXTENDED format.
                    $start = Request::getDateTime('start', \DateTimeInterface::RFC3339_EXTENDED);
                    $end   = Request::getDateTime('end', \DateTimeInterface::RFC3339_EXTENDED);
                    if ($start) {
                        //Correct the timezone first before setting start and end time.
                        $local_datetime = new DateTime();
                        $start->setTimezone($local_datetime->getTimezone());
                        $this->entry->dow = $start->format('N');
                        $this->entry->start_time = $start->format('Hi');
                        if ($end) {
                            $end->setTimezone($local_datetime->getTimezone());
                            $this->entry->end_time = $end->format('Hi');
                        } else {
                            $end = clone $start;
                            $end = $end->add(new DateInterval('PT1H'));
                            $this->entry->end_time = $end->format('Hi');
                        }
                    }
                } else {
                    $begin = time() + 3600;
                    $end   = $begin + 3600;
                    $this->entry->dow = intval(date('N', $begin));
                    $this->entry->setFormattedStart(date('H:00', $begin));
                    $this->entry->setFormattedEnd(date('H:00', $end));
                }
            }
            PageLayout::setTitle(_('Neuer Termin'));
        } else {
            //Edit mode
            $this->entry = ScheduleEntry::find($entry_id);
            if (!$this->entry) {
                PageLayout::postError(_('Der Termin wurde nicht gefunden.'));
            }
            if (!$this->entry->isWritable($GLOBALS['user']->id)) {
                throw new AccessDeniedException(_('Sie dürfen diesen Termin nicht bearbeiten!'));
            }
            PageLayout::setTitle($this->entry->toString());
        }

        if (Request::submitted('save')) {
            CSRFProtection::verifyUnsafeRequest();
            $this->saveEntry($entry_id);
        } elseif (Request::submitted('delete')) {
            CSRFProtection::verifyUnsafeRequest();
            $this->deleteEntry();
        }
    }

    /**
     * Handles storing a schedule entry.
     */
    public function save_entry_action(string $entry_id)
    {
        $this->entry = null;
        if ($entry_id === 'add') {
            //Add mode
            $this->entry = new ScheduleEntry();
            $this->entry->user_id = $GLOBALS['user']->id;
            PageLayout::setTitle(_('Neuer Termin'));
        } else {
            //Edit mode
            $this->entry = ScheduleEntry::find($entry_id);
            if (!$this->entry) {
                PageLayout::postError(_('Der Termin wurde nicht gefunden.'));
            }
            if (!$this->entry->isWritable($GLOBALS['user']->id)) {
                throw new AccessDeniedException(_('Sie dürfen diesen Termin nicht bearbeiten!'));
            }
            PageLayout::setTitle($this->entry->toString());
        }

        $this->entry->dow = Request::int('dow', date('N'));
        $this->entry->setFormattedStart(Request::get('start'));
        $this->entry->setFormattedEnd(Request::get('end'));
        $this->entry->colour_id = Request::get('colour_id') ?? '';
        $this->entry->label   = Request::get('label', '');
        $this->entry->content = Request::get('content', '');

        if ($this->entry->start_time >= $this->entry->end_time) {
            PageLayout::postError(_('Der Startzeitpunkt darf nicht nach dem Endzeitpunkt liegen!'));
            $this->redirect('calendar/schedule/entry/' . $entry_id);
            return;
        }

        if ($this->entry->store() !== false) {
            if ($entry_id === 'add') {
                PageLayout::postSuccess(_('Der Termin wurde hinzugefügt.'));
            } else {
                PageLayout::postSuccess(_('Der Termin wurde bearbeitet.'));
            }
            if (Request::isDialog()) {
                $this->response->add_header('X-Dialog-Close', '1');
            } else {
                $this->redirect('calendar/schedule/index');
            }
        } else {
            if ($entry_id === 'add') {
                PageLayout::postError(_('Der Termin konnte nicht hinzugefügt werden.'));
            } else {
                PageLayout::postError(_('Der Termin konnte nicht bearbeitet werden.'));
            }
            $this->redirect('calendar/schedule/entry/' . $entry_id);
        }
        $this->render_nothing();
    }

    /**
     * Handles deleting a schedule entry.
     */
    public function delete_entry_action(string $entry_id)
    {
        CSRFProtection::verifyUnsafeRequest();
        $this->entry = ScheduleEntry::find($entry_id);
        if (!$this->entry) {
            PageLayout::postError(_('Der Termin wurde nicht gefunden.'));
        }
        if (!$this->entry->isWritable($GLOBALS['user']->id)) {
            throw new AccessDeniedException(_('Sie dürfen diesen Termin nicht bearbeiten!'));
        }
        if ($this->entry->delete()) {
            PageLayout::postSuccess(_('Der Termin wurde gelöscht'));
        } else {
            PageLayout::postError(_('Der Termin konnte nicht gelöscht werden.'));
        }
        if (Request::isDialog()) {
            $this->response->add_header('X-Dialog-Close', '1');
        } else {
            $this->redirect('calendar/schedule/index');
        }
        $this->render_nothing();
    }

    /**
     * Displays information about a course in the schedule.
     *
     * @param string $cycle_date_id The ID of the cycle date of the course.
     */
    public function course_info_action(string $cycle_date_id)
    {
        $this->cycle_date = SeminarCycleDate::find($cycle_date_id);
        if (!$this->cycle_date) {
            PageLayout::postError(_('Der Veranstaltungstermin wurde nicht gefunden.'));
            return;
        }
        $this->course = $this->cycle_date->course ?? null;
        if (!$this->course) {
            PageLayout::postError(_('Die Veranstaltung wurde nicht gefunden.'));
            return;
        }
        $this->membership = CourseMember::findOneBySQL(
            '`seminar_id` = :course_id AND `user_id` = :user_id',
            [
                'course_id' => $this->course->id,
                'user_id'   => $GLOBALS['user']->id
            ]
        );
        $this->schedule_course_entry = ScheduleCourseDate::findOneBySQL(
            '`metadate_id` = :cycle_date_id AND `user_id` = :user_id',
            [
                'cycle_date_id' => $this->cycle_date->id,
                'user_id'       => $GLOBALS['user']->id
            ]
        );

        PageLayout::setTitle($this->course->getFullName());
    }

    /**
     * Hides a course in the schedule.
     *
     * @param string $cycle_date_id The ID of the cycle date to hide.
     */
    public function hide_course_action(string $cycle_date_id)
    {
        CSRFProtection::verifyUnsafeRequest();
        $success = false;

        $cycle_date = SeminarCycleDate::find($cycle_date_id);
        if ($cycle_date) {
            $this->membership = CourseMember::findOneBySQL(
                '`seminar_id` = :course_id AND `user_id` = :user_id',
                [
                    'course_id' => $cycle_date->seminar_id,
                    'user_id'   => $GLOBALS['user']->id
                ]
            );

            //Hide the cycle date.
            if ($this->membership) {
                //Hide the cycle date in the schedule by creating a new schedule course entry
                //with the visibility set to 0:
                $entry = ScheduleCourseDate::findOneBySQL(
                    '`user_id` = :user_id AND `metadate_id` = :cycle_date_id',
                    ['user_id' => $GLOBALS['user']->id, 'cycle_date_id' => $cycle_date->id]
                );
                if (!$entry) {
                    $entry              = new ScheduleCourseDate();
                    $entry->user_id     = $GLOBALS['user']->id;
                    $entry->course_id   = $cycle_date->seminar_id;
                    $entry->metadate_id = $cycle_date->id;
                }
                $entry->visible = false;
                $success = $entry->store() !== false;
            } else {
                //Remove the entry of the marked cycle date from the schedule.
                $success = ScheduleCourseDate::deleteBySQL(
                        '`user_id` = :user_id AND `metadate_id` = :cycle_date_id',
                        ['user_id' => $GLOBALS['user']->id, 'cycle_date_id' => $cycle_date->id]
                    ) > 0;
                if (!$success) {
                    //Variant 2: The whole course has been added to the schedule via the "mark course in schedule"
                    //action on the course details page. In that case, only one schedule course date exists for
                    //the whole course instead of having one schedule course date for each regular date.
                    $success = ScheduleCourseDate::deleteBySQL(
                        '`user_id` = :user_id AND `course_id` = :course_id',
                        ['user_id' => $GLOBALS['user']->id, 'course_id' => $cycle_date->seminar_id]
                    ) > 0;
                }
            }
        }
        if ($success) {
            if (Request::isDialog()) {
                $this->response->add_header('X-Dialog-Close', '1');
            } else {
                $this->redirect('calendar/schedule/index');
            }
        }
        $this->render_nothing();
    }

    /**
     * Makes a hidden course visible again in the schedule.
     *
     * @param string $cycle_date_id The ID of the cycle date of the course.
     */
    public function show_course_action(string $cycle_date_id)
    {
        CSRFProtection::verifyUnsafeRequest();
        $success = false;

        $cycle_date = SeminarCycleDate::find($cycle_date_id);
        if ($cycle_date) {
            //Make a hidden cycle date visible again.
            $entry = ScheduleCourseDate::findOneBySQL(
                '`user_id` = :user_id AND `metadate_id` = :cycle_date_id',
                ['user_id' => $GLOBALS['user']->id, 'cycle_date_id' => $cycle_date->id]
            );
            if ($entry) {
                $entry->visible = true;
                $success = $entry->store() !== false;
            } else {
                $success = true;
            }
            //In case no entry exists, the cycle date is not hidden since an entry in schedule_courses
            //must exist with its visible set to zero to make a cycle date disappear from the schedule.
        }
        if ($success) {
            if (Request::isDialog()) {
                $this->response->add_header('X-Dialog-Close', '1');
            } else {
                $this->redirect('calendar/schedule/index');
            }
        }
        $this->render_nothing();
    }

    /**
     * Saves the data that are specific to displaying a course in the schedule.
     * Currently, this means saving only the colour of the course.
     *
     * @param string $course_id The ID of the course.
     */
    public function save_course_info_action(string $course_id)
    {
        CSRFProtection::verifyUnsafeRequest();
        $success = false;

        $course = Course::find($course_id);
        if ($course) {
            $this->membership = CourseMember::findOneBySQL(
                '`seminar_id` = :course_id AND `user_id` = :user_id',
                [
                    'course_id' => $course->id,
                    'user_id' => $GLOBALS['user']->id
                ]
            );
            if (!$this->membership) {
                throw new AccessDeniedException();
            }
            //Save the selected group.
            $selected_groups = Request::getArray('gruppe');
            if (array_key_exists($course->id, $selected_groups)) {
                $this->membership->gruppe = $selected_groups[$course->id] ?? '0';
            }
            $success = $this->membership->store() !== false;
        }
        if ($success) {
            PageLayout::postSuccess(_('Die Farbe der Veranstaltung wurde geändert.'));
        } else {
            PageLayout::postError(_('Die Farbe der Veranstaltung konnte nicht geändert werden.'));
        }
        if ($success) {
            if (Request::isDialog()) {
                $this->response->add_header('X-Dialog-Close', '1');
            } else {
                $this->redirect('calendar/schedule/index');
            }
        }
        $this->render_nothing();
    }

    public function mark_course_action(string $course_id)
    {
        $course = Course::find($course_id);
        if ($course->isStudygroup()) {
            throw new AccessDeniedException();
        }
        $entry = ScheduleCourseDate::findOneBySQL(
            '`course_id` = :course_id AND `user_id` = :user_id',
            [
                'course_id' => $course_id,
                'user_id'   => $GLOBALS['user']->id
            ]
        );
        if ($entry) {
            PageLayout::postInfo(_('Die Veranstaltung wurde bereits zum Stundenplan hinzugefügt.'));
        } else {
            $entry = new ScheduleCourseDate();
            $entry->course_id   = $course->id;
            $entry->user_id     = $GLOBALS['user']->id;
            $entry->metadate_id = '';
            $entry->visible     = true;
            if ($entry->store() !== false) {
                PageLayout::postSuccess(_('Die Veranstaltung wurde zum Stundenplan hinzugefügt.'));
            } else {
                PageLayout::postError(_('Die Veranstaltung konnte nicht zum Stundenplan hinzugefügt werden.'));
            }
        }
        $this->redirect('calendar/schedule/index');
    }

    /**
     * Shows the settings dialog for the schedule.
     */
    public function settings_action()
    {
        $user_config = UserConfig::get(User::findCurrent()->id);
        $this->schedule_settings = $user_config->getValue('SCHEDULE_SETTINGS');

        //Provide good defaults:
        $default_config = [
            'start_time'   => '08:00',
            'end_time'     => '20:00',
            'visible_days' => [1, 2, 3, 4, 5]
        ];
        if (
            empty($this->schedule_settings['start_time'])
            && empty($this->schedule_settings['end_time'])
            && empty($this->schedule_settings['visible_days'])
        ) {
            //Use the defaults:
            $this->schedule_settings = $default_config;
        }
    }

    /**
     * Saves the schedule settings from the settings dialog.
     */
    public function save_settings_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $start_time       = Request::get('start_time', '08:00');
        $end_time         = Request::get('end_time', '20:00');
        $visible_days    = Request::intArray('visible_days');
        if ($start_time >= $end_time) {
            PageLayout::postError(_('Die Startuhrzeit muss vor der Enduhrzeit liegen.'));
            $this->redirect('calendar/schedule/settings');
            return;
        }
        if (empty($visible_days)) {
            PageLayout::postError(_('Es wurde kein Wochentag ausgewählt.'));
            $this->redirect('calendar/schedule/settings');
            return;
        }

        //Update the settings:
        $schedule_settings = UserConfig::get(User::findCurrent()->id)->getValue('SCHEDULE_SETTINGS');
        $schedule_settings['start_time']   = $start_time;
        $schedule_settings['end_time']     = $end_time;
        $schedule_settings['visible_days'] = $visible_days;
        UserConfig::get(User::findCurrent()->id)->store('SCHEDULE_SETTINGS', $schedule_settings);

        PageLayout::postSuccess(_('Die Einstellungen wurden gespeichert.'));
        if (Request::isDialog()) {
            $this->response->add_header('X-Dialog-Close', '1');
        } else {
            $this->redirect('calendar/schedule/index');
        }
        $this->render_nothing();
    }
}
