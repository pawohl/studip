<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author     Rasmus Fuhse <fuhse@data-quest.de>
 * @copyright   2014 Stud.IP Core-Group
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 *
 * @property string $id alias column for termin_id
 * @property string $termin_id database column
 * @property string $range_id database column
 * @property string $autor_id database column
 * @property string $content database column
 * @property int $date database column
 * @property int $end_time database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property int $date_typ database column
 * @property string|null $raum database column
 * @property string|null $metadate_id database column
 * @property string $resource_id database column
 * @property User $author belongs_to User
 * @property Course $course belongs_to Course
 * @property SeminarCycleDate|null $cycle belongs_to SeminarCycleDate
 * @property-read mixed $topics additional field
 * @property-read mixed $statusgruppen additional field
 * @property-read mixed $dozenten additional field
 * @property-read mixed $room_booking additional field
 * @property-read mixed $room_request additional field
 */

class CourseExDate extends SimpleORMap implements PrivacyObject, Event
{
    /**
     * Configures this model.
     *
     * @param Array $config Configuration array
     */
    protected static function configure($config = [])
    {
        $config['db_table'] = 'ex_termine';
        $config['belongs_to']['author'] = [
            'class_name'  => User::class,
            'foreign_key' => 'autor_id'
        ];
        $config['belongs_to']['course'] = [
            'class_name'  => Course::class,
            'foreign_key' => 'range_id'
        ];
        $config['belongs_to']['cycle'] = [
            'class_name'  => SeminarCycleDate::class,
            'foreign_key' => 'metadate_id'
        ];

        $dummy_relation = function () { return new SimpleCollection(); };
        $dummy_null = function () { return null; };
        $config['additional_fields']['topics']['get'] = $dummy_relation;
        $config['additional_fields']['statusgruppen']['get'] = $dummy_relation;
        $config['additional_fields']['dozenten']['get'] = $dummy_relation;
        $config['additional_fields']['room_booking']['get'] = $dummy_null;
        $config['additional_fields']['room_request']['get'] = $dummy_null;
        $config['default_values']['date_typ'] = 1;
        parent::configure($config);
    }

    const FORMAT_DEFAULT = 'default';
    const FORMAT_VERBOSE = 'verbose';

    /**
     * Returns course dates by course id
     *
     * @param String $seminar_id Id of the course
     * @return array with the associated dates
     */
    public static function findBySeminar_id($seminar_id)
    {
        return self::findByRange_id($seminar_id);
    }

    /**
     * Return course dates by range id (which is in many cases the course id)
     *
     * @param String $seminar_id Id of the course
     * @param String $order_by   Optional order definition
     * @return array with the associated dates
     */
    public static function findByRange_id($seminar_id, $order_by = 'ORDER BY date')
    {
        return parent::findByRange_id($seminar_id, $order_by);
    }
    /**
     * Returns the name of the assigned room for this date.
     *
     * @return String that is always empty
     */
    public function getRoomNames()
    {
        return '';
    }

    /**
     * @see CourseExDate::getRoomNames
     *
     * @deprecated Will be removed in Stud.IP 7.0. Please use getRoomNames.
     */
    public function getRoomName()
    {
        return '';
    }

    /**
     * Returns the assigned rooms for this date as an object.
     *
     * @return array An empty array, always. Cancelled dates need no rooms.
     */
    public function getRooms()
    {
        return [];
    }

    /**
     * Returns the assigned room for this date as an object.
     *
     * @return null. always. canceled dates need no room.
     *
     * @deprecated Will be removed in Stud.IP 7.0. Please use getRooms.
     */
    public function getRoom()
    {
        return null;
    }

    /**
     * Returns the name of the type of this date.
     *
     * @param String containing the type name
     */
    public function getTypeName()
    {
        return $GLOBALS['TERMIN_TYP'][$this->date_typ]['name'];
    }

