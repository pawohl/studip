<?php
/**
 * ICalendarExport.php
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
 * @since       5.5
 */


class ICalendarExport
{
     /**
     * Line break used in iCalendar
     */
    const NEWLINE = "\r\n";

    /**
     * Default start of the week
     */
    const WEEKSTART = 'MO';

    /**
     * Holds the time (as unix timestamp) used for
     * the timestamp in every exported iCalendar object.
     *
     * @var int $time
     */
    private $time = 0;

    public string $format;

    public function __construct()
    {
        $this->format = "iCalendar";
    }

    public function exportCalendarDates(string $range_id, DateTimeInterface $start, DateTimeInterface $end): string
    {
        if ($this->time === 0) {
            $this->time = time();
        }
        $dates = CalendarDate::findBySQL(
            "LEFT JOIN `calendar_date_assignments`
                ON `calendar_dates`.`id` = `calendar_date_assignments`.`calendar_date_id`
            WHERE
                `calendar_date_assignments`.`range_id` = :range_id
                AND (
                    (`calendar_dates`.`begin` <= :end
                        AND `calendar_dates`.`end` >= :begin)
                    OR (`calendar_dates`.`repetition_type` != 'SINGLE'
                        AND (`calendar_dates`.`repetition_end` >= :begin
                            OR `calendar_dates`.`repetition_end` = 0)
                    AND `calendar_dates`.`begin` < :end))",
            [
                ':range_id' => $range_id,
                ':begin'    => $start->getTimestamp(),
                ':end'      => $end->getTimestamp(),
            ]
        );
        $ical = '';
        foreach ($dates as $date) {
            $ical .= $this->writeICalEvent($this->prepareCalendarDate($date));
        }
        return $ical;
    }

    public function exportCourseDates(string $user_id, DateTimeInterface $start, DateTimeInterface $end)
    {
        if ($this->time === 0) {
            $this->time = time();
        }
        $dates = CalendarCourseDate::getEvents($start, $end, $user_id);
        $ical = '';
        foreach ($dates as $date) {
            $ical .= $this->writeICalEvent($this->prepareCourseDate($date));
        }
        return $ical;
    }

    public function exportCourseExDates(string $user_id, DateTimeInterface $start, DateTimeInterface $end)
    {
        if ($this->time === 0) {
            $this->time = time();
        }
        $dates = CalendarCourseExDate::getEvents($start, $end, $user_id);
        $ical = '';
        foreach ($dates as $date) {
            $ical .= $this->writeICalEvent($this->prepareCourseDate($date));
        }
        return $ical;
    }

    /**
     * @param CalendarDate $date The calendar date to export.
     * @return array Calendar date data prepared for export.
     */
    public function prepareCalendarDate(CalendarDate $date): array
    {

        return [
            'SUMMARY'       => $date->title,
            'DESCRIPTION'   => $date->description,
            'LOCATION'      => $date->location,
            'CATEGORIES'    => $date->getCategoryAsString(),
            'LAST-MODIFIED' => $date->chdate,
            'CREATED'       => $date->mkdate,
            'DTSTAMP'       => $this->time,
            'DTSTART'       => $date->begin,
            'DTEND'         => $date->end,
            'EXDATE'        => implode(',', $date->exceptions->pluck('date')),
            'PRIORITY'      => 5,
            'RRULE'         => [
                'type'      => $date->repetition_type,
                'offset'    => $date->offset,
                'interval'  => $date->interval,
                'days'      => $date->days,
                'count'     => $date->number_of_dates,
                'expire'    => $date->repetition_end,
                'month'     => $date->month
            ],
            'UID'           => $date->unique_id
        ];
    }

