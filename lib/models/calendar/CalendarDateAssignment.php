<?php
/**
 * CalendarDateAssignment.php - Model class for calendar date assignments.
 *
 * CalendarDateAssignment represents the assignment of a calendar date
 *  to a specific calendar. The calendar is represented by a range-ID
 *  since it can be a personal calendar, course calendar or institute
 *  calendar.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Moritz Strohm <strohm@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       5.5
 *
 * @property array $id alias for pk
 * @property string $range_id The range-ID for the assignment.
 * @property string $calendar_date_id The ID of the calendar date for the assignment.
 * @property int $mkdate The creation date of the assignment.
 * @property int $chdate The modification date of the assignment.
 * @property string $participation The participation status of the receiver (range_id).
     This column is an enum with the following values:
     - empty string: Participation status is unknown.
     - "ACCEPTED": The calendar owner accepted the date.
     - "DECLINED": The calendar owner declined the date.
     - "ACKNOWLEDGED": The calendar owner only acknowledged that the date exists
           but doesn't necessarily participate in it.
 * @property CalendarDate $calendar_date The associated calendar date object.
 * @property User $user
 * @property Course $course
 */
class CalendarDateAssignment extends SimpleORMap implements Event
{
    /**
     * @var bool This attribute allows the suppression of automatic mail sending
     *     when storing or deleting the calendar date assignment.
     *     By default, mails are sent.
     */
    public $suppress_mails = false;

    protected static function configure($config = [])
    {
        $config['db_table'] = 'calendar_date_assignments';

        $config['belongs_to']['calendar_date'] = [
            'class_name'  => CalendarDate::class,
            'foreign_key' => 'calendar_date_id',
            'assoc_func'  => 'find'
        ];
        $config['belongs_to']['user'] = [
            'class_name'  => User::class,
            'foreign_key' => 'range_id',
            'assoc_func'  => 'find'
        ];
        $config['belongs_to']['course'] = [
            'class_name'  => Course::class,
            'foreign_key' => 'range_id',
            'assoc_func'  => 'find'
        ];

        $config['registered_callbacks']['after_create'][] = 'cbSendNewDateMail';
        $config['registered_callbacks']['after_delete'][] = 'cbSendDateDeletedMail';

        parent::configure($config);
    }


    public function cbSendNewDateMail()
    {
        if ($this->suppress_mails) {
            return;
        }
        if ($this->range_id === $this->calendar_date->editor_id) {
            return;
        }
        if (!$this->calendar_date || !$this->user) {
            //Wrong calendar range (not a user) or invalid data set.
            return;
        }

        $template_factory = new Flexi\Factory($GLOBALS['STUDIP_BASE_PATH'] . '/locale/');

        setTempLanguage($this->range_id);
        $lang_path = getUserLanguagePath($this->range_id);
        $template = $template_factory->open($lang_path . '/LC_MAILS/date_created.php');
        $template->set_attribute('date', $this->calendar_date);
        $template->set_attribute('receiver', $this->user);
        $mail_text = $template->render();
        Message::send(
            '____%system%____',
            [$this->user->username],
            sprintf(_('%s hat einen Termin im Kalender eingetragen'), $this->calendar_date->editor->getFullName()),
            $mail_text
        );

        restoreLanguage();
    }

    public function cbSendDateDeletedMail()
    {
        if ($this->suppress_mails) {
            return;
        }
        $actor = User::findCurrent() ?? $this->calendar_date->editor;
        if ($this->range_id === $actor->id) {
            //The user who deleted the date shall not get notified about this.
            return;
        }
        if (!$this->calendar_date || !$this->user) {
            //Wrong calendar range (not a user) or invalid data set.
            return;
        }

        $template_factory = new Flexi\Factory($GLOBALS['STUDIP_BASE_PATH'] . '/locale/');

        setTempLanguage($this->range_id);
        $lang_path = getUserLanguagePath($this->range_id);
        $template = $template_factory->open($lang_path . '/LC_MAILS/date_deleted.php');
        $template->set_attribute('date', $this->calendar_date);
        $template->set_attribute('actor', $actor);
        $template->set_attribute('receiver', $this->user);
        $mail_text = $template->render();
        Message::send(
            '____%system%____',
            [$this->user->username],
            sprintf(_('%s hat einen Termin im Kalender gelöscht'), $actor->getFullName()),
            $mail_text
        );

        restoreLanguage();
    }