    /**
     * Returns the full qualified name of this date.
     *
     * @param string $format Optional format type (only 'default' and
     *                       'verbose' are supported by now)
     * @return string containing the full name of this date.
     */
    public function getFullName(string $format = self::FORMAT_DEFAULT)
    {
        if (!$this->date || !in_array($format, [self::FORMAT_DEFAULT, self::FORMAT_VERBOSE])) {
            return '';
        }

        if (($this->end_time - $this->date) / 60 / 60 > 23) {
            $date_string = studip_interpolate(
                $format === self::FORMAT_VERBOSE
                    ? _('%{weekday}, %{date}, %{start} - %{end} Uhr (ganztägig, fällt aus)')
                    : _('%{weekday}, %{date}, %{start} - %{end} (ganztägig, fällt aus)')
                ,
                [
                    'weekday' => getWeekday(date('N', $this->date)),
                    'date'    => date('d.m.y', $this->date),
                    'start'   => date('H:i', $this->date),
                    'end'     => date('H:i', $this->end_time),
                ]
            );
            return $date_string;
        }

        $date_string = studip_interpolate(
            $format === self::FORMAT_VERBOSE
                ? _('%{weekday}, %{date}, %{start} - %{end} Uhr (fällt aus)')
                : _('%{weekday}, %{date}, %{start} - %{end} (fällt aus)')
            ,
            [
                'weekday' => getWeekday(date('N', $this->date)),
                'date'    => date('d.m.y', $this->date),
                'start'   => date('H:i', $this->date),
                'end'     => date('H:i', $this->end_time),
            ]
        );

        return $date_string;
    }