    /**
     * @param CourseDate | CourseExDate $date The course date to export.
     * @return array Course date data prepared for export.
     */
    public function prepareCourseDate($date): array
    {
        $summary = $date->course->getFullName();
        $categories = $date->getTypeName();
        if ($date instanceof CourseExDate) {
            $summary .= ' ' . _('(fällt aus)');
            $categories = '';
            $description = $date->content;
        } else {
            $description = implode("\n", $date->topics->pluck('title'));
        }
        return [
            'SUMMARY'       => $summary,
            'DESCRIPTION'   => $description,
            'LOCATION'      => $date->getRoomNames(),
            'CATEGORIES'    => $categories,
            'LAST-MODIFIED' => $date->chdate,
            'CREATED'       => $date->mkdate,
            'DTSTAMP'       => $this->time,
            'DTSTART'       => $date->date,
            'DTEND'         => $date->end_time,
            'PRIORITY'      => '',
            'UID'           => 'Stud.IP-SEM-' . $date->id . '@' . ($_SERVER['SERVER_NAME'] ?? '')
        ];
    }

    /**
     * Returns an iCalendar header with a rudimentary time zone definition.
     *
     * @return string The iCalendar header.
     */
    public function writeHeader()
    {
        // Default values
        $header = "BEGIN:VCALENDAR" . self::NEWLINE;
        $header .= "VERSION:2.0" . self::NEWLINE;
        if (isset($this->client_identifier)) {
            $header .= "PRODID:" . $this->client_identifier . self::NEWLINE;
        } else {
            $server_name = $_SERVER['SERVER_NAME'] ?? 'unknown';

            $header .= "PRODID:-//Stud.IP@{$server_name}//Stud.IP_iCalendar Library";
            $header .= " //EN" . self::NEWLINE;
        }
        $header .= "METHOD:PUBLISH" . self::NEWLINE;

        // time zone definition CET/CEST
        $header .= 'CALSCALE:GREGORIAN' . self::NEWLINE
            . 'BEGIN:VTIMEZONE' . self::NEWLINE
            . 'TZID:Europe/Berlin' . self::NEWLINE
            . 'BEGIN:DAYLIGHT' . self::NEWLINE
            . 'TZOFFSETFROM:+0100' . self::NEWLINE
            . 'TZOFFSETTO:+0200' . self::NEWLINE
            . 'TZNAME:CEST' . self::NEWLINE
            . 'DTSTART:19700329T020000' . self::NEWLINE
            . 'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3' . self::NEWLINE
            . 'END:DAYLIGHT' . self::NEWLINE
            . 'BEGIN:STANDARD' . self::NEWLINE
            . 'TZOFFSETFROM:+0200' . self::NEWLINE
            . 'TZOFFSETTO:+0100' . self::NEWLINE
            . 'TZNAME:CET' . self::NEWLINE
            . 'DTSTART:19701025T030000' . self::NEWLINE
            . 'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10' . self::NEWLINE
            . 'END:STANDARD' . self::NEWLINE
            . 'END:VTIMEZONE' .self::NEWLINE;

        return $header;
    }

    /**
     * Returns the footer.
     *
     * @return string
     */
    public function writeFooter()
    {
        return "END:VCALENDAR" . self::NEWLINE;
    }