    /**
     * Sends the participation status of the calendar the date
     * is assigned to. This is only done for user calendars
     * and not for course calendars.
     *
     * @return void
     */
    public function sendParticipationStatus() : void
    {
        if (!($this->user instanceof User)) {
            //The calendar date is assigned to a course calendar.
            return;
        }

        if (!$this->participation || $this->participation === 'ACKNOWLEDGED') {
            //Nothing shall be done in these two cases.
            return;
        }

        if (empty($this->calendar_date->author->username)) {
            //The calendar date has no author.
            return;
        }
        if ($this->range_id === $this->calendar_date->author_id) {
            //The author of the date changed their participation status.
            //So they know what they did and do not have to be notified.
            return;
        }

        $template_factory = new Flexi\Factory($GLOBALS['STUDIP_BASE_PATH'] . '/locale/');

        setTempLanguage($this->range_id);
        $lang_path = getUserLanguagePath($this->range_id);
        $template = $template_factory->open($lang_path . '/LC_MAILS/date_participation.php');
        $template->set_attribute('date_assignment', $this);
        $mail_text = $template->render();

        $subject = '';
        if ($this->participation === 'ACCEPTED') {
            $subject = sprintf(
                _('%1$s hat Ihren Termin am %2$s angenommen'),
                $this->user->getFullName(),
                date('d.m.Y', $this->calendar_date->begin)
            );
        } elseif ($this->participation === 'DECLINED') {
            $subject = sprintf(
                _('%1$s hat Ihren Termin am %2$s abgelehnt'),
                $this->user->getFullName(),
                date('d.m.Y', $this->calendar_date->begin)
            );
        }

        Message::send(
            '____%system%____',
            [$this->calendar_date->author->username],
            $subject,
            $mail_text
        );

        restoreLanguage();
    }

