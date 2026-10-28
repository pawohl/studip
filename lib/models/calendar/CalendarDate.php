<?php
/**
 * CalendarDate.php - Model class for calendar dates.
 *
 * CalendarDate represents a date in the personal calendar.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Peter Thienel <thienel@data-quest.de>
 * @author      Moritz Strohm <strohm@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       3.2
 *
 * @property string $id database column
 * @property string $author_id database column
 * @property string|null $editor_id database column
 * @property string $unique_id database column
 * @property int $begin database column
 * @property int $end database column
 * @property string $title database column
 * @property string|null $description database column
 * @property string $access database column
 * @property string|null $user_category database column
 * @property int $category database column
 * @property string|null $location database column
 * @property int|null $interval database column
 * @property int|null $offset database column
 * @property string|null $days database column
 * @property int|null $month database column
 * @property string|null $repetition_type database column
 * @property int $number_of_dates database column
 * @property int $repetition_end database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property int $import_date database column
 * @property SimpleORMapCollection<CalendarDateAssignment> $calendars
 * @property SimpleORMapCollection<CalendarDateException> $exceptions
 * @property User $author
 * @property User|null $editor
 */
class CalendarDate extends SimpleORMap implements PrivacyObject
{
    /**
     * NEVER_ENDING represents the value of the repetition_end field for
     * a date that never ends. The value is the result of computing
     * 2 ^ 31 - 1.
     *
     * NOTE: This constant must be changed long before 2038-01-19 03:14:07 UTC
     * or else dates that should end at some specific point in time may end
     * never.
     */
    public const NEVER_ENDING = 2147483647;

    public const REPETITION_SINGLE = 'SINGLE';
    public const REPETITION_DAILY = 'DAILY';
    public const REPETITION_WEEKLY = 'WEEKLY';
    public const REPETITION_MONTHLY = 'MONTHLY';
    public const REPETITION_YEARLY = 'YEARLY';

    protected static function configure($config = [])
    {
        $config['db_table'] = 'calendar_dates';

        $config['belongs_to']['author'] = [
            'class_name'  => User::class,
            'foreign_key' => 'author_id',
        ];
        $config['belongs_to']['editor'] = [
            'class_name'  => User::class,
            'foreign_key' => 'editor_id',
        ];
        $config['has_many']['calendars'] = [
            'class_name'  => CalendarDateAssignment::class,
            'assoc_foreign_key' => 'calendar_date_id',
            'on_store'    => 'store',
            'on_delete'   => 'delete'
        ];
        $config['has_many']['exceptions'] = [
            'class_name'  => CalendarDateException::class,
            'assoc_foreign_key' => 'calendar_date_id',
            'on_store'    => 'store',
            'on_delete'   => 'delete'
        ];

        $config['default_values']['interval'] = 0;
        $config['default_values']['offset'] = 0;

        $config['registered_callbacks']['before_store'][] = 'calculateExpiration';
        $config['registered_callbacks']['after_store'][] = 'cbSendDateModificationMail';
        $config['registered_callbacks']['before_store'][] = 'cbGenerateUniqueId';

        parent::configure($config);

    }

    /**
     * @deprecated
     */
    public function getDefaultValue($field)
    {
        if ($field === 'begin') {
            return time();
        }
        if ($field === 'end' && $this->content['begin']) {
            return $this->content['begin'] + 3600;
        }
        return parent::getDefaultValue($field);
    }

    public function cbSendDateModificationMail()
    {
        $template_factory = new Flexi\Factory($GLOBALS['STUDIP_BASE_PATH'] . '/locale/');

        foreach ($this->calendars as $calendar) {
            if ($calendar->range_id === $this->editor_id) {
                //The editor shall not get a mail about the changes they just made.
                continue;
            }
            if (!$calendar->user) {
                //Wrong range or not a user.
                continue;
            }
            setTempLanguage($calendar->range_id);

            $lang_path = getUserLanguagePath($calendar->range_id);
            $template = $template_factory->open($lang_path . '/LC_MAILS/date_changed.php');
            $template->set_attribute('date', $this);
            $template->set_attribute('receiver', $calendar->user);
            $template->set_attribute('receiver_date_assignment', $calendar);
            $mail_text = $template->render();
            Message::send(
                '____%system%____',
                [$calendar->user->username],
                sprintf(_('Terminänderung durch %s'), $this->editor->getFullName()),
                $mail_text
            );

            restoreLanguage();
        }
    }

