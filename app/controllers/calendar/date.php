<?php

class Calendar_DateController extends AuthenticatedController
{
    protected function getCategoryOptions()
    {
        if (empty($GLOBALS['PERS_TERMIN_KAT'])) {
            return [];
        }
        $options = [];
        foreach ($GLOBALS['PERS_TERMIN_KAT'] as $key => $data) {
            $options[$key] = $data['name'];
        }
        return $options;
    }

    protected function getCalendarOwner($range_and_id)
    {
        $range = '';
        $range_id = '';
        $range_and_id = explode('_', $range_and_id ?? []);
        if (!empty($range_and_id[1])) {
            $range = $range_and_id[0];
            $range_id = $range_and_id[1];
        }
        if (!$range) {
            $range_id = Request::option('user_id', $GLOBALS['user']->id);
            $range = 'user';
        }

        $owner = null;
        if (!$range_id) {
            //Assume a user calendar. $range contains the user-ID.
            $owner = User::getCalendarOwner($range);
        } else {
            if ($range === 'user') {
                $owner = User::getCalendarOwner($range_id);
            } elseif ($range === 'course') {
                $owner = Course::getCalendarOwner($range_id);
            }
        }

        if (!$owner || !$owner->isCalendarReadable()) {
            throw new AccessDeniedException(_('Sie dürfen diesen Kalender nicht sehen!'));
        }
        return $owner;
    }

    /**
     * A helper method to determine whether the current user may write the date.
     *
     * @return Studip\Calendar\Owner[] The owners in which the current user may add a date.
     */
    protected function getCalendarOwnersWithWriteAccess(?CalendarDate $date, ?Studip\Calendar\Owner $owner) : array
    {
        $result = [];
        if ($owner instanceof Course) {
            //For course calendars, only the course can be the owner.
            $result[$owner->id] = $owner;
            return $result;
        }
        if ($date) {
            foreach ($date->calendars as $calendar) {
                if ($calendar->user) {
                    $result[$calendar->user->id] = $calendar->user;
                } elseif ($calendar->course) {
                    $result[$calendar->course->id] = $calendar->course;
                }
            }
        } else {
            if ($group_id = Request::get('group_id')) {
                $group = ContactGroup::find($group_id);
                if ($group) {
                    foreach ($group->items as $item) {
                        if ($item->user && $item->user->isCalendarWritable()) {
                            $result[$item->user_id] = $item->user;
                        }
                    }
                }
            } elseif ($user_id = Request::get('user_id', $GLOBALS['user']->id)) {
                $user = User::find($user_id);
                if ($user && $user->isCalendarWritable()) {
                    $result[$user->id] = $user;
                }
            }
            if ($other_calendar_ids = Request::getArray('other_calendar_ids')) {
                foreach ($other_calendar_ids as $other_calendar_id) {
                    $user = User::find($other_calendar_id);
                    if ($user && $user->isCalendarWritable()) {
                        $result[$user->id] = $user;
                    }
                }
            }
        }
        return $result;
    }