    /**
     * Retrieves calendar dates inside a specified time range that are present in the calendar of a
     * course or user. They can additionally be filtered by the access level and declined events
     * can be filtered out, too.
     *
     * @param DateTime $begin The beginning of the time range.
     *
     * @param DateTime $end The end of the time range.
     *
     * @param string $range_id The ID of the course or user whose calendar dates shall be retrieved.
     *
     * @param array $access_levels The access level filter: Only include calendar dates that have one of the
     *     access levels in the list.
     *
     * @param bool $with_declined Include declined calendar dates (true) or filter them out (false).
     *     Defaults to false.
     *
     * @return CalendarDateAssignment[] A list of calendar date assignments in the time range that match the filters.
     */
    public static function getEvents(
        DateTime $begin,
        DateTime $end,
        string $range_id,
        array $access_levels = ['PUBLIC', 'PRIVATE', 'CONFIDENTIAL'],
        bool $with_declined = false
    ) : array
    {
        // Always use the timezone of the server:
        $local_timezone = (new DateTime())->getTimezone();
        $begin->setTimezone($local_timezone);
        $end->setTimezone($local_timezone);

        // one whole day as minimum (begin and end time stamp at the same day)
        $begin->modify('midnight');
        $end->modify('tomorrow -1 second');

        $sql = "JOIN `calendar_dates`
            ON calendar_date_id = `calendar_dates`.`id`
            WHERE
            `calendar_date_assignments`.`range_id` = :range_id
            AND
            `access` IN ( :access_levels ) ";
        if (!$with_declined) {
            $sql .= "AND `calendar_date_assignments`.`participation` <> 'DECLINED' ";
        }
        $sql_single = $sql . " AND
                `calendar_dates`.`begin` < :end  AND :begin < `calendar_dates`.`end`
            ";

        $events = self::findBySql($sql_single, [
            'range_id'      => $range_id,
            'begin'         => $begin->getTimestamp(),
            'end'           => $end->getTimestamp(),
            'access_levels' => $access_levels
        ]);


        $sql_repetition = $sql . " AND `calendar_dates`.`begin` < :end AND `calendar_dates`.`repetition_type` IN ('DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY')
                    AND `calendar_dates`.`repetition_end` > :begin
            ";

        $events = array_merge($events, self::findBySql($sql_repetition, [
            'range_id'      => $range_id,
            'begin'         => $begin->getTimestamp(),
            'end'           => $end->getTimestamp(),
            'access_levels' => $access_levels
        ]));

        $m_start = clone $begin;
        $m_end = clone $end;
        $events_created = [];
        while ($m_start < $m_end) {

            foreach ($events as $event) {
                $e_start = clone $event->getBegin();
                $e_end = clone $event->getEnd();
                $e_expire = $event->getExpire();

                $cal_start = DateTimeImmutable::createFromMutable($m_start);
                $cal_end = DateTimeImmutable::createFromMutable($m_start)->modify('tomorrow -1 second');
                $cal_noon = $cal_start->modify('noon');
                // single events or first event
                if (
                    ($e_start >= $cal_start && $e_end <= $cal_end)
                    || ($e_start >= $cal_start && $e_start <= $cal_end)
                    || ($e_start < $cal_start && $e_end > $cal_end)
                    || ($e_end > $cal_start && $e_start <= $cal_end)
                ) {
                    // exception for first event or single event
                    if (!$event->calendar_date->exceptions->findOneBy('date', $cal_start->format('Y-m-d'))
                        && !isset($events_created[$event->calendar_date->id])) {
                            $events_created[$event->calendar_date->id . '_' . $event->calendar_date->begin] = $event;
                    }
                } elseif ($e_expire > $cal_start) {
                    $events_created = array_merge($events_created, self::getRepetition($event, $cal_noon));
                }
            }

            $m_start->modify('+1 day');
        }

        return $events_created;
    }

    private static function getRepetition(
        CalendarDateAssignment $date,
        DateTimeImmutable $cal_noon,
        bool $calc_prev = true
    ): array
    {
        $rep_dates = [];
        $ts = $date->getNoonDate();
        if ($cal_noon >= $ts) {
            if ($date->isRepeatedAtDate($cal_noon)) {
                $rep_dates = array_merge($rep_dates, self::createRecurrentDate($date, $cal_noon));
            }
            if ($calc_prev) {
                $rep_noon = $cal_noon->modify(sprintf('-%s days', $date->getDurationDays()));
                $rep_dates = array_merge(
                    $rep_dates,
                    self::getRepetition(
                        $date,
                        $rep_noon,
                        false
                    )
                );
            }
        }
        return $rep_dates;
    }