    /**
     * Generates an unique id if it isn't present.
     * @return void
     */
    public function cbGenerateUniqueId()
    {
        if (!$this->unique_id) {
            $this->unique_id = 'Stud.IP-' . $this->id . '@' . ($_SERVER['SERVER_NAME'] ?? '');
        }
    }

    /**
     * TODO
     *
     * @param string $range_id
     * @return bool
     */
    public function isVisible(string $range_id)
    {
        if (CalendarDateAssignment::exists([$range_id, $this->id])) {
            //Users may see the dates in their calendar:
            return true;
        }

        $assignments = CalendarDateAssignment::findByCalendar_date_id($this->id);
        foreach ($assignments as $assignment) {
            if ($assignment->course instanceof Course) {
                if ($assignment->course->isCalendarReadable($range_id)) {
                    return true;
                }
            } elseif ($assignment->user instanceof User) {
                if ($assignment->user->isCalendarReadable($range_id)) {
                    //The date is only readable if it isn't confidential:
                    return $this->access !== 'CONFIDENTIAL';
                }
            }
        }

        //In case the date is not in a calendar of a user or a course
        //where the user has read access to, the date is only visible
        //when it is public.
        return $this->access === 'PUBLIC';
    }


    public function isWritable(string $range_id)
    {
        if (ConsultationSlot::isSlotEvent($this)) {
            return false;
        }

        if ($this->author_id === $range_id) {
            //The author may always modify one of their dates:
            return true;
        }

        if (CalendarDateAssignment::exists([$range_id, $this->id])) {
            //The date is in the calendar of the user/course
            //and therefore, the user or course administrator (tutor, dozent)
            //may change the date.
            return true;
        }
        //In case $range_id is a User-ID, a check has to be made if the calendar
        //date is bound to a course and the user has at least "tutor" permissions
        //in the course.
        if (User::exists($range_id)) {
            $writable_via_course = false;
            $assignments = CalendarDateAssignment::findByCalendar_date_id($this->id);
            foreach ($assignments as $assignment) {
                if (Course::exists($assignment->range_id)
                    && $GLOBALS['perm']->have_studip_perm('tutor', $assignment->range_id, $range_id)) {
                    $writable_via_course = true;
                    break;
                }
            }
            if ($writable_via_course) {
                return true;
            }
        }

        //Check contacts: Has the contact of the user that is represented by
        //$range_id write permissions to all the calendars of all the users that
        //are assigned to the date?

        $contacts_with_write_permissions = Contact::countBySql(
            "JOIN `calendar_date_assignments` cda
               ON `contact`.`user_id` = cda.`range_id`
            WHERE `contact`.`owner_id` = :current_range_id
              AND `contact`.`calendar_permissions` = 'WRITE'
              AND cda.`calendar_date_id` = :calendar_date_id
              AND cda.`range_id` <> :current_range_id",
            [
                'calendar_date_id' => $this->id,
                'current_range_id' => $range_id
            ]
        );
        $other_participant_count = CalendarDateAssignment::countBySql(
            "`calendar_date_id` = :calendar_date_id
             AND `range_id` <> :current_range_id",
            [
                'calendar_date_id' => $this->id,
                'current_range_id' => $range_id
            ]
        );

        if ($contacts_with_write_permissions === $other_participant_count) {
            //The user represented by $range_id has write permissions to all
            //calendars of all the other users that are assigned to the date.
            return true;
        }

        //NOTE: CALENDAR_GRANT_ALL_INSERT MUST NOT be regarded here, because it only
        //defines the behavior when inserting calendar dates and not when modifying them.

        //In case it is a course date, we must check if the user has write
        //permissions from the course:
        $course_assignments = CalendarDateAssignment::findBySql(
            "JOIN `seminare`
               ON `calendar_date_assignments`.`range_id` = `seminare`.`seminar_id`
            WHERE `calendar_date_id` = :calendar_date_id",
            ['calendar_date_id' => $this->id]
        );
        foreach ($course_assignments as $course_assignment) {
            if ($course_assignment->course->isCalendarWritable($range_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether the date spans over one whole day. This means that the date takes
     * place on one day from 0:00:00 to 23:59:59.
     *
     * @return bool True, if the date spans over the whole day, false otherwise.
     */
    public function isWholeDay() : bool
    {
        $begin = new DateTime();
        $begin->setTimestamp($this->begin);
        $end = new DateTime();
        $end->setTimestamp($this->end);

        if ($begin->format('Ymd') !== $end->format('Ymd')) {
            //Beginning and end are on different days.
            return false;
        }
        //If the beginning is on midnight and the end is one second before midnight of the next day,
        //the date spans over the whole day.
        return $begin->format('His') === '000000'
            && $end->format('His') === '235959';
    }


    /**
     * Calculates the value of the "expire" column in case the CalendarDate object
     * has a repetition defined.
     *
     * @return void
     */
    public function calculateExpiration()
    {
        if (
            !in_array($this->repetition_type, [
                self::REPETITION_DAILY,
                self::REPETITION_WEEKLY,
                self::REPETITION_MONTHLY,
                self::REPETITION_YEARLY,
            ])
        ) {
            //No repetition. Nothing to do.
            return;
        }

        if ($this->number_of_dates > 1) {
            //There is a certain amount of repetitions, so that the expiration date
            //has to be calculated by that.
            $expiration = new DateTime();
            $expiration->setTimestamp($this->begin);
            $interval_str = '';
            if ($this->repetition_type === self::REPETITION_DAILY) {
                $interval_str = sprintf('P%dD', ((int) $this->number_of_dates - 1) * $this->interval);
            } elseif ($this->repetition_type === self::REPETITION_WEEKLY) {
                $days_length = mb_strlen($this->days);
                if ($days_length > 0) {
                    $wday = $expiration->format('N');
                    // set next weekday as first repetition
                    $expiration->modify($this->getWeekdayName());

                    $rep_offset = ($this->number_of_dates - 1) % $days_length;

                    $rep_count = $this->number_of_dates - 1;

                    $days_offset = floor($rep_count / $days_length) * 7 *
                        $this->interval + $rep_offset - 1;
                    $interval_str = sprintf('P%dD', $days_offset);
                } else {
                    $interval_str = sprintf('P%dW', ($this->number_of_dates - 1) * $this->interval);
                }
            } elseif ($this->repetition_type === self::REPETITION_MONTHLY) {
                $interval_str = sprintf('P%dM', ($this->number_of_dates - 1) * $this->interval);
            } elseif ($this->repetition_type === self::REPETITION_YEARLY) {
                $interval_str = sprintf('P%dY', ($this->number_of_dates - 1) * $this->interval);
            }
            try {
                $interval = new DateInterval($interval_str);
                $expiration->add($interval);
                $expiration->setTime(23, 59, 59);
                $this->repetition_end = $expiration->getTimestamp();
            } catch (Exception $e) {
                //Nothing to do.
            }
        } elseif (!$this->repetition_end) {
            //No expiration date is specified.
            //This would mean that the event "never" expires.
            $this->repetition_end = self::NEVER_ENDING;
        }
    }


    /**
     *
     * Returns the DateInterval for the repetition of this calendar date.
     *
     * @return DateInterval|null The DateInterval for this calendar date or null
     *     in case the date has no repetition.
     * @throws Exception In case a DateInterval cannot be constructed.
     */
    public function getRepetitionInterval() : ?DateInterval
    {
        if ($this->repetition_type === self::REPETITION_DAILY) {
            return new DateInterval(sprintf('P%uD', $this->interval));
        } elseif ($this->repetition_type === 'WORKDAYS') {
            return new DateInterval('P1W');
        } elseif ($this->repetition_type === self::REPETITION_WEEKLY) {
            return new DateInterval(sprintf('P%uW', $this->interval));
        } elseif ($this->repetition_type === self::REPETITION_MONTHLY) {
            return new DateInterval(sprintf('P%uM', $this->interval));
        } elseif ($this->repetition_type === self::REPETITION_YEARLY) {
            return new DateInterval(sprintf('P%uY', $this->interval));
        }
        //No repetition: no interval.
        return null;
    }


    public function getRepetitionOffset() : ?DateInterval
    {
        if (!$this->offset) {
            return null;
        }

        if ($this->repetition_type === self::REPETITION_MONTHLY) {
            if ($this->days_offset) {
                return new DateInterval(sprintf('P%1$uM%2$uD', $this->offset, $this->days_offset));
            } else {
                return new DateInterval(sprintf('P%uM', $this->offset));
            }
        } elseif ($this->repetition_type === self::REPETITION_YEARLY) {
            return new DateInterval(sprintf('P%uM', $this->offset));
        }
        return null;
    }


    /**
     * Export available data of a given user into a storage object
     * (an instance of the StoredUserData class) for that user.
     *
     * @param StoredUserData $storage object to store data into
     */
    public static function exportUserData(StoredUserData $storage)
    {
        $sorm = self::findThru($storage->user_id, [
            'thru_table'        => 'calendar_date_assignments',
            'thru_key'          => 'range_id',
            'thru_assoc_key'    => 'calendar_date_id',
            'assoc_foreign_key' => 'id',
        ]);
        if ($sorm) {
            $field_data = [];
            foreach ($sorm as $row) {
                $field_data[] = $row->toRawArray();
            }
            if ($field_data) {
                $storage->addTabularData(_('Kalendereinträge'), 'calendar_dates', $field_data);
            }
        }
    }


    /**
     * This is a helper method to set all the fields for date repetition to an empty string.
     *
     * @return void
     */
    public function clearRepetitionFields()
    {
        $this->repetition_type = self::REPETITION_SINGLE;
        $this->interval = 0;
        $this->offset = 0;
        $this->days = '';
        $this->month = null;
        $this->number_of_dates = 1;
        $this->repetition_end = 0;
    }

    public function getAccessAsString() : string
    {
        if ($this->access === 'PUBLIC') {
            return _('Öffentlich');
        } elseif ($this->access === 'PRIVATE') {
            return _('Privat');
        } elseif ($this->access === 'CONFIDENTIAL') {
            return _('Vertraulich');
        } else {
            return _('Keine Angabe');
        }
    }

    public function getRepetitionAsString() : string
    {
        $repetition_string = '';

        if ($this->repetition_type === self::REPETITION_SINGLE) {
            $repetition_string = _('Keine Wiederholung');
        } elseif ($this->repetition_type === self::REPETITION_DAILY) {
            if ($this->interval > 0) {
                if ($this->interval == '1') {
                    //Each day
                    if ($this->number_of_dates > 1) {
                        $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                        $repetition_string = sprintf(
                            _('Täglich (%u Termine)'),
                            $this->number_of_dates - $number_of_exceptions
                        );
                    } elseif ($this->repetition_end < CalendarDate::NEVER_ENDING) {
                        $repetition_string = sprintf(
                            _('Täglich bis zum %1$s'),
                            date('d.m.Y', $this->repetition_end)
                        );
                    } else {
                        $repetition_string = _('Täglich ohne Begrenzung');
                    }
                } else {
                    //Every %u day
                    if ($this->number_of_dates > 1) {
                        $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                        $repetition_string = sprintf(
                            _('Jeden %1$u. Tag (%2$u Termine)'),
                            $this->interval,
                            $this->number_of_dates - $number_of_exceptions
                        );
                    } elseif ($this->repetition_end < self::NEVER_ENDING) {
                        $repetition_string = sprintf(
                            _('Jeden %1$u. Tag bis zum %2$s'),
                            $this->interval,
                            date('d.m.Y', $this->repetition_end)
                        );
                    } else {
                        $repetition_string = sprintf(
                            _('Jeden %u. Tag ohne Begrenzung'),
                            $this->interval
                        );
                    }
                }
            }
        } elseif ($this->repetition_type === self::REPETITION_WEEKLY) {
            $weekday_string = '';
            if (strlen((string) $this->days) > 1) {
                //Multiple days
                $days = [];
                foreach (str_split($this->days) as $day_number) {
                    $days[] = getWeekday($day_number === '7' ? 0 : (int) $day_number, false);
                }
                $all_but_last_day = array_slice($days, 0, -1);
                $weekday_string = sprintf(
                    _('%1$s und %2$s'),
                    implode(', ', $all_but_last_day),
                    end($days)
                );
            } elseif ((string) $this->days !== '') {
                //One day
                $weekday_string = getWeekday((int) $this->days[0], false);
            } else {
                //Defective data without a weekday: fall back to the weekday of the beginning.
                $weekday_string = getWeekday((int) date('w', $this->begin), false);
            }
            if ($this->interval == '1') {
                //Each week
                if ($this->number_of_dates > 1) {
                    $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                    $repetition_string = sprintf(
                        ngettext('Einmal am folgenden %s', 'Jeden %1$s (%2$u Termine)', $this->number_of_dates - 1),
                        $weekday_string,
                        $this->number_of_dates - $number_of_exceptions
                    );
                } elseif ($this->repetition_end < self::NEVER_ENDING) {
                    $repetition_string = sprintf(
                        _('Jeden %1$s bis zum %2$s'),
                        $weekday_string,
                        date('d.m.Y', $this->repetition_end)
                    );
                } else {
                    $repetition_string = sprintf(
                        _('Jeden %s ohne Begrenzung'),
                        $weekday_string
                    );
                }
            } else {
                //Every %u week
                if ($this->number_of_dates > 1) {
                    $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                    $repetition_string = sprintf(
                        _('Jeden %1$u. %2$s (%3$u Termine)'),
                        $this->interval,
                        $weekday_string,
                        $this->number_of_dates - $number_of_exceptions
                    );
                } elseif ($this->repetition_end < self::NEVER_ENDING) {
                    $repetition_string = sprintf(
                        _('Jeden %1$u. %2$s bis zum %3$s'),
                        $this->interval,
                        $weekday_string,
                        date('d.m.Y', $this->repetition_end)
                    );
                } else {
                    $repetition_string = sprintf(
                        _('Jeden %1$u. %2$s ohne Begrenzung'),
                        $this->interval,
                        $weekday_string
                    );
                }
            }
        } elseif ($this->repetition_type === self::REPETITION_MONTHLY) {
            if ($this->interval == '1') {
                //Each month
                if ($this->days) {
                    if ($this->offset < 0) {
                        //Repetition on one specific day of week in the last week.
                        $repetition_string = sprintf(
                            _('Jeden Monat am letzten %s'),
                            getWeekday($this->days, false)
                        );
                    } else {
                        //Repetition on one specific day of week in a specific week.
                        $repetition_string = sprintf(
                            _('Jeden Monat am %1$u. %2$s'),
                            $this->offset,
                            getWeekday($this->days, false)
                        );
                    }
                } else {
                    //Repetition on one specific day of month.
                    $repetition_string = sprintf(
                        _('Jeden Monat am %u. Tag'),
                        $this->offset
                    );
                }
            } else {
                //Every %u month
                if ($this->days) {
                    if ($this->offset < 0) {
                        //Repetition on one specific day of week on the last week.
                        $repetition_string = sprintf(
                            _('Jeden %1$u. Monat am letzten %2$s'),
                            $this->interval,
                            getWeekday($this->days, false)
                        );
                    } else {
                        //Repetition on one specific day of week in a specific week.
                        $repetition_string = sprintf(
                            _('Jeden %1$u. Monat am %2$u. %3$s'),
                            $this->interval,
                            $this->offset,
                            getWeekday($this->days, false)
                        );
                    }
                } else {
                    //Repetition on one specific day of month.
                    $repetition_string = sprintf(
                        _('Jeden %1$u. Monat am %2$u.'),
                        $this->interval,
                        $this->offset
                    );
                }
            }
        } elseif ($this->repetition_type === self::REPETITION_YEARLY) {
            if ($this->interval == '1') {
                //Each year
                if ($this->days) {
                    //Repetition on one specific day of week in a specific week
                    //in a specific month.
                    if ($this->offset < 0) {
                        if ($this->number_of_dates > 1) {
                            $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                            $repetition_string = sprintf(
                                _('Jedes Jahr im %1$s am letzten %2$s (%3$u Termine)'),
                                getMonthName($this->month, false),
                                getWeekday($this->days, false),
                                $this->number_of_dates - $number_of_exceptions
                            );
                        } elseif ($this->repetition_end < self::NEVER_ENDING) {
                            $repetition_string = sprintf(
                                _('Jedes Jahr im %1$s am letzten %2$s bis zum %3$s'),
                                getMonthName($this->month, false),
                                getWeekday($this->days, false),
                                date('d.m.Y', $this->repetition_end)
                            );
                        } else {
                            $repetition_string = sprintf(
                                _('Jedes Jahr im %1$s am letzten %2$s ohne Begrenzung'),
                                getMonthName($this->month, false),
                                getWeekday($this->days, false)
                            );
                        }
                    } else {
                        if ($this->number_of_dates > 1) {
                            $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                            $repetition_string = sprintf(
                                _('Jedes Jahr im %1$s am %2$u. %3$s (%4$u Termine'),
                                getMonthName($this->month, false),
                                $this->offset,
                                getWeekday($this->days, false),
                                $this->number_of_dates - $number_of_exceptions
                            );
                        } elseif ($this->repetition_end < self::NEVER_ENDING) {
                            $repetition_string = sprintf(
                                _('Jedes Jahr im %1$s am %2$u. %3$s bis zum %4$s'),
                                getMonthName($this->month, false),
                                $this->offset,
                                getWeekday($this->days, false),
                                date('d.m.Y', $this->repetition_end)
                            );
                        } else {
                            $repetition_string = sprintf(
                                _('Jedes Jahr im %1$s am %2$u. %3$s ohne Begrenzung'),
                                getMonthName($this->month, false),
                                $this->offset,
                                getWeekday($this->days, false)
                            );
                        }
                    }
                } else {
                    //Repetition on one specific day of month.
                    if ($this->number_of_dates > 1) {
                        $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                        $repetition_string = sprintf(
                            _('Jedes Jahr am %1$u. %2$s (%3$u Termine)'),
                            $this->offset,
                            getMonthName($this->month, false),
                            $this->number_of_dates - $number_of_exceptions
                        );
                    } elseif ($this->repetition_end < self::NEVER_ENDING) {
                        $repetition_string = sprintf(
                            _('Jedes Jahr am %1$u. %2$s bis zum %3$s'),
                            $this->offset,
                            getMonthName($this->month, false),
                            date('d.m.Y', $this->repetition_end)
                        );
                    } else {
                        $repetition_string = sprintf(
                            _('Jedes Jahr am %1$u. %2$s ohne Begrenzung'),
                            $this->offset,
                            getMonthName($this->month, false)
                        );
                    }
                }
            } else {
                //Every %u years
                if ($this->days) {
                    //Repetition on one specific day of week in a specific week
                    //in a specific month.
                    if ($this->offset < 0) {
                        if ($this->number_of_dates > 1) {
                            $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                            $repetition_string = sprintf(
                                _('Jedes %1$u. Jahr im %2$s am letzten %3$s (%4$u Termine)'),
                                $this->interval,
                                getMonthName($this->month, false),
                                getWeekday($this->days, false),
                                $this->number_of_dates - $number_of_exceptions
                            );
                        } elseif ($this->repetition_end < self::NEVER_ENDING) {
                            $repetition_string = sprintf(
                                _('Jedes %1$u. Jahr im %2$s am letzten %3$s bis zum %4$s'),
                                $this->interval,
                                getMonthName($this->month, false),
                                getWeekday($this->days, false),
                                date('d.m.Y', $this->repetition_end)
                            );
                        } else {
                            $repetition_string = sprintf(
                                _('Jedes %1$u. Jahr im %2$s am letzten %3$s ohne Begrenzung'),
                                $this->interval,
                                getMonthName($this->month, false),
                                getWeekday($this->days, false)
                            );
                        }
                    } else {
                        if ($this->number_of_dates > 1) {
                            $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                            $repetition_string = sprintf(
                                _('Jedes %1$u. Jahr im %2$s am %3$u. %4$s (%5$u Termine)'),
                                $this->interval,
                                getMonthName($this->month, false),
                                $this->offset,
                                getWeekday($this->days, false),
                                $this->number_of_dates - $number_of_exceptions
                            );
                        } elseif ($this->repetition_end < self::NEVER_ENDING) {
                            $repetition_string = sprintf(
                                _('Jedes %1$u. Jahr im %2$s am %3$u. %4$s bis zum %5$s'),
                                $this->interval,
                                getMonthName($this->month, false),
                                $this->offset,
                                getWeekday($this->days, false),
                                date('d.m.Y', $this->repetition_end)
                            );
                        } else {
                            $repetition_string = sprintf(
                                _('Jedes %1$u. Jahr im %2$s am %3$u. %4$s ohne Begrenzung'),
                                $this->interval,
                                getMonthName($this->month, false),
                                $this->offset,
                                getWeekday($this->days, false)
                            );
                        }
                    }
                } else {
                    //Repetition on one specific day of month.
                    if ($this->number_of_dates > 1) {
                        $number_of_exceptions = CalendarDateException::countByCalendar_date_id($this->id);
                        $repetition_string = sprintf(
                            _('Jedes %1$u. Jahr am %2$u. %3$s (%4$u Termine)'),
                            $this->interval,
                            $this->offset,
                            getMonthName($this->month, false),
                            $this->number_of_dates - $number_of_exceptions
                        );
                    } elseif ($this->repetition_end < self::NEVER_ENDING) {
                        $repetition_string = sprintf(
                            _('Jedes %1$u. Jahr am %2$u. %3$s bis zum %4$s'),
                            $this->interval,
                            $this->offset,
                            getMonthName($this->month, false),
                            date('d.m.Y', $this->repetition_end)
                        );
                    } else {
                        $repetition_string = sprintf(
                            _('Jedes %1$u. Jahr am %2$u. %3$s ohne Begrenzung'),
                            $this->interval,
                            $this->offset,
                            getMonthName($this->month, false)
                        );
                    }
                }
            }
        }

        return $repetition_string;
    }


    /**
     * Creates the HTML for creating a repetition input Vue component instance
     * and fills it with the values from the model.
     *
     * @param string $element_name The name of the element.
     *
     * @return string The HTML code for creating the repetition input vue instance.
     */
    public function getRepetitionInputHtml(string $element_name = 'repetition') : string
    {
        $repetition_end_type = '';
        $repetition_end_date = '';
        $repetition_dow = '[]';
        $repetition_dow_week = '';

        if ($this->isNew()) {
            $repetition_end_date = htmlReady(date('d.m.Y', $this->end));
            $repetition_dow = sprintf('["%s"]', date('N', $this->begin));
            $repetition_dow_week = '1';
        } else {

            if ($this->repetition_end && intval($this->repetition_end) !== self::NEVER_ENDING) {
                $repetition_end_date = htmlReady(date('d.m.Y', $this->repetition_end));
            } else {
                //Provide a good default value in case the user wants to enable or change the repetition:
                $repetition_end_date = htmlReady(date('d.m.Y', $this->end));
            }
            if ($this->days) {
                $repetition_dow = json_encode(str_split($this->days));
                $repetition_dow_week = $this->offset;
            } else {
                //The days field is not in use. Use the day of the beginning as a good default.
                $repetition_dow = sprintf('["%s"]', date('N', $this->begin));
                //Also set repetition_dow_week to 1 as a good default in case the user
                //switches to the monthly repetition type where a specific day of week
                //is selected instead of a specific day of month:
                $repetition_dow_week = '1';
            }

            if ($this->number_of_dates > 1) {
                $repetition_end_type = 'end_count';
            } elseif ($this->repetition_end && intval($this->repetition_end) !== self::NEVER_ENDING) {
                //The end date is at some certain date and not on the virtual "never" date.
                $repetition_end_type = 'end_date';
            }
        }

        $attributes = [
            'name'                   => $element_name,
            'default_date'           => $this->begin,
            'repetition_type'        => $this->repetition_type ?? '',
            'repetition_interval'    => $this->interval > 0 ? $this->interval : '1',
            ':repetition_dow'        => $repetition_dow,
            ':repetition_dow_week'   => $repetition_dow_week,
            ':repetition_month'      => $this->isNew() ? date('m', $this->begin) : $this->month,
            ':repetition_month_type' => $this->isNew() ? "'dom'" : ($this->days ? "'dow'" : "'dom'"),
            ':repetition_dom'        => $this->isNew() ? date('d', $this->begin) : $this->offset,
            ':repetition_end_type'   => sprintf("'%s'", $repetition_end_type),
            ':number_of_dates'       => $this->isNew() ? '1' : $this->number_of_dates,
            ':repetition_end_date'   => sprintf("'%s'", $repetition_end_date)
        ];
        return sprintf('<repetition-input %s></repetition-input>', arrayToHtmlAttributes($attributes));
    }

    public function getCategoryAsString() : string
    {
        if ($this->user_category) {
            return $this->user_category;
        }
        return $GLOBALS['PERS_TERMIN_KAT'][$this->category]['name'] ?? '';
    }

    /**
     * Returns the textual ordinal for the offset of a weekday from property offset
     * or an empty string if offset is not set.
     *
     * @return string The textual ordinal.
     */
    public function getOrdinalName(): string
    {
        if (mb_strlen($this->offset)) {
            $ordinal_array = [
                '1' => 'first',
                '2' => 'second',
                '3' => 'third',
                '4' => 'fourth',
                '5' => 'fifth',
                '-1' => 'last'
            ];
            return $ordinal_array[$this->offset];
        }
        return '';
    }

    /**
     * Returns the short name of first weekday from property days or an
     * empty string if days is not set.
     *
     * @param $offset int Offset of days.
     * @return string Short name of weekday.
     */
    public function getWeekdayName(int $offset = 0): string
    {
        if (mb_strlen($this->days)) {
            $wdays = [
                '1' => 'mon',
                '2' => 'tue',
                '3' => 'wed',
                '4' => 'thu',
                '5' => 'fri',
                '6' => 'sat',
                '7' => 'sun'
            ];
            return $wdays[substr($this->days, $offset, 1)];
        }
        return '';
    }

    /**
     * Returns a string representation of the access field.
     *
     * @return string A localised string of the access field.
     */
    public function getVisibilityAsString() : string
    {
        if ($this->access === 'PUBLIC') {
            return _('Öffentlich');
        } elseif ($this->access === 'CONFIDENTIAL') {
            return _('Vertraulich');
        } else {
            return _('Privat');
        }
    }

    /**
     * Returns the names of the participants of the date. This also includes courses
     * to which the date is assigned.
     *
     * @param string $user_id The user for which to generate the participant array.
     *     The user with that ID is excluded from that list.
     * @return array A list with the names of the participants of the date.
     */
    public function getParticipantsAsStringArray(string $user_id = '') : array
    {
        $participant_strings = [];
        foreach ($this->calendars as $calendar) {
            if ($calendar->range_id === $user_id) {
                //Exclude the user for which to generate the list.
                continue;
            }
            if ($calendar->course instanceof Course) {
                $participant_strings[] = $calendar->course->getFullName();
            } elseif ($calendar->user instanceof User) {
                $participant_strings[] = $calendar->user->getFullName();
            }
        }

        asort($participant_strings);

        return $participant_strings;
    }
}