    /**
     * Export prepared calendar data as iCalendar.
     *
     * @param array $properties The event to export.
     * @return string iCalendar formatted data
     */
    public function writeICalEvent(array $properties): string
    {
        $result = "BEGIN:VEVENT" . self::NEWLINE;

        foreach ($properties as $name => $value) {
            $params = [];
            $params_str = '';
            if ($value === '' || is_null($value)) {
                continue;
            }
            switch ($name) {
                // not supported event properties
                case 'SEMNAME':
                    continue 2;

                // Text fields
                case 'SUMMARY':
                    $value = $this->quoteText($value);
                    break;
                case 'DESCRIPTION':
                    $value = $this->quoteText($value);
                    break;
                case 'LOCATION':
                    $value = $this->quoteText($value);
                    break;
                case 'CATEGORIES':
                    $value = $this->quoteText($value);
                    break;

                // Date fields
                case 'LAST-MODIFIED':
                case 'CREATED':
                case 'COMPLETED':
                    $value = $this->_exportDateTime($value, true);
                    break;

                case 'DTSTAMP':
                    $value = $this->_exportDateTime(time(), true);
                    break;

                case 'DTSTART':
                    $exdate_time = $value;
                    $dtstart = (new DateTime())->setTimestamp($value);
                    $dtend   = (new DateTime())->setTimestamp($properties['DTEND']);

                    $isAllDay = $dtstart->format('His') === '000000'
                        && $dtend->format('His') === '235959';

                    if ($isAllDay) {
                        $value = $dtstart->format('Ymd');
                        $params_str = ';VALUE=DATE';
                    } else {
                        $value = $this->_exportDateTime($value);
                        $params_str = ';TZID=Europe/Berlin';
                    }
                    break;
                case 'DTEND':
                    $dt = (new DateTime())->setTimestamp($value);
                    if ($dt->format('His') === '000000' || $dt->format('His') === '235959') {
                        if ($name === 'DTEND') {
                            $dt->modify('+1 day');
                        }
                        $value = $dt->format('Ymd');
                        $params_str = ';VALUE=DATE';
                    } else {
                        $value = $this->_exportDateTime($value);
                        $params_str = ';TZID=Europe/Berlin';
                    }
                    break;

                case 'DUE':

                case 'RECURRENCE-ID':
                    if (array_key_exists('VALUE', $params)) {
                        if ($params['VALUE'] === 'DATE') {
                            $value = $this->_exportDate($value);
                        } else {
                            $value = $this->_exportDateTime($value);
                            $params_str = ';TZID=Europe/Berlin';
                        }
                    } else {
                        $value = $this->_exportDateTime($value);
                        $params_str = ';TZID=Europe/Berlin';
                    }
                    break;

                case 'EXDATE':
                    if (array_key_exists('VALUE', $params) && $params['VALUE'] === 'DATE') {
                        $value = $this->exportExDate($value);
                    } else {
                        $value = $this->exportExDateTime($value, $exdate_time);
                        $params_str = ';TZID=Europe/Berlin';
                    }
                    break;

                // Integer fields
                case 'PERCENT-COMPLETE':
                case 'REPEAT':
                case 'SEQUENCE':
                    $value = "$value";
                    break;

                case 'PRIORITY':
                    switch ($value) {
                        case 1:
                            $value = '1';
                            break;
                        case 2:
                            $value = '5';
                            break;
                        case 3:
                            $value = '9';
                            break;
                        default:
                            $value = '0';
                    }
                    break;

                // Geo fields
                case 'GEO':
                        $value = $value['latitude'] . ',' . $value['longitude'];
                    break;

                // Recursion fields
                case 'EXRULE':
                case 'RRULE':
                    if ($value['type'] !== 'SINGLE' && $value['type'] !== '') {
                        $value = $this->_exportRecurrence($value);
                    }
                    break;

                case "UID":
                    $value = "$value";
            }
            if ($name && !is_array($value)) {
                $attr_string = $name . $params_str . ':' . $value;
                $result .= $this->foldLine($attr_string) . self::NEWLINE;
            }
        }
        if (isset($properties['GROUP_EVENT'])) {
            $result .= $this->exportGroupEventProperties($properties['GROUP_EVENT']);
        }
        $result .= "END:VEVENT" . self::NEWLINE;

        return $result;
    }

    /**
     * Quotes some characters accordingly to iCalendar format.
     *
     * @param string $text The text to quote.
     * @return string The quoted text.
     */
    public function quoteText(string $text): string
    {
        $match = ['\\', '\n', ';', ','];
        $replace = ['\\\\', '\\n', '\;', '\,'];
        return str_replace($match, $replace, $text);
    }

    /**
     * Export a DateTime field
     *
     * @param int $value Unix timestamp
     * @return String Date and time (UTC) iCalendar formatted
     */
    public function _exportDateTime($value, $utc = false)
    {
        $date_time = new DateTime();
        $date_time->setTimestamp(intval($value));
        //transform local time to UTC
        if ($utc) {
            $tz_utc = new DateTimeZone('UTC');
            $date_time->setTimezone($tz_utc);
            return $date_time->format('Ymd\THis\Z');
        }
        return $date_time->format('Ymd\THis');
    }