    public function index_action($date_id)
    {
        $this->date = CalendarDate::find($date_id);
        if (!$this->date) {
            PageLayout::postError(_('Der angegebene Termin wurde nicht gefunden.'));
            return;
        }
        if (!$this->date->isVisible($GLOBALS['user']->id)) {
            throw new AccessDeniedException();
        }
        PageLayout::setTitle(
            sprintf(
                _('%1$s (am %2$s von %3$s bis %4$s Uhr)'),
                $this->date->title,
                date('d.m.Y', $this->date->begin),
                date('H:i', $this->date->begin),
                date('H:i', $this->date->end)
            )
        );
        $this->selected_date = '';
        if ($this->date->repetition_type) {
            $this->selected_date = Request::get('selected_date');
        }
        $this->user_calendar_assignments = CalendarDateAssignment::findBySql(
            "INNER JOIN `auth_user_md5`
            ON `calendar_date_assignments`.`range_id` = `auth_user_md5`.`user_id`
            WHERE
            `calendar_date_id` = :calendar_date_id",
            ['calendar_date_id' => $this->date->id]
        );
        $this->participation_message = null;
        $this->user_participation_status = '';
        $this->all_assignments_writable = false;
        $this->is_group_date = count($this->user_calendar_assignments) > 1;

        if ($this->user_calendar_assignments) {
            $writable_assignment_c = 0;
            $more_than_one_assignment = count($this->user_calendar_assignments) > 1;
            //Find the calendar assignment of the user and set the participation message
            //according to the participation status.
            foreach ($this->user_calendar_assignments as $index => $assignment) {
                if ($assignment->range_id === $GLOBALS['user']->id && $this->is_group_date) {
                    $this->user_participation_status = $assignment->participation;
                    if ($assignment->participation === 'ACCEPTED') {
                        $this->participation_message = MessageBox::info(_('Sie nehmen am Termin teil.'));
                    } elseif ($assignment->participation === 'DECLINED') {
                        $this->participation_message = MessageBox::info(_('Sie nehmen nicht am Termin teil.'));
                    } elseif ($assignment->participation === 'ACKNOWLEDGED') {
                        $this->participation_message = MessageBox::info(_('Sie haben den Termin zur Kenntnis genommen.'));
                    } else {
                        $this->participation_message = MessageBox::info(_('Sie haben keine Angaben zur Teilnahme gemacht.'));
                    }
                    if ($more_than_one_assignment) {
                        $writable_assignment_c++;
                    } else {
                        //We don't need the users own assignment in the list of assignments
                        //when there is only one assignment to the users own calendar.
                        unset($this->user_calendar_assignments[$index]);

                    }
                } else {
                    if ($assignment->isWritable($GLOBALS['user']->id)) {
                        $writable_assignment_c++;
                    }
                }
            }

            $this->all_assignments_writable = $writable_assignment_c === count($this->user_calendar_assignments);

            //Order all calendar assignments by type and name:
            uasort($this->user_calendar_assignments, function ($a, $b) {
                $compare_name = ($a->course instanceof Course && $b->course instanceof Course)
                    || ($a->user instanceof User && $b->user instanceof User);
                if ($compare_name) {
                    $a_name = '';
                    if ($a->course instanceof Course) {
                        $a_name = $a->course->getFullName();
                    } elseif ($a->user instanceof User) {
                        $a_name = $a->user->getFullName();
                    }
                    $b_name = '';
                    if ($b->course instanceof Course) {
                        $b_name = $b->course->getFullName();
                    } elseif ($b->user instanceof User) {
                        $b_name = $b->user->getFullName();
                    }
                    if ($a_name < $b_name) {
                        return -1;
                    } elseif ($a_name > $b_name) {
                        return 1;
                    } else {
                        return 0;
                    }
                } else {
                    //Compare types.
                    $a_is_course = $a->course instanceof Course;
                    if ($a_is_course) {
                        return -1;
                    } else {
                        //$b is a course:
                        return 1;
                    }
                }
            });
        } else {
            //Check for other calendar assignments (course calendar):
            $writable_assignment_c = 0;
            $other_assignment_c = 0;
            foreach ($this->date->calendars as $assignment) {
                if (Course::exists($assignment->range_id)) {
                    //It is a course assignment:
                    $other_assignment_c++;
                    if ($assignment->isWritable($GLOBALS['user']->id)) {
                        $writable_assignment_c++;
                    }
                }
            }
            $this->all_assignments_writable = $writable_assignment_c == $other_assignment_c;
        }
    }

    public function add_action($range_and_id = '')
    {
        PageLayout::setTitle(_('Termin anlegen'));

        $owner = $this->getCalendarOwner($range_and_id);

        $this->date = new CalendarDate();
        if (Request::submitted('start') && Request::submitted('end')) {
            $tz_date = new DateTime();
            $start = Request::getDateTime('start', DateTimeInterface::RFC3339_EXTENDED);
            $end   = Request::getDateTime('end', DateTimeInterface::RFC3339_EXTENDED);
            //The date might be in UTC instead of the default time zone, so it has to be converted:
            $start->setTimezone($tz_date->getTimezone());
            $end->setTimezone($tz_date->getTimezone());
            if ($start === false || $end === false) {
                throw new InvalidArgumentException(_('Ungültiges Datumsformat'));
            }
            if (Request::get('all_day')) {
                $start->setTime(0, 0, 0);
                //Special case for the end timestamp:
                //In Fullcalendar, all-day events end on the next day while in Stud.IP
                //they end on 23:59:59 on the same day.
                $end->setTime(0, 0, 0);
                $end = $end->sub(new DateInterval('PT1S'));
            }
            $this->date->begin = $start->getTimestamp();
            $this->date->end   = $end->getTimestamp();
            $this->date->repetition_end = $this->date->end;
        } elseif (Request::submitted('begin_str') && Request::submitted('end_str')) {
            //Assume the textual format d.m.Y H:i:
            $begin = Request::getDateTime('begin_str', 'd.m.Y H:i');
            $end   = Request::getDateTime('end_str', 'd.m.Y H:i');
            $this->date->begin = $begin->getTimestamp();
            $this->date->end   = $end->getTimestamp();
        } else {
            $time = new DateTime();
            if (Request::submitted('timestamp')) {
                $time->setTimestamp(Request::int('timestamp'));
            } elseif (Request::submitted('defaultDate')) {
                $date_parts = explode('-', Request::get('defaultDate'));
                if (count($date_parts) === 3) {
                    $time->setDate($date_parts[0], $date_parts[1], $date_parts[2]);
                }
            }
            $time = $time->add(new DateInterval('PT1H'));
            $time->setTime(intval($time->format('H')), 0, 0);
            $this->date->begin = $time->getTimestamp();
            $time = $time->add(new DateInterval('PT30M'));
            $this->date->end = $time->getTimestamp();
            $this->date->repetition_end = $this->date->end;
        }
        if ($owner instanceof Course) {
            $this->form_post_link = $this->link_for('calendar/date/add/course_' . $owner->id);
        } else {
            //Personal calendar or group calendar
            $this->form_post_link = $this->link_for('calendar/date/add');
        }
        $this->handleForm('add', $owner);
    }