    /**
     * Converts a CourseExDate Entry to a CourseDate Entry
     * returns instance of the new CourseDate or NULL
     * @return Object CourseDate
     */
    public function unCancelDate()
    {
        //NOTE: If you modify this method make sure the changes
        //are also inserted in SingleDateDB::storeSingleDate
        //and CourseDate::cancelDate to keep the behavior consistent
        //across Stud.IP!

        //These statements are used below to update the relations
        //of this ex-date.
        $db = DBManager::get();

        $groups_stmt = $db->prepare(
            "UPDATE termin_related_groups
            SET termin_id = :termin_id
            WHERE termin_id = :ex_termin_id;"
        );

        $persons_stmt = $db->prepare(
            "UPDATE termin_related_persons
            SET range_id = :termin_id
            WHERE range_id = :ex_termin_id;"
        );

        $ex_date = $this->toArray();

        //REMOVE content
        unset($ex_date['content']);

        $date = new CourseDate();
        $date->setData($ex_date);
        $date->setId($date->getNewId());

        if ($date->store()) {
            //Update the relations to the ex-date so that they
            //use the ID of the new date.

            $groups_stmt->execute(
                [
                    'termin_id' => $date->id,
                    'ex_termin_id' => $this->id
                ]
            );

            $persons_stmt->execute(
                [
                    'termin_id' => $date->id,
                    'ex_termin_id' => $this->id
                ]
            );

            //After we updated the relations so that they refer to the
            //new date we can delete this ex-date and return the date:

            StudipLog::log('SEM_UNDELETE_SINGLEDATE', $this->termin_id, $this->range_id, 'Cycle_id: ' . $this->metadate_id);
            $this->delete();
            return $date;
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
        $sorm = self::findBySQL("autor_id = ?", [$storage->user_id]);
        if ($sorm) {
            $field_data = [];
            foreach ($sorm as $row) {
                $field_data[] = $row->toRawArray();
            }
            if ($field_data) {
                $storage->addTabularData(_('ausgefallende Termine'), 'ex_termine', $field_data);
            }
        }
    }


    /**
     * @return string A string representation of the course date.
     */
    public function __toString() : string
    {
        return sprintf(
            _('Ausgefallener Termin am %1$s, %2$s von %3$s bis %4$s Uhr'),
            strftime('%A', $this->date),
            strftime('%x', $this->date),
            date('H:i', $this->date),
            date('H:i', $this->end_time)
        );
    }

    //Start of Event interface implementation.

    public static function getEvents(DateTime $begin, DateTime $end, string $range_id): array
    {
        return self::findBySQL(
            "JOIN `seminar_user`
               ON `seminar_user`.`seminar_id` = `ex_termine`.`range_id`
             WHERE `seminar_user`.`user_id` = :user_id
               AND `ex_termine`.`date` BETWEEN :begin AND :end
               AND (
                   IFNULL(`ex_termine`.`metadate_id`, '') = ''
                   OR `ex_termine`.`metadate_id` NOT IN (
                       SELECT `metadate_id`
                       FROM `schedule_courses`
                       WHERE `user_id` = :user_id
                         AND `visible` = 0
                   )
               )
             ORDER BY date",
            [
                'begin'   => $begin->getTimestamp(),
                'end'     => $end->getTimestamp(),
                'user_id' => $range_id
            ]
        );
    }

    //Event interface implementation:

    public function getObjectId() : string
    {
        return (string) $this->id;
    }

    public function getPrimaryObjectID(): string
    {
        return $this->range_id;
    }

    public function getObjectClass(): string
    {
        return static::class;
    }

    public function getTitle(): string
    {
        return sprintf('%s (fällt aus)', $this->course->name ?? '');
    }

    public function getBegin(): DateTime
    {
        $begin = new DateTime();
        $begin->setTimestamp($this->date);
        return $begin;
    }

    public function getEnd(): DateTime
    {
        $end = new DateTime();
        $end->setTimestamp($this->end_time);
        return $end;
    }

    public function getDuration(): DateInterval
    {
        $begin = $this->getBegin();
        $end = $this->getEnd();
        return $end->diff($begin);
    }

    public function getLocation(): string
    {
        return $this->getRoomNames();
    }

    public function getUniqueId(): string
    {
        return sprintf('Stud.IP-SEM-%1$s@%2$s', $this->id, $_SERVER['SERVER_NAME']);
    }

    public function getDescription(): string
    {
        return trim($this->getValue('content'));
    }

    public function getAdditionalDescriptions(): array
    {
        $descriptions = [];
        if (count($this->dozenten) > 0) {
            $descriptions[_('Durchführende Lehrende')] = implode(', ', $this->dozenten->getFullName());
        }
        if (count($this->statusgruppen) > 0) {
            $descriptions[_('Beteiligte Gruppen')] = implode(', ', $this->statusgruppen->getValue('name'));
        }
        return $descriptions;
    }

    public function isAllDayEvent(): bool
    {
        //Course dates are never all day events.
        return false;
    }

    public function isWritable(string $user_id): bool
    {
        return $GLOBALS['perm']->have_studip_perm('dozent', $this->range_id, $user_id);
    }

    public function getCreationDate(): DateTime
    {
        $mkdate = new DateTime();
        $mkdate->setTimestamp($this->mkdate);
        return $mkdate;
    }

    public function getModificationDate(): DateTime
    {
        $chdate = new DateTime();
        $chdate->setTimestamp($this->chdate);
        return $chdate;
    }

    public function getImportDate(): DateTime
    {
        return $this->getCreationDate();
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getEditor(): ?User
    {
        return null;
    }

    public function toEventData(string $user_id): \Studip\Calendar\EventData
    {
        $begin = new DateTime();
        $begin->setTimestamp($this->date);
        $end = new DateTime();
        $end->setTimestamp($this->end_time);

        $studip_view_urls = [];
        if ($GLOBALS['perm']->have_studip_perm('user', $this->range_id, $user_id)) {
            $studip_view_urls['show'] = URLHelper::getURL('dispatch.php/course/details', ['cid' => $this->range_id, 'link_to_course' => '1']);
        }

        return new \Studip\Calendar\EventData(
            $begin,
            $end,
            $this->getTitle(),
            [],
            '#000000',
            '#aaaaaa',
            $this->isWritable($user_id),
            CourseExDate::class,
            $this->id,
            Course::class,
            $this->range_id,
            'course',
            $this->range_id,
            $studip_view_urls,
            [],
            'seminar',
            'rgba(0,0,0,0)'
        );
    }

    //End of Event interface implementation.
}