    /**
     * Export a Time field
     *
     * @param int $value Unix timestamp
     * @return String Time (UTC) iCalendar formatted
     */
    public function _exportTime($value, $utc = false)
    {
        $time = date("His", $value);
        if ($utc) {
            $time .= 'Z';
        }

        return $time;
    }

    /**
     * Export a Date field
     */
    public function _exportDate($value)
    {
        return date("Ymd", $value);
    }

    /**
     * Export a recurrence rule
     */
    public function _exportRecurrence($value)
    {
        $rrule = [];
        // the last day of week in a MONTHLY or YEARLY recurrence in the
        // Stud.IP calendar is 5, in iCalendar it is -1
        if ($value['offset'] == '5') {
            $value['offset'] = '-1';
        }

        if ($value['count'] > 1) {
            unset($value['expire']);
        }

        foreach ($value as $r_param => $r_value) {
            if ($r_value) {
                switch ($r_param) {
                    case 'type':
                        $rrule[] = 'FREQ=' . $r_value;
                        break;
                    case 'expire':
                        if ($r_value)
                            $rrule[] = 'UNTIL=' . $this->_exportDateTime($r_value, true);
                        break;
                    case 'interval':
                        $rrule[] = 'INTERVAL=' . $r_value;
                        break;
                    case 'days':
                        switch ($value['type']) {
                            case 'WEEKLY':
                                $rrule[] = 'BYDAY=' . $this->_exportWdays($r_value);
                                break;
                            // Some CUAs (e.g. Outlook) don't understand the nWDAY syntax
                            // (where n is the nth ocurrence of the day in a given period of
                            // time and WDAY is the day of week) the RRULE uses the BYSETPOS
                            // rule.
                            case 'MONTHLY':
                            case 'YEARLY':
                                $rrule[] = 'BYDAY=' . $value['offset'] . $this->_exportWdays($r_value);
                                $rrule[] = 'BYDAY=' . $this->_exportWdays($r_value);
                                if ($value['offset']) {
                                    $rrule[] = 'BYSETPOS=' . $value['offset'];
                                }
                                break;
                        }
                        break;
                    case 'day':
                        $rrule[] = 'BYMONTHDAY=' . $r_value;
                        break;
                    case 'month':
                        $rrule[] = 'BYMONTH=' . $r_value;
                        break;
                    case 'count':
                        if ($r_value > 1) {
                            $rrule[] = 'COUNT=' . $r_value;
                        }
                        break;
                }
            }
        }

        if ($value['type'] === 'WEEKLY' && self::WEEKSTART != 'MO') {
            $rrule[] = 'WKST=' . self::WEEKSTART;
        }

        return implode(';', $rrule);
    }

    /**
     * Return the days from CalendarDate::days as attribute of a event recurrence.
     *
     * @param string $value
     * @return string
     */
    public function _exportWdays(string $value): string
    {
        $wdays_map = ['1' => 'MO', '2' => 'TU', '3' => 'WE', '4' => 'TH', '5' => 'FR',
            '6' => 'SA', '7' => 'SU'];
        $wdays = [];
        preg_match_all('/(\d)/', $value, $matches);
        foreach ($matches[1] as $match) {
            $wdays[] = $wdays_map[$match];
        }
        return implode(',', $wdays);
    }


    /**
     * Formats dates of exception.
     *
     * @param string $value Date values (Y-m-d) as csv list.
     * @return string The formatted Exceptions.
     */
    public function exportExDate(string $value): string
    {
        $ex_dates = [];
        $dates = explode(',', $value);
        foreach ($dates as $date) {
            $ex_datetime = $date . ' 12:00:00';
            $ex_date = DateTime::createFromFormat('Y-m-d H:i:s', $ex_datetime);
            $ex_dates[] = $this->_exportDate($ex_date->getTimestamp());
        }

        return implode(',', $ex_dates);
    }

    /**
     * Formats date times of exception.
     *
     * @param string $value Date values (Y-m-d) as csv list.
     * @param int $begin Start date of event as unix timestamp.
     * @return string The formatted Exceptions.
     */
    public function exportExDateTime(string $value, int $begin): string
    {
        $ex_dates = [];
        $dates = explode(',', $value);
        foreach ($dates as $date) {
            $ex_datetime = $date . date(' H:i:s', $begin);
            $date_time = DateTime::createFromFormat('Y-m-d H:i:s', $ex_datetime);
            $ex_dates[] = $date_time->format('Ymd\THis');
        }
        return implode(',', $ex_dates);
    }