    private function isRepeatedAtDate(DateTimeImmutable $cal_date): bool
    {
        $ts = $this->getNoonDate();
        $pos = 1;
        switch ($this->getRepetitionType()) {
            case CalendarDate::REPETITION_DAILY:
                $pos = $cal_date->diff($ts)->days % $this->calendar_date->interval;
                break;
            case CalendarDate::REPETITION_WEEKLY:
                $cal_ts = $cal_date->modify('monday this week noon');
                if ($cal_date >= $this->getBegin()) {
                    $pos = $cal_ts->diff($ts)->days % ($this->calendar_date->interval * 7);
                    if (
                        $pos === 0
                        && strpos($this->calendar_date->days, $cal_date->format('N')) === false
                    ) {
                        $pos = 1;
                    }
                }
                break;
            case CalendarDate::REPETITION_MONTHLY:
                $cal_ts = $cal_date->modify('first day of this month noon');
                $diff = $cal_ts->diff($ts);
                $pos = ($diff->m + $diff->y * 12) % $this->calendar_date->interval;
                if ($pos === 0) {
                    if (strlen($this->calendar_date->days)) {
                        $cal_ts_dom = $cal_ts->modify(sprintf('%s %s of this month noon',
                            $this->calendar_date->getOrdinalName(),
                            $this->calendar_date->getWeekdayName()));
                        if ($cal_ts_dom != $cal_date->setTime(12, 0)) {
                            $pos = 1;
                        }
                    } elseif ($this->calendar_date->offset !== $cal_date->format('j')) {
                        $pos = 1;
                    }
                }
                break;
            case CalendarDate::REPETITION_YEARLY:
                $cal_ts = $cal_date->modify('first day of this year noon');
                $diff = $cal_ts->diff($ts);
                $pos = $diff->y % $this->calendar_date->interval;
                if ($pos === 0) {
                    if (strlen($this->calendar_date->days)) {
                        $ts_doy = $ts->modify(sprintf('%s %s of %s-%s noon',
                            $this->calendar_date->getOrdinalName(),
                            $this->calendar_date->getWeekdayName(),
                            $cal_date->format('Y'),
                            $this->calendar_date->month));
                        if ($ts_doy->format('n-j') !== $cal_date->format('n-j')) {
                            $pos = 1;
                        }
                    } elseif (
                        $cal_date->format('n-j') !== sprintf(
                            '%s-%s',
                            $this->calendar_date->month,
                            $this->calendar_date->offset
                        )
                    ) {
                        $pos = 1;
                    }
                }
                break;
            default:
                $pos = 1;
        }
        //Also check for exceptions before returning:
        return $pos === 0
            && !$this->calendar_date->exceptions->findOneBy(
                'date',
                $cal_date->format('Y-m-d'));
    }

    private static function createRecurrentDate(
        CalendarDateAssignment $date,
        DateTimeImmutable $date_time
    ) : array
    {
        $date_begin = $date->getBegin();
        $date_end = $date->getEnd();

        $rec_date = clone $date;
        $time_begin = $date_begin->format('H:i:s');
        $time_end = $date_end->format('H:i:s');

        $rec_date_begin = $date_time->modify(sprintf('today %s', $time_begin));
        $rec_date_end = $rec_date_begin->add($date->getDuration())->modify($time_end);

        $rec_date->calendar_date->begin = $rec_date_begin->getTimestamp();
        $rec_date->calendar_date->end = $rec_date_end->getTimestamp();
        $index = $date->calendar_date->id . '_' . $rec_date_begin->getTimestamp();
        return [$index => $rec_date];
    }

    //Event interface implementation:

    public function getObjectId() : string
    {
        return (string)$this->id;
    }

    public function getPrimaryObjectID(): string
    {
        return $this->calendar_date_id;
    }

    public function getObjectClass(): string
    {
        return static::class;
    }

    public function getTitle() : string
    {
        return $this->calendar_date->title ?? '';
    }

    public function getBegin(): DateTime
    {
        $begin = new DateTime();
        $begin->setTimestamp($this->calendar_date->begin ?? 0);
        return $begin;
    }

    public function getEnd(): DateTime
    {
        $end = new DateTime();
        $end->setTimestamp($this->calendar_date->end ?? 0);
        return $end;
    }

    public function getDuration(): DateInterval
    {
        $begin = $this->getBegin();
        $end = $this->getEnd();
        return $begin->diff($end);
    }

    /**
     * Returns the "extent" in days of this date.
     *
     * @return int The "extent" in days of this date.
     */
    public function getDurationDays(): int
    {
        return self::getExtent($this->getEnd(), $this->getBegin());
    }