    public function edit_action($date_id)
    {
        PageLayout::setTitle(_('Termin bearbeiten'));

        $this->date = CalendarDate::find($date_id);
        if (!$this->date) {
            throw new Exception(_('Der Termin wurde nicht gefunden.'));
        }
        //Set the repetition end date to the end of the date in case it isn't set:
        if (!$this->date->repetition_end) {
            $this->date->repetition_end = $this->date->end;
        }

        $this->form_post_link = $this->link_for('calendar/date/edit/' . $this->date->id);
        $this->handleForm();
    }

    protected function handleForm($mode = 'edit', $owner = null)
    {
        $this->form_errors = [];
        $this->calendar_assignment_items = [];

        $this->writable_calendars = $this->getCalendarOwnersWithWriteAccess($mode === 'edit' ? $this->date : null, $owner);
        if (!$this->writable_calendars) {
            throw new AccessDeniedException();
        }
        $this->user_id  = Request::get('user_id', $owner->id ?? '');
        $this->group_id = '';
        if (!$owner) {
            $this->group_id = Request::get('group_id');
        }
        $this->owner_id = $owner ? $owner->id : '';

        $this->category_options = $this->getCategoryOptions();
        $this->exceptions = [];

        if (!$owner || !($owner instanceof Course)) {
            $this->user_quick_search_type = null;
            $this->multi_person_search = null;
            if (Config::get()->CALENDAR_GROUP_ENABLE) {
                if (Config::get()->CALENDAR_GRANT_ALL_INSERT) {
                    $this->user_quick_search_type = new StandardSearch('user_id');
                } else {
                    //Only get those users where the current user has
                    //write access to the calendar.
                    $this->user_quick_search_type = new SQLSearch(
                            "SELECT
                            `auth_user_md5`.`user_id`, "
                            . $GLOBALS['_fullname_sql']['full'] . " AS fullname
                            FROM `auth_user_md5`
                            INNER JOIN `contact`
                            ON `auth_user_md5`.`user_id` = `contact`.`owner_id`
                            INNER JOIN `user_info`
                            ON `user_info`.`user_id` = `auth_user_md5`.`user_id`
                            WHERE
                            `auth_user_md5`.`user_id` <> " . DBManager::get()->quote($GLOBALS['user']->id) . "
                            AND `contact`.`user_id` = " . DBManager::get()->quote($GLOBALS['user']->id) . "
                            AND `contact`.`calendar_permissions` = 'WRITE'
                            AND (
                                `auth_user_md5`.`username` LIKE :input
                                OR CONCAT(`auth_user_md5`.`Vorname`, ' ', `auth_user_md5`.`Nachname`) LIKE :input
                                OR CONCAT(`auth_user_md5`.`Nachname`, ' ', `auth_user_md5`.`Vorname`) LIKE :input
                                OR `auth_user_md5`.`Nachname` LIKE :input
                                OR " . $GLOBALS['_fullname_sql']['full'] . " LIKE :input
                            )
                            GROUP BY `auth_user_md5`.`user_id`
                            ORDER BY fullname ASC",
                            _('Person suchen'),
                            'user_id'
                        );
                }
            }
        }

        if ($this->date->isNew()) {
            if (!($owner instanceof Course)) {
                //Assign the date to the calendar of the owner by default:
                $this->calendar_assignment_items[] = [
                    'value'     => $owner->id,
                    'name'      => $owner->getFullName(),
                    'deletable' => true
                ];
            }
        } else {
            $exceptions = CalendarDateException::findBySql(
                'calendar_date_id = :date_id ORDER BY `date` ASC',
                ['date_id' => $this->date->id]
            );
            foreach ($exceptions as $exception) {
                $this->exceptions[] = $exception->date;
            }

            $calendars_assignments = CalendarDateAssignment::findByCalendar_date_id($this->date->id);
            foreach ($calendars_assignments as $assignment) {
                $range_avatar = $assignment->getRangeAvatar();
                $this->calendar_assignment_items[] = [
                    'value'     => $assignment->range_id,
                    'name'      => $assignment->getRangeName(),
                    'deletable' => true
                ];
            }
        }

        $this->all_day_event = false;
        if ($mode === 'add' && Request::bool('all_day')) {
            $this->all_day_event = true;
            //Check for a fullcalendar all-day event ending. In that case, remove one second to have an all-day event
            //that abides to the Stud.IP rules.
            $end = new DateTime();
            $end->setTimestamp($this->date->end);
            if ($end->format('His') === '000000') {
                $end = $end->sub(new DateInterval('PT1S'));
            }
            $this->date->end = $end->getTimestamp();
        } else {
            $begin = new DateTime();
            $begin->setTimestamp(intval($this->date->begin));
            $end = new DateTime();
            $end->setTimestamp(intval($this->date->end));
            $duration = $end->diff($begin);
            if ($duration->h === 23 && $duration->i === 59 && $duration->s === 59 && $begin->format('H:i:s') === '00:00:00') {
                //The event starts at midnight and ends on 23:59:59. It is an all-day event.
                $this->all_day_event = true;
            }
        }

        if (!Request::isPost()) {
            return;
        }
        if (Request::submitted('save')) {
            CSRFProtection::verifyUnsafeRequest();

            if ($this->date->isNew()) {
                $this->date->author_id = $GLOBALS['user']->id;
            }
            $this->date->editor_id = $GLOBALS['user']->id;

            $begin = Request::getDateTime('begin_str', 'd.m.Y H:i');
            $end = Request::getDateTime('end_str', 'd.m.Y H:i');
            if (Request::get('all_day') === '1') {
                $this->all_day_event = true;
                $begin->setTime(0,0,0);
                $end->setTime(23,59,59);
            }
            $this->date->begin = $begin->getTimestamp();
            $this->date->end = $end->getTimestamp();
            if (!$this->date->begin) {
                $this->form_errors[_('Beginn')] = _('Bitte geben Sie einen Startzeitpunkt ein.');
            }
            if (!$this->date->end && !$this->all_day_event) {
                $this->form_errors[_('Ende')] = _('Bitte geben Sie einen Endzeitpunkt ein.');
            }
            if ($this->date->begin && $this->date->end && ($this->date->end < $this->date->begin)) {
                $this->form_errors[_('Ende')] = _('Der Startzeitpunkt darf nicht nach dem Endzeitpunkt liegen!');
            }

            $this->date->title = Request::get('title');
            if (!$this->date->title) {
                $this->form_errors[_('Titel')] = _('Bitte geben Sie einen Titel ein.');
            }

            $this->date->access = Request::get('access');
            if (!in_array($this->date->access, ['PUBLIC', 'CONFIDENTIAL', 'PRIVATE'])) {
                $this->form_errors[_('Zugriff')] = _('Bitte wählen Sie einen Zugriffstyp aus.');
            }

            $this->date->description = Request::get('description');

            $this->date->category = Request::get('category');
            if (!in_array($this->date->category, array_keys($this->category_options))) {
                $this->form_errors[_('Kategorie')] = _('Bitte wählen Sie eine gültige Kategorie aus.');
            }

            $this->date->user_category = Request::get('user_category');

            $this->date->location = Request::get('location');

            //Store the repetition information:

            $this->date->clearRepetitionFields();
            $this->date->repetition_type = Request::get('repetition_type', CalendarDate::REPETITION_SINGLE);
            if (
                !in_array($this->date->repetition_type, [
                    CalendarDate::REPETITION_SINGLE,
                    CalendarDate::REPETITION_DAILY,
                    CalendarDate::REPETITION_WEEKLY,
                    CalendarDate::REPETITION_MONTHLY,
                    CalendarDate::REPETITION_YEARLY,
                    'WORKDAYS',
                ])
            ) {
                $this->form_errors[_('Wiederholung')] = _('Bitte wählen Sie ein gültiges Wiederholungsintervall aus.');
            } elseif ($this->date->repetition_type !== CalendarDate::REPETITION_SINGLE) {
                $this->date->interval = '';
                if ($this->date->repetition_type !== 'WORKDAYS') {
                    $this->date->interval = Request::int('repetition_interval');
                    if ($this->date->interval < 1) {
                        $this->form_errors[_('Wiederholung')] = _('Bitte wählen Sie ein gültiges Wiederholungsintervall aus.');
                    }
                }

                if ($this->date->repetition_type === CalendarDate::REPETITION_WEEKLY) {
                    $dow = array_unique(Request::getArray('repetition_dow'));
                    foreach ($dow as $day) {
                        if ($day < 1 || $day > 7) {
                            $this->form_errors[_('Wiederholung an bestimmtem Wochentag')] = _('Bitte wählen Sie einen Wochentag zwischen Montag und Sonntag aus.');
                        }
                    }
                    $this->date->days = implode('', $dow);
                } elseif ($this->date->repetition_type === 'WORKDAYS') {
                    //Special case: The "WORKDAYS" repetition type is a shorthand type
                    //for a weekly repetition from Monday to Friday.
                    $this->date->repetition_type = CalendarDate::REPETITION_WEEKLY;
                    $this->date->days = '12345';
                    $this->date->interval = 1;
                } elseif ($this->date->repetition_type === CalendarDate::REPETITION_MONTHLY) {
                    $month_type = Request::get('repetition_month_type');
                    if ($month_type === 'dom') {
                        $this->date->offset = Request::int('repetition_dom');
                    } elseif ($month_type === 'dow') {
                        $this->date->days = Request::get('repetition_dow');
                        $this->date->offset = Request::int('repetition_dow_week');
                    }
                } elseif ($this->date->repetition_type === CalendarDate::REPETITION_YEARLY) {
                    $month = Request::int('repetition_month');
                    if ($month < 1 || $month > 12) {
                        $this->form_errors[_('Monat')] = _('Bitte wählen Sie einen Monat zwischen Januar und Dezember aus.');
                    }
                    $this->date->month = $month;
                    $month_type = Request::get('repetition_month_type');
                    if ($month_type === 'dom') {
                        $this->date->offset = Request::int('repetition_dom');
                    } elseif ($month_type === 'dow') {
                        $this->date->days = Request::get('repetition_dow');
                        $this->date->offset = Request::int('repetition_dow_week');
                    }
                }

                $end_type = Request::get('repetition_rep_end_type');
                if ($end_type === 'end_date') {
                    $end_date = Request::getDateTime('repetition_rep_end_date', 'd.m.Y');
                    $end_date->setTime(23,59,59);
                    $this->date->repetition_end = $end_date->getTimestamp();
                } elseif ($end_type === 'end_count') {
                    $this->date->number_of_dates = Request::int('repetition_number_of_dates');
                } else {
                    //Repetition never ends:
                    $this->date->repetition_end = CalendarDate::NEVER_ENDING;
                }
            }

            $assigned_calendar_ids = Request::getArray('assigned_calendar_ids');
            if (!$assigned_calendar_ids || (count($assigned_calendar_ids) === 0)) {
                $this->form_errors[_('Teilnehmende Personen')] = _('Der Termin ist keinem Kalender zugewiesen!');
            }

            if ($this->form_errors) {
                return;
            }

            $stored = false;
            if ($this->date->isDirty()) {
                $stored = $this->date->store();
            } else {
                $stored = true;
            }
            if (!$stored) {
                PageLayout::postError(
                    _('Beim Speichern des Termins ist ein Fehler aufgetreten.')
                );
                return;
            }

            //Assign the calendar date to all writable calendars.

            //Check the assigned calendar-IDs first if they are valid:
            $valid_assigned_calendar_ids = [];
            if (($owner instanceof Course)) {
                //Set the course as calendar:
                $allowed_calendar_ids = [$owner->id];
            } elseif (Context::isCourse()) {
                //Set the course as allowed calendar:
                $allowed_calendar_ids = [Context::getId()];
            } else {
                //Assign the date to the calendars of all the selected users:
                $allowed_calendar_ids = [$GLOBALS['user']->id];
                if (Config::get()->CALENDAR_GROUP_ENABLE) {
                    $allowed_calendar_results = $this->user_quick_search_type->getResults('%%%%');
                    foreach ($allowed_calendar_results as $result) {
                        $allowed_calendar_ids[] = $result[0];
                    }
                }
            }

            foreach ($assigned_calendar_ids as $assigned_calendar_id) {
                if (Course::exists($assigned_calendar_id) || User::exists($assigned_calendar_id)) {
                    //Valid ID of an existing calendar (range-ID).
                    if (in_array($assigned_calendar_id, $allowed_calendar_ids)) {
                        //The calendar is writable.
                        $valid_assigned_calendar_ids[] = $assigned_calendar_id;
                    }
                }
            }
            if (count($valid_assigned_calendar_ids) < 1) {
                PageLayout::postError(
                    _('Die Zuweisungen des Termins zu Kalendern sind ungültig!')
                );
                return;
            }

            //Remove the date from all user calendars that aren't in the array of writable calendars.
            CalendarDateAssignment::deleteBySQL(
                '`range_id` NOT IN ( :owner_ids ) AND `calendar_date_id` = :calendar_date_id',
                ['owner_ids' => $allowed_calendar_ids, 'calendar_date_id' => $this->date->id]
            );

            //Now add the date to all selected calendars:
            foreach($valid_assigned_calendar_ids as $assigned_calendar_id) {
                $assignment = CalendarDateAssignment::findOneBySql(
                    'range_id = :assigned_calendar_id AND calendar_date_id = :calendar_date_id',
                    [
                        'assigned_calendar_id' => $assigned_calendar_id,
                        'calendar_date_id' => $this->date->id
                    ]
                );
                if (!$assignment) {
                    $assignment = new CalendarDateAssignment();
                    $assignment->range_id = $assigned_calendar_id;
                    $assignment->calendar_date_id = $this->date->id;
                    $assignment->store();
                }
            }
            //Remove the date from calendars that are not in the $valid_assigned_calendar_ids array:
            CalendarDateAssignment::deleteBySQL(
                '`range_id` NOT IN ( :assigned_calendar_ids ) AND `calendar_date_id` = :calendar_date_id',
                [
                    'assigned_calendar_ids' => $valid_assigned_calendar_ids,
                    'calendar_date_id'      => $this->date->id
                ]
            );

            //Clear all exceptions for the event and set them again:
            CalendarDateException::deleteByCalendar_date_id($this->date->id);
            $new_exceptions = Request::getArray('exceptions');
            $stored_c = 0;
            foreach ($new_exceptions as $exception) {
                $date_parts = explode('-', $exception);
                if (count($date_parts) === 3) {
                    //Should be a valid date string.
                    $e = new CalendarDateException();
                    $e->calendar_date_id = $this->date->id;
                    $e->date = $exception;
                    if ($e->store()) {
                        $stored_c++;
                    }
                }
            }
            if ($stored_c === count($new_exceptions)) {
                PageLayout::postSuccess(_('Der Termin wurde gespeichert.'));
            } else {
                PageLayout::postWarning(_('Der Termin wurde gespeichert, aber nicht mit allen Terminausfällen!'));
            }
            if (Request::submitted('selected_date')) {
                $selected_date = Request::getDateTime('selected_date');
                if ($selected_date) {
                    //Set the calendar default date to the previously selected date:
                    $_SESSION['calendar_date'] = $selected_date->format('Y-m-d');
                }
            } else {
                //Set the calendar default date to the beginning of the date:
                $_SESSION['calendar_date'] = $begin->format('Y-m-d');
            }
            $this->response->add_header('X-Dialog-Close', '1');
        }
    }