    /**
     * Returns iCalendar group event properties if the date has mor than one attendee.
     *
     * @param CalendarDate $date The date object to extract the group data from.
     * @return string The formatted group event properties.
     */
    private function exportGroupEventProperties(CalendarDate $date): string
    {
        if (!count($date->calendars)) {
            return '';
        }
        $organizer = $date->author;
        if ($organizer) {
            $properties = $this->foldLine('ORGANIZER;CN="'
                    . $organizer->getFullName()
                    . '":mailto:' . $organizer->Email)
                . self::NEWLINE;
        } else {
            $properties = $this->foldLine('ORGANIZER;CN="'
                    . _('unbekannt')
                    . '":mailto:' . $GLOBALS['user']->email)
                . self::NEWLINE;
        }
        foreach ($date->calendars as $calendar) {
            if ($date->author_id === $calendar->range_id) {
                if ($calendar->user) {
                    $properties .= $this->foldLine('ATTENDEE;'
                            . 'ROLE=REQ-PARTICIPANT;'
                            . 'CN="' . $calendar->user->getFullName()
                            . '":mailto:' . $calendar->user->Email)
                        . self::NEWLINE;
                } else {
                    $properties = '';
                }
            } else {
                if ($calendar->user) {
                    switch ($calendar->participation) {
                        case 'ACCEPTED' :
                            $attendee = 'ATTENDEE;ROLE=REQ-PARTICIPANT'
                                . ';PARTSTAT=ACCEPTED';
                            break;
                        case 'ACKNOWLEDGED' :
                            $attendee = 'ATTENDEE;ROLE=NON-PARTICIPANT'
                                . ';PARTSTAT=ACCEPTED'
                                . ';DELEGATED-TO="mailto:'
                                . $this->getFacultyEmail($organizer->id)
                                . '"';
                            break;
                        case 'DECLINED' :
                            $attendee = 'ATTENDEE;ROLE=REQ-PARTICIPANT'
                                . ';PARTSTAT=DECLINED';
                            break;
                        default :
                            $attendee = 'ATTENDEE;ROLE=REQ-PARTICIPANT';
                            $attendee .= ';PARTSTAT=TENTATIVE';
                            $attendee .= ';RSVP=TRUE';

                    }
                    $attendee .= ';CN="' . $calendar->user->getFullName()
                        . '":mailto:' . $calendar->user->Email;
                    $properties .= $this->foldLine($attendee) . self::NEWLINE;
                }
            }
        }
        return $properties;
    }

    /**
     * @param string $user_id
     * @return string
     */
    private function getFacultyEmail(string $user_id): string
    {
        return DBManager::get()->fetchColumn('
            SELECT `email`
            FROM `Institute`
            LEFT JOIN `user_inst` USING(`institut_id`)
            WHERE `Institute`.`Institut_id` = `fakultaets_id`
              AND `user_id` = ?', [$user_id]);
    }

    /**
     * Returns the folded version of a text line.
     *
     * @param string $line
     * @return string
     */
    private function foldLine(string $line): string
    {
        $line = preg_replace('/(\r\n|\n|\r)/', '\n', $line);
        if (mb_strlen($line) > 75) {
            $foldedline = '';
            while ($line !== '') {
                $maxLine = mb_substr($line, 0, 75);
                $cutPoint = max(60, max(mb_strrpos($maxLine, ';'), mb_strrpos($maxLine, ':')) + 1);

                $foldedline .= ( empty($foldedline)) ?
                    mb_substr($line, 0, $cutPoint) :
                    self::NEWLINE . ' ' . mb_substr($line, 0, $cutPoint);

                $line = (mb_strlen($line) <= $cutPoint) ? '' : mb_substr($line, $cutPoint);
            }
            return $foldedline;
        }
        return $line;
    }
}