    /**
     * Returns the "extent" in days of this date.
     * The extent is the number of days a date is displayed in a calendar.
     *
     * @return int The "extent" in days of this date.
     */
    public static function getExtent(DateTimeInterface $date_begin, DateTimeInterface $date_end): int
    {
        $days_duration = $date_end->diff($date_begin)->days;
        if ($date_begin->format('His') > $date_end->format('His')) {
            $days_duration += 1;
        }
        return $days_duration;
    }

    public function getLocation(): string
    {
        return $this->calendar_date->location ?? '';
    }

    public function getUniqueId(): string
    {
        return $this->calendar_date->unique_id ?? '';
    }

    public function getDescription(): string
    {
        return $this->calendar_date->description ?? '';
    }

    public function getAdditionalDescriptions(): array
    {
        return [
            _('Kategorie')    => $this->calendar_date->getCategoryAsString(),
            _('Sichtbarkeit') => $this->calendar_date->getVisibilityAsString(),
            _('Wiederholung') => $this->calendar_date->getRepetitionAsString()
        ];
    }

    public function isAllDayEvent(): bool
    {
        $begin = $this->getBegin();
        if ($begin->format('His') !== '000000') {
            return false;
        }
        $end = $this->getEnd();
        return $end->format('His') === '235959';
    }

    public function isWritable(string $user_id): bool
    {
        if ($this->calendar_date->isWritable($user_id)) {
            //The date is writable.
            return true;
        }

        //The user referenced by $user_id is not the author of the date.
        //Check if they have write permissions to the calendar where the date is assigned to:
        if ($this->user instanceof User) {
            //It is a personal calendar. Check if the owner of the calendar has granted write permissions
            //to the user:
            return Contact::countBySQL(
                "`owner_id` = :owner_id AND `user_id` = :user_id
                AND `calendar_permissions` = 'WRITE'",
                ['owner_id' => $this->range_id, 'user_id' => $user_id]
            ) > 0;
        } elseif ($this->course instanceof Course) {
            //It is a course calendar.
            return $GLOBALS['perm']->have_studip_perm('dozent', $this->range_id, $user_id);
        }

        //No write permissions are granted.
        return false;
    }

    public function getCreationDate(): DateTime
    {
        $mkdate = new DateTime();
        $mkdate->setTimestamp($this->calendar_date->mkdate ?? 0);
        return $mkdate;
    }

    public function getModificationDate(): DateTime
    {
        $chdate = new DateTime();
        $chdate->setTimestamp($this->calendar_date->chdate ?? 0);
        return $chdate;
    }

    public function getImportDate(): DateTime
    {
        $import_date = new DateTime();
        $import_date->setTimestamp($this->calendar_date->import_date ?? 0);
        return $import_date;
    }

    public function getAuthor(): ?User
    {
        return $this->calendar_date->author ?? null;
    }

    public function getEditor(): ?User
    {
        return $this->calendar_date->editor ?? null;
    }

    /**
     * TODO calculate end of repetition for different types of repetition
     * @return float|int|object
     */
    public function getExpire()
    {
        if ($this->calendar_date->repetition_end > 0) {
            $expire = $this->calendar_date->repetition_end;
        } else {
            $expire = CalendarDate::NEVER_ENDING;
        }

        $end = new DateTime();
        $end->setTimestamp($expire);
        return $end;
    }

    // TODO calculate ts for monthly and yearly repetition
    public function getNoonDate()
    {
        $ts = DateTimeImmutable::createFromMutable($this->getBegin());
        switch ($this->calendar_date->repetition_type) {
            case CalendarDate::REPETITION_DAILY:
                return $ts->modify('noon');
            case CalendarDate::REPETITION_WEEKLY:
                return  $ts->modify('monday this week noon');
            case CalendarDate::REPETITION_MONTHLY:
                return $ts->modify('first day of this month noon');
            case CalendarDate::REPETITION_YEARLY:
                return $ts->modify('first day of this year noon');
            default:
                return $ts;
        }
    }