    public function move_action($date_id)
    {
        $this->date = CalendarDate::find($date_id);
        if (!$this->date) {
            throw new InvalidArgumentException(
                _('Der angegebene Termin wurde nicht gefunden.')
            );
        }
        if (!$this->date->isWritable($GLOBALS['user']->id)) {
            throw new AccessDeniedException(
                _('Sie sind nicht berechtigt, diesen Termin zu ändern.')
            );
        }

        $tz_date = new DateTime();
        $this->begin = Request::getDateTime('start', \DateTimeInterface::RFC3339_EXTENDED);
        $this->end   = Request::getDateTime('end', \DateTimeInterface::RFC3339_EXTENDED);
        if ($this->begin) {
            $this->begin->setTimezone($tz_date->getTimezone());
        }
        if (Request::get('all_day') && $this->begin) {
            if (!$this->end) {
                //Set the end to the end of the day the event starts:
                $this->end = clone $this->begin;
                $this->end = $this->end->add(new DateInterval('P1D'));
                $this->end->setTime(0,0,0);
            }
            //At this point, there should be an end date.
            //If it is on 0:00:00 in the local time zone, we need to get to
            //the last second of the day before so that the event is
            //detected as all-day event.
            $this->end->setTimezone($tz_date->getTimezone());
            if ($this->end->format('His') == 0) {
                $this->end = $this->end->sub(new DateInterval('PT1S'));
            }
        }
        if (!$this->begin || !$this->end) {
            throw new InvalidArgumentException();
        }
        $this->end->setTimezone($tz_date->getTimezone());

        //In case the moved event is a repetition event, we must know the original date from where
        //it was moved from to correctly move the whole date series:
        $original_date = Request::getDateTime('original_date');
        $real_begin = new DateTime();
        $real_begin->setTimestamp($this->date->begin);
        if (
            $original_date
            && $original_date->format('Ymd') !== $real_begin->format('Ymd')
        ) {
            //The original date is set. If it differs from the beginning, a repetition date
            //of a date series has been moved. In this case, the difference of the beginning
            //and the original date has to be calculated and the begin and end fields have
            //to be updated.
            $original_date->setTime(
                $real_begin->format('H'),
                $real_begin->format('i'),
                $real_begin->format('s')
            );
            $diff = $real_begin->diff($original_date);
            $this->begin = $this->begin->sub($diff);
            $this->end = $this->end->sub($diff);
        }

        if ($this->date->repetition_type !== CalendarDate::REPETITION_SINGLE) {
            PageLayout::setTitle(_('Verschieben eines Termins aus einer Terminserie'));
            //Show the dialog to decide what shall be done with the repetition.
            if (Request::submitted('move')) {
                CSRFProtection::verifyUnsafeRequest();
                $repetition_handling = Request::get('repetition_handling');
                $store_old_date = false;
                if ($repetition_handling === 'create_single_date') {
                    //Create a new date with the new time range and then
                    //create an exception for the old date.
                    $new_date = new CalendarDate();
                    $new_date->setData($this->date->toArray());
                    $new_date->id = $new_date->getNewId();
                    $new_date->unique_id = '';
                    $new_date->begin = $this->begin->getTimestamp();
                    $new_date->end = $this->end->getTimestamp();
                    $new_date->author_id = $GLOBALS['user']->id;
                    $new_date->editor_id = $GLOBALS['user']->id;
                    $new_date->clearRepetitionFields();
                    $new_date->store();
                    foreach ($this->date->calendars as $calendar) {
                        $new_date_calendar = new CalendarDateAssignment();
                        $new_date_calendar->calendar_date_id = $new_date->id;
                        $new_date_calendar->range_id = $calendar->range_id;
                        $new_date_calendar->store();
                    }
                    $exception = CalendarDateException::findBySQL(
                        '`calendar_date_id` = :calendar_date_id AND `date` = :date',
                        [
                            'calendar_date_id' => $this->date->id,
                            'date' => $this->begin->format('Y-m-d')
                        ]
                    );
                    if (!$exception) {
                        $exception = new CalendarDateException();
                        $exception->calendar_date_id = $this->date->id;
                        $exception->date = $this->begin->format('Y-m-d');
                        $exception->store();
                    }
                    $this->response->add_header('X-Dialog-Close', '1');
                    return;
                } elseif ($repetition_handling === 'change_all') {
                    $this->date->begin = $this->begin->getTimestamp();
                    if ($this->date->repetition_end && intval($this->date->repetition_end) != pow(2,31) - 1) {
                        //The repetition end date is set to one specific date.
                        //It must be recalculated from the end date.
                        $old_end = new DateTime();
                        $old_end->setTimestamp($this->date->end);
                        $old_repetition_end = new DateTime();
                        $old_repetition_end ->setTimestamp($this->date->repetition_end);
                        $distance = $old_end->diff($old_repetition_end);
                        $this->date->end = $this->end->getTimestamp();
                        $new_repetition_end = clone $this->end;
                        $new_repetition_end = $new_repetition_end->add($distance);
                        $this->date->repetition_end = $new_repetition_end->getTimestamp();
                    }
                    $this->date->end = $this->end->getTimestamp();

                    //Set the editor-ID:
                    $this->date->editor_id = $GLOBALS['user']->id;

                    $store_old_date = true;
                } else {
                    //Invalid choice.
                    PageLayout::postError(_('Ungültige Auswahl!'));
                    return;
                }
                if ($store_old_date) {
                    $success = false;
                    if ($this->date->isDirty()) {
                        $success = $this->date->store();
                    } else {
                        $success = true;
                    }
                    if ($success) {
                        $this->response->add_header('X-Dialog-Close', '1');
                        $this->render_nothing();
                    } else {
                        throw new Exception(_('Der Termin konnte nicht gespeichert werden.'));
                    }
                }
            }
        } else {
            //Set the new date and time directly.
            $this->date->begin = $this->begin->getTimestamp();
            $this->date->end = $this->end->getTimestamp();
            //Set the editor-ID:
            $this->date->editor_id = $GLOBALS['user']->id;

            $success = false;
            if ($this->date->isDirty()) {
                $success = $this->date->store();
            } else {
                $success = true;
            }
            if ($success) {
                $this->response->add_header('X-Dialog-Close', '1');
                $this->render_nothing();
            } else {
                throw new Exception(_('Der Termin konnte nicht gespeichert werden.'));
            }
        }
    }

    public function delete_action($date_id)
    {
        PageLayout::setTitle(_('Termin löschen'));
        $this->date = CalendarDate::find($date_id);
        if (!$this->date) {
            PageLayout::postError(
                _('Der Termin wurde nicht gefunden.')
            );
            $this->render_nothing();
        }
        if (!$this->date->isWritable($GLOBALS['user']->id)) {
            throw new AccessDeniedException(_('Sie sind nicht berechtigt, diesen Termin zu löschen.'));
        }

        $this->date_has_repetitions = $this->date->repetition_type !== CalendarDate::REPETITION_SINGLE;
        $this->selected_date = null;
        if ($this->date_has_repetitions) {
            $this->selected_date = Request::getDateTime('selected_date');
            if (!$this->selected_date) {
                $this->selected_date = new DateTime();
                $this->selected_date->setTimestamp($this->date->begin);
            }
        }
        $this->date_is_in_multiple_calendars = count($this->date->calendars) > 1;

        $this->repetition_handling  = Request::get('repetition_handling', 'create_exception');
        $this->multiple_calendar_handling = Request::get('multiple_calendar_handling', 'delete_from_mine');
        if (Request::submitted('delete')) {
            $delete_whole_date = false;
            CSRFProtection::verifyUnsafeRequest();
            if ($this->date_has_repetitions) {
                if ($this->repetition_handling === 'create_exception') {
                    $exception = new CalendarDateException();
                    $exception->calendar_date_id = $this->date->id;
                    $exception->date = $this->selected_date->format('Y-m-d');
                    if ($exception->store()) {
                        PageLayout::postSuccess(
                            sprintf(
                                _('Die Ausnahme am %s wurde der Terminserie hinzugefügt.'),
                                $this->selected_date->format('d.m.Y')
                            )
                        );
                        $this->response->add_header('X-Dialog-Close', '1');
                        $this->render_nothing();
                        return;
                    } else {
                        PageLayout::postError(
                            sprintf(
                                _('Die Ausnahme am %s konnte der Terminserie nicht hinzugefügt werden.'),
                                $this->selected_date->format('d.m.Y')
                            )
                        );
                    }
                } elseif ($this->repetition_handling === 'delete_all' && $this->multiple_calendar_handling === 'delete_all') {
                    $delete_whole_date = true;
                }
            } else {
                $delete_whole_date = !$this->date_is_in_multiple_calendars
                    || $this->multiple_calendar_handling === 'delete_all';
            }
            if ($delete_whole_date) {
                if ($this->date->delete()) {
                    if ($this->date_has_repetitions) {
                        PageLayout::postSuccess(_('Die Terminserie wurde gelöscht!'));
                    } else {
                        PageLayout::postSuccess(_('Der Termin wurde gelöscht'));
                    }
                    $this->response->add_header('X-Dialog-Close', '1');
                    $this->render_nothing();
                } else {
                    if ($this->date_has_repetitions) {
                        PageLayout::postError(_('Die Terminserie konnte nicht gelöscht werden.'));
                    } else {
                        PageLayout::postError(_('Der Termin konnte nicht gelöscht werden.'));
                    }
                }
            } elseif ($this->multiple_calendar_handling === 'delete_from_mine') {
                $result = CalendarDateAssignment::deleteBySQL(
                    '`calendar_date_id` = :calendar_date_id AND `range_id` = :current_user_id',
                    [
                        'calendar_date_id' => $this->date->id,
                        'current_user_id'  => User::findCurrent()->id
                    ]
                );
                if ($result) {
                    PageLayout::postSuccess(_('Der Termin wurde aus Ihrem Kalender gelöscht.'));
                    $this->response->add_header('X-Dialog-Close', '1');
                    $this->render_nothing();
                } else {
                    PageLayout::postError(_('Der Termin konnte nicht aus Ihrem Kalender gelöscht werden!'));
                }
            }
        }
    }

    public function participation_action($date_id)
    {
        $this->calendar_assignment = CalendarDateAssignment::find([$GLOBALS['user']->id, $date_id]);
        if (!$this->calendar_assignment) {
            throw new AccessDeniedException();
        }
        CSRFProtection::verifyUnsafeRequest();

        $participation = Request::get('participation');
        if (!in_array($participation, ['', 'ACCEPTED', 'DECLINED', 'ACKNOWLEDGED'])) {
            throw new InvalidArgumentException();
        }

        $this->calendar_assignment->participation = $participation;
        if ($this->calendar_assignment->isDirty()) {
            $this->calendar_assignment->store();
            $this->calendar_assignment->sendParticipationStatus();
        }
        $this->response->add_header('X-Dialog-Close', '1');
        PageLayout::postSuccess(_('Ihre Teilnahmestatus wurde geändert.'));
        $this->render_nothing();
    }
}