    /**
     * Returns the type of repetition.
     *
     * @return string The type of repetition.
     */
    public function getRepetitionType(): string
    {
        return $this->calendar_date->repetition_type;
    }

    public function toEventData(string $user_id): \Studip\Calendar\EventData
    {
        $begin = $this->getBegin();
        $end = $this->getEnd();

        $all_day = $this->isAllDayEvent();

        $hide_confidential_data = $this->calendar_date->access === 'CONFIDENTIAL'
            && $user_id !== $this->calendar_date->author_id;

        $event_classes = ['user-date'];

        $text_colour = '#000000';
        $background_colour = '#ffffff';
        $border_colour = '#000000';
        if (!$hide_confidential_data) {
            if ($this->calendar_date->user_category) {
                //The date belongs to a personal category that gets a grey colour.
                $background_colour = '#a7abaf';
                $border_colour     = '#a7abaf';
            } else {
                //The date belongs to a system category that has its own colours.
                $text_colour = $GLOBALS['PERS_TERMIN_KAT'][$this->calendar_date->category]['fgcolor'] ?? $text_colour;
                $background_colour = $GLOBALS['PERS_TERMIN_KAT'][$this->calendar_date->category]['bgcolor'] ?? $background_colour;
                $border_colour = $GLOBALS['PERS_TERMIN_KAT'][$this->calendar_date->category]['border_color'] ?? $border_colour;
                $event_classes[] = sprintf('user-date-category%d', $this->calendar_date->category);
            }
        }

        $studip_urls = [];
        $action_urls = [];
        if (!$hide_confidential_data) {
            $show_url_params = [];
            if ($this->calendar_date->repetition_type !== CalendarDate::REPETITION_SINGLE) {
                $show_url_params['selected_date'] = $begin->format('Y-m-d');
            }
            $studip_urls['show'] = URLHelper::getURL('dispatch.php/calendar/date/index/' . $this->calendar_date_id, $show_url_params);

            if ($this->isWritable($user_id)) {
                $studip_urls['resize_dialog'] = URLHelper::getURL('dispatch.php/calendar/date/move/' . $this->calendar_date_id);
                $studip_urls['move_dialog']   = URLHelper::getURL('dispatch.php/calendar/date/move/' . $this->calendar_date_id, ['original_date' => $begin->format('Y-m-d')]);
            }
        }

        return new \Studip\Calendar\EventData(
            $begin,
            $end,
            !$hide_confidential_data ? $this->getTitle() : _('Vertraulich'),
            $event_classes,
            $text_colour,
            $background_colour,
            $this->isWritable($user_id) && $this->calendar_date->isVisible($user_id),
            CalendarDateAssignment::class,
            $this->id,
            CalendarDate::class,
            $this->calendar_date_id,
            'user',
            $this->range_id ?? '',
            $studip_urls,
            $action_urls,
            $this->participation === 'DECLINED' ? 'decline-circle-full' : '',
            $border_colour,
            $all_day,
            $this->calendar_date_id
        );
    }

    public function getRangeName() : string
    {
        if ($this->course instanceof Course) {
            return $this->course->getFullName();
        } elseif ($this->user instanceof User) {
            return $this->user->getFullName();
        }
        return '';
    }

    public function getRangeAvatar() : ?Avatar
    {
        if ($this->course instanceof Course) {
            return CourseAvatar::getAvatar($this->range_id);
        } elseif ($this->user instanceof User) {
            return Avatar::getAvatar($this->range_id);
        }
        return null;
    }

    public function getParticipationAsString() : string
    {
        if ($this->participation === '') {
            return _('Abwartend');
        } elseif ($this->participation === 'ACKNOWLEDGED') {
            return _('Angenommen (keine Teilnahme)');
        } elseif ($this->participation === 'ACCEPTED') {
            return _('Angenommen');
        } elseif ($this->participation === 'DECLINED') {
            return _('Abgelehnt');
        }
        return '';
    }
}
