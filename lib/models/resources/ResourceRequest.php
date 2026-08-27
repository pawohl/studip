<?php

/**
 * ResourceRequest.php - Contains a model class for resource requests.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Moritz Strohm <strohm@data-quest.de>
 * @copyright   2017-2025
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     resources
 * @since       4.5
 *
 * The attributes begin and end are only used in simple resource requests.
 * The "traditional" resource requests use either course_id, metadate_id
 * or termin_id to store the time ranges connected to the request.
 *
 * @property string $id database column
 * @property string $course_id database column
 * @property string $termin_id database column
 * @property string $metadate_id database column
 * @property string $user_id database column
 * @property string $last_modified_by database column
 * @property string $resource_id database column
 * @property string|null $category_id database column
 * @property string|null $comment database column
 * @property string|null $reply_comment database column
 * @property string|null $booking_text database column
 * @property string $reply_recipients database column
 * @property int $closed database column
 * @property int|null $mkdate database column
 * @property int|null $chdate database column
 * @property int $begin database column
 * @property int $end database column
 * @property int $preparation_time database column
 * @property int $subsequent_time database column
 * @property int $marked database column
 * @property SimpleORMapCollection<ResourceRequestProperty> $properties has_many ResourceRequestProperty
 * @property SimpleORMapCollection<ResourceRequestAppointment> $appointments has_many ResourceRequestAppointment
 * @property Resource $resource belongs_to Resource
 * @property ResourceCategory|null $category belongs_to ResourceCategory
 * @property User $user belongs_to User
 * @property User $last_modifier belongs_to User
 * @property Course $course belongs_to Course
 * @property SeminarCycleDate $cycle belongs_to SeminarCycleDate
 * @property CourseDate $date belongs_to CourseDate
 */
class ResourceRequest extends SimpleORMap implements PrivacyObject, Studip\Calendar\EventSource
{
    const MARK_NONE = 0;
    const MARK_RED = 1;
    const MARK_YELLOW = 2;
    const MARK_GREEN = 3;

    const REPLY_REQUESTER = 'requester';
    const REPLY_LECTURER = 'lecturer';

    const STATE_OPEN = 0; // room-request is open
    const STATE_PENDING = 1; // room-request has been processed, but no confirmation has been sent
    const STATE_CLOSED = 2; // room-request has been processed and a confirmation has been sent
    const STATE_DECLINED = 3; // room-request has been declined

    /**
     * The amount of defined marking states.
     */
    const MARKING_STATES = 4;

    protected static function configure($config = [])
    {
        $config['db_table'] = 'resource_requests';

        $config['belongs_to']['resource'] = [
            'class_name'  => Resource::class,
            'foreign_key' => 'resource_id',
            'assoc_func'  => 'find'
        ];

        $config['belongs_to']['category'] = [
            'class_name'  => ResourceCategory::class,
            'foreign_key' => 'category_id',
            'assoc_func'  => 'find'
        ];

        $config['belongs_to']['user'] = [
            'class_name'  => User::class,
            'foreign_key' => 'user_id',
            'assoc_func'  => 'find'
        ];

        $config['belongs_to']['last_modifier'] = [
            'class_name'  => User::class,
            'foreign_key' => 'last_modified_by',
            'assoc_func'  => 'find'
        ];

        $config['belongs_to']['course'] = [
            'class_name'  => Course::class,
            'foreign_key' => 'course_id',
            'assoc_func'  => 'find'
        ];

        $config['belongs_to']['cycle'] = [
            'class_name'  => SeminarCycleDate::class,
            'foreign_key' => 'metadate_id'
        ];

        $config['belongs_to']['date'] = [
            'class_name'  => CourseDate::class,
            'foreign_key' => 'termin_id'
        ];

        $config['has_many']['properties'] = [
            'class_name'        => ResourceRequestProperty::class,
            'foreign_key'       => 'id',
            'assoc_foreign_key' => 'request_id',
            'on_store'          => 'store',
            'on_delete'         => 'delete'
        ];

        $config['has_many']['appointments'] = [
            'class_name'        => ResourceRequestAppointment::class,
            'foreign_key'       => 'id',
            'assoc_foreign_key' => 'request_id',
            'on_store'          => 'store',
            'on_delete'         => 'delete'
        ];

        //In regard to TIC 6460:
        //As long as TIC 6460 is not implemented, we must add the validate
        //method as a callback before storing the object.
        if (!method_exists('SimpleORMap', 'validate')) {
            $config['registered_callbacks']['before_store'][] = 'validate';
        }
        $config['registered_callbacks']['after_create'][] = 'cbLogNewRequest';
        $config['registered_callbacks']['before_store'][] = 'cbBeforeStore';
        $config['registered_callbacks']['after_store'][] = 'cbAfterStore';
        $config['registered_callbacks']['after_delete'][] = 'cbAfterDelete';


        parent::configure($config);
    }

    /**
     * @inheritDoc
     */
    public static function exportUserdata(StoredUserData $storage)
    {
        $user = User::find($storage->user_id);

        $requests = self::findBySql(
            'user_id = :user_id ORDER BY mkdate',
            [
                'user_id' => $storage->user_id
            ]
        );

        $request_rows = [];
        foreach ($requests as $request) {
            $request_rows[] = $request->toRawArray();
        }
        $storage->addTabularData(
            _('Ressourcenanfragen'),
            'resource_requests',
            $request_rows,
            $user
        );
    }

    /**
     * Retrieves all resource requests from the database.
     *
     * @return ResourceRequest[] An array of ResourceRequests objects
     *     or an empty array, if no resource requests are stored
     *     in the database.
     */
    public static function findAll()
    {
        return self::findBySql('TRUE ORDER BY mkdate ASC');
    }

    /**
     * Retrieves all open resource requests from the database.
     *
     * @return ResourceRequest[] An array of ResourceRequests objects
     *     or an empty array, if no open resource requests are stored
     *     in the database.
     */
    public static function findOpen()
    {
        return self::findBySql(
            'closed = ? ORDER BY mkdate ASC',
            [self::STATE_OPEN]
        );
    }

    /**
     * Internal method that generated the SQL query used in
     * findByResourceAndTimeRanges and countByResourceAndTimeRanges.
     *
     * @see findByResourceAndTimeRanges
     * @inheritDoc
     */
    protected static function buildResourceAndTimeRangesSqlQuery(
        Resource $resource,
        $time_ranges = [],
        $closed_status = null,
        $excluded_request_ids = [],
        $additional_conditions = '',
        $additional_parameters = []
    )
    {
        if (!is_array($time_ranges)) {
            throw new InvalidArgumentException(
                _('Es wurde keine Liste mit Zeiträumen angegeben!')
            );
        }

        //Check the array:
        foreach ($time_ranges as $time_range) {
            if ($time_range['begin'] > $time_range['end']) {
                throw new InvalidArgumentException(
                    _('Der Startzeitpunkt darf nicht hinter dem Endzeitpunkt liegen!')
                );
            }

            if ($time_range['begin'] == $time_range['end']) {
                throw new InvalidArgumentException(
                    _('Startzeitpunkt und Endzeitpunkt dürfen nicht identisch sein!')
                );
            }
        }

        $sql_params = [
            'resource_id' => $resource->id
        ];

        //First we build the SQL snippet for the case that the $closed_status
        //variable is set to something different than null.
        $closed_status_sql = '';
        if ($closed_status !== null) {
            $closed_status_sql    = ' AND (resource_requests.closed = :status) ';
            $sql_params['status'] = strval($closed_status);
        }

        //Then we build the snipped for excluded request IDs, if specified.
        $excluded_request_ids_sql = '';
        if (is_array($excluded_request_ids) && count($excluded_request_ids)) {
            $excluded_request_ids_sql   = ' AND resource_requests.id NOT IN ( :excluded_ids ) ';
            $sql_params['excluded_ids'] = $excluded_request_ids;
        }

        //Now we build the SQL snippet for the time intervals.
        //These are repeated four times in the query below.
        //BEGIN and END are replaced below since the columns for
        //BEGIN and END are different in the four cases where we
        //repeat the SQL snippet for the time intervals.

        $time_sql = '';
        if ($time_ranges) {
            $time_sql = 'AND (';

            $i = 1;
            foreach ($time_ranges as $time_range) {
                if ($i > 1) {
                    $time_sql .= ' OR ';
                }
                $time_sql .= sprintf('BEGIN < :end%d AND END > :begin%d ', $i, $i);

                $sql_params[('begin' . $i)] = $time_range['begin'];
                $sql_params[('end' . $i)]   = $time_range['end'];

                $i++;
            }

            $time_sql .= ') ';
        }

        //Check if the request has a start and end timestamp set or if it belongs
        //to a date, a metadate or a course.
        //This is done in the rest of the SQL query:

        // FIXME this subselect looks unnecessarily complex
        $whole_sql = '
                SELECT `id` FROM `resource_requests`
                WHERE
                `resource_id` = :resource_id
        '
            . str_replace(
                ['BEGIN', 'END'],
                [
                    '(CAST(`begin` AS SIGNED) - CAST(`preparation_time` AS SIGNED))',
                    '(CAST(`end` AS SIGNED) + CAST(`subsequent_time` AS SIGNED))'
                ],
                $time_sql
            )
            . $closed_status_sql
            . '
                UNION
                SELECT `id` FROM `resource_requests`
                INNER JOIN `termine` USING (`termin_id`)
                WHERE
                `resource_id` = :resource_id
                   '
            . str_replace(
                ['BEGIN', 'END'],
                [
                    '(CAST(`termine`.`date` AS SIGNED) - CAST(`resource_requests`.`preparation_time` AS SIGNED))',
                    '(CAST(`termine`.`end_time` AS SIGNED) + CAST(`resource_requests`.`subsequent_time` AS SIGNED))'
                ],
                $time_sql
            )
            . $closed_status_sql
            . '
                UNION
                SELECT `id` FROM `resource_requests`
                INNER JOIN `termine` USING (`metadate_id`)
                WHERE
                `resource_id` = :resource_id
                   '
            . str_replace(
                ['BEGIN', 'END'],
                [
                    '(CAST(`termine`.`date` AS SIGNED) - CAST(`resource_requests`.`preparation_time` AS SIGNED))',
                    '(CAST(`termine`.`end_time` AS SIGNED) + CAST(`resource_requests`.`subsequent_time` AS SIGNED))',
                ],
                $time_sql
            )
            . $closed_status_sql
            . '
            UNION
            SELECT `id` FROM `resource_requests`
            INNER JOIN `termine`
            ON `resource_requests`.`course_id` = `termine`.`range_id`
            WHERE
            `resource_id` = :resource_id
                   '
            . str_replace(
                ['BEGIN', 'END'],
                [
                    '(`termine`.`date` - `resource_requests`.`preparation_time`)',
                    '(`termine`.`end_time` + `resource_requests`.`subsequent_time`)',
                ],
                $time_sql
            )
            . $closed_status_sql
            . '
            GROUP BY `id`
         '
            . $excluded_request_ids_sql;
        $request_ids = DBManager::get()->fetchFirst($whole_sql, $sql_params);
        $whole_sql = "`resource_requests`.`id` IN(:request_ids)";
        $sql_params = ['request_ids' => $request_ids];
        if ($additional_conditions) {
            $whole_sql .= ' AND ' . $additional_conditions;
            if ($additional_parameters) {
                $sql_params = array_merge($sql_params, $additional_parameters);
            }
        }
        $whole_sql .= ' ORDER BY `mkdate` ASC';

        return [
            'sql'    => $whole_sql,
            'params' => $sql_params
        ];
    }

    /**
     * Retrieves all resource requests for the given resource and
     * time range. By default, all requests are returned.
     * To get only open or closed requests set the $closed_status parameter.
     *
     * @param Resource $resource The resource whose requests shall be retrieved.
     * @param array $time_ranges An array with time ranges as DateTime objects.
     *     The array has the following structure:
     *     [
     *         [
     *             'begin' => begin timestamp,
     *             'end' => end timestamp
     *         ],
     *         ...
     *     ]
     * @param mixed $closed_status An optional status for the closed column in the
     *     database. By default this is set to null which means that
     *     resource requests are not filtered by the status column field.
     *     A value of 0 means only open requests are retrived.
     *     A value of 1 means only closed requests are retrieved.
     *
     * @param array $excluded_request_ids An array of strings representing
     *     resource request IDs. IDs specified in this array are excluded from
     *     the search.
     * @return ResourceRequest[] An array of ResourceRequest objects.
     *     If no requests can be found, the array is empty.
     *
     * @throws InvalidArgumentException, if the time ranges are either not in an
     *     array matching the format description from above or if one of the
     *     following conditions is met in one of the time ranges:
     *     - begin > end
     *     - begin == end
     */
    public static function findByResourceAndTimeRanges(
        Resource $resource,
        $time_ranges = [],
        $closed_status = null,
        $excluded_request_ids = [],
        $additional_conditions = '',
        $additional_parameters = []
    )
    {
        //Build the SQL query and the parameter array.

        $sql_data = self::buildResourceAndTimeRangesSqlQuery(
            $resource,
            $time_ranges,
            $closed_status,
            $excluded_request_ids,
            $additional_conditions,
            $additional_parameters
        );

        //Call findBySql:
        return self::findBySql($sql_data['sql'], $sql_data['params']);
    }

    public static function countByResourceAndTimeRanges(
        Resource $resource,
        $time_ranges = [],
        $closed_status = null,
        $excluded_request_ids = [],
        $additional_conditions = '',
        $additional_parameters = []
    )
    {
        $sql_data = self::buildResourceAndTimeRangesSqlQuery(
            $resource,
            $time_ranges,
            $closed_status,
            $excluded_request_ids,
            $additional_conditions,
            $additional_parameters
        );

        return self::countBySql($sql_data['sql'], $sql_data['params']);
    }

    public static function findByCourse($course_id)
    {
        return self::findOneBySql(
            "termin_id = '' AND metadate_id = '' AND course_id = :course_id",
            [
                'course_id' => $course_id
            ]
        );
    }

    public static function findByDate($date_id)
    {
        return self::findOneBySql(
            'termin_id = :date_id',
            [
                'date_id' => $date_id
            ]
        );
    }

    public static function findByMetadate($metadate_id)
    {
        return self::findOneBySql(
            'metadate_id = :metadate_id',
            [
                'metadate_id' => $metadate_id
            ]
        );
    }

    public static function existsByCourse($course_id, $request_is_open = false)
    {
        $parameters = [':course_id' => $course_id];

        $sql = '';
        if ($request_is_open) {
            $sql .= "closed = :closed_state AND ";
            $parameters[':closed_state'] = self::STATE_OPEN;
        }

        $request = self::findOneBySql(
            $sql . "termin_id = '' AND metadate_id = '' AND course_id = :course_id",
            $parameters
        );

        if ($request) {
            return $request->id;
        } else {
            return false;
        }
    }

    public static function existsByDate($date_id, $request_is_open = false)
    {
        $parameters = [':date_id' => $date_id];

        $sql = '';
        if ($request_is_open) {
            $sql .= "closed = :closed_state AND ";
            $parameters[':closed_state'] = self::STATE_OPEN;
        }

        $request = self::findOneBySql(
            $sql . "termin_id = :date_id",
            $parameters
        );

        if ($request) {
            return $request->id;
        } else {
            return false;
        }
    }

    public static function existsByMetadate($metadate_id, $request_is_open = false)
    {
        $parameters = [':metadate_id' => $metadate_id];

        $sql = '';
        if ($request_is_open) {
            $sql .= "closed = :closed_state AND ";
            $parameters[':closed_state'] = self::STATE_OPEN;
        }

        $request = self::findOneBySql(
            $sql . "metadate_id = :metadate_id",
            $parameters
        );

        if ($request) {
            return $request->id;
        } else {
            return false;
        }
    }

    /**
     * A callback method that creates a Stud.IP log entry
     * when a new request has been made.
     */
    public function cbLogNewRequest()
    {
        $this->sendNewRequestMail();
        StudipLog::log('RES_REQUEST_NEW', $this->course_id, $this->resource_id, $this->getLoggingInfoText());
    }

    /**
     * A callback method that sets the users connection before store.
     */
    public function cbBeforeStore()
    {
        if ($this->isNew() && !$this->user_id) {
            $this->user_id = User::findCurrent()->id;
        }

        $this->last_modified_by = User::findCurrent()->id;
    }

    /**
     * A callback method that send a mail
     * when a new request has been udpated.
     */
    public function cbAfterStore()
    {
        if ($this->isFieldDirty('closed')) {
            if ($this->closed == self::STATE_DECLINED) {
                $this->sendRequestDeniedMail();
                StudipLog::log('RES_REQUEST_DENY', $this->course_id, $this->resource_id, $this->getLoggingInfoText());
            } elseif ($this->closed == self::STATE_PENDING || $this->closed == self::STATE_CLOSED) {
                StudipLog::log('RES_REQUEST_RESOLVE', $this->course_id, $this->resource_id, $this->getLoggingInfoText());
            }
        } else {
            StudipLog::log('RES_REQUEST_UPDATE', $this->course_id, $this->resource_id, $this->getLoggingInfoText());
        }
    }

    public function cbAfterDelete()
    {
        StudipLog::log('RES_REQUEST_DEL', $this->course_id, $this->resource_id, $this->getLoggingInfoText());
    }

    /**
     * This validation method is called before storing an object.
     */
    public function validate()
    {
        if (!$this->resource_id && !$this->category_id) {
            throw new Exception(
                _('Eine Anfrage muss einer konkreten Ressource oder deren Kategorie zugewiesen sein!')
            );
        }
    }

    public function getDerivedClassInstance()
    {
        if (!$this->resource) {
            //We cannot determine a derived class.
            return $this;
        }
        $class_name = $this->resource->class_name;

        if ($class_name === 'Resource') {
            //This is already the correct class.
            return $this;
        }

        if (is_subclass_of($class_name, 'Resource')) {
            //Now we append 'Request' to the class name:
            $class_name         = $class_name . 'Request';
            return $class_name::buildExisting(
                $this->toRawArray()
            );
        } else {
            //$class_name does not contain the name of a subclass
            //of Resource. That's an error!
            throw new NoResourceClassException(
                sprintf(
                    _('Die Klasse %1$s ist keine Spezialisierung der Ressourcen-Kernklasse!'),
                    $class_name
                )
            );
        }
    }

    /**
     * Sets the range fields (termin_id, metadate_id, course_id)
     * or the ResourceRequestAppointment objects related to this request
     * according to the range type and its range-IDs specified as parameters
     * for this method. The ResourceRequest object is not stored after
     * setting the fields / related objects.
     *
     * @param string $range_type The range type for this request. One of
     *     the following: 'date', 'cycle', 'course' or 'date-multiple'.
     *
     * @param array $range_ids An array of range-IDs to be set for the
     *     specified range type. This is mostly an array of size one
     *     since the fields termin_id, metadate_id and course_id only
     *     accept one ID. The range type 'date-multiple' accepts multiple
     *     IDs.
     *
     * @return void No return value.
     */
    public function setRangeFields($range_type = '', $range_ids = [])
    {
        if ($range_type === 'date') {
            $this->termin_id   = $range_ids[0];
            $this->metadate_id = '';
        } elseif ($range_type === 'cycle') {
            $this->termin_id   = '';
            $this->metadate_id = $range_ids[0];
        } elseif ($range_type === 'date-multiple') {
            $this->termin_id   = '';
            $this->metadate_id = '';
            $appointments      = [];
            foreach ($range_ids as $range_id) {
                $app                 = new ResourceRequestAppointment();
                $app->appointment_id = $range_id;
                $appointments[]      = $app;
            }
            $this->appointments = $appointments;
        } elseif ($range_type === 'course') {
            $this->termin_id   = '';
            $this->metadate_id = '';
            $this->course_id   = $range_ids[0];
        }
    }

    /**
     * Closes the requests and sends out notification mails.
     * If the request is closed and a resource has been booked,
     * it can be passed as parameter to be included in the notification mails.
     *
     * @param bool $notify_lecturers Whether to notify lecturers of a course
     *     (true) or not (false). Defaults to false. Note that this parameter
     *     is only useful in case the request is bound to a course, either
     *     directly or via a course date or a course cycle date.
     *
     * @param ResourceBooking $bookings The resource bookings that have been
     *     created from this request.
     * @return bool @TODO
     */
    public function closeRequest($notify_lecturers = false, $bookings = [])
    {
        if (
            $this->closed == self::STATE_CLOSED
            || $this->closed == self::STATE_DECLINED
        ) {
            //The request has already been closed.
            return true;
        }

        $this->closed = self::STATE_PENDING;
        if ($this->isDirty()) {
            $this->store();
        }

        //Now we send the confirmation mail to the requester:
        $this->sendCloseRequestMailToRequester($bookings);

        if ($notify_lecturers) {
            $this->sendCloseRequestMailToLecturers($bookings);
        }

        //Sending successful: The request is closed.
        $this->closed = self::STATE_CLOSED;
        if ($this->isDirty()) {
            return $this->store();
        }
        return true;
    }

    /**
     * Returns the resource requests whose time ranges overlap
     * with those of this resource request.
     *
     * @return ResourceRequest[] An array of ResourceRequest objects.
     */
    public function getOverlappingRequests()
    {
        if ($this->resource) {
            return self::findByResourceAndTimeRanges(
                $this->resource,
                $this->getTimeIntervals(true),
                self::STATE_OPEN,
                [$this->id]
            );
        }
        return [];
    }

    /**
     * Counts the resource requests whose time ranges overlap
     * with those of this resource request.
     *
     * @return int The amount of overlapping resource requests.
     */
    public function countOverlappingRequests()
    {
        if ($this->resource) {
            return self::countByResourceAndTimeRanges(
                $this->resource,
                $this->getTimeIntervals(true),
                self::STATE_OPEN,
                [$this->id]
            );
        }
        return 0;
    }

    /**
     * Returns the resource bookings whose time ranges overlap
     * with those of this resource request.
     *
     * @return ResourceBooking[] An array of ResourceBooking objects.
     */
    public function getOverlappingBookings()
    {
        if ($this->resource) {
            return ResourceBooking::findByResourceAndTimeRanges(
                $this->resource,
                $this->getTimeIntervals(true),
                [ResourceBooking::TYPE_NORMAL, ResourceBooking::TYPE_LOCK]
            );
        }
        return [];
    }

    /**
     * Counts the resource bookings whose time ranges overlap
     * with those of this resource request.
     *
     * @return int The amount of overlapping resource bookings.
     */
    public function countOverlappingBookings()
    {
        if ($this->resource) {
            return ResourceBooking::countByResourceAndTimeRanges(
                $this->resource,
                $this->getTimeIntervals(true),
                [ResourceBooking::TYPE_NORMAL, ResourceBooking::TYPE_LOCK]
            );
        }
        return 0;
    }

    /**
     * Returns the repetion interval if regular appointments are used
     * for this request.
     *
     * @return DateInterval|null In case regular appointments are used
     *     for this request a DateInterval is returned.
     *     Otherwise null is returned.
     */
    public function getRepetitionInterval()
    {
        if ($this->metadate_id) {
            //It is a set of regular appointments.
            //We just have to compute the time difference between the first
            //two appointments to get the interval.

            $first_date  = $this->cycle->dates[0];
            $second_date = $this->cycle->dates[1];

            if (!$first_date || !$second_date) {
                //Either only one date is in the set of regular appointments
                //or there is a database error. We cannot continue.
                return null;
            }

            $first_datetime = new DateTime();
            $first_datetime->setTimestamp($first_date->date);
            $second_datetime = new DateTime();
            $second_datetime->setTimestamp($second_date->date);

            return $first_datetime->diff($second_datetime);
        }

        return null;
    }

    public function getStartDate()
    {
        $start_date = new DateTime();
        if (count($this->appointments) > 0) {
            $start_date->setTimestamp($this->appointments->first()->appointment->date);
            return $start_date;
        }

        if ($this->termin_id) {
            $start_date->setTimestamp($this->date->date);
            return $start_date;
        }

        if (isset($this->cycle) && count($this->cycle->dates) > 0) {
            $first_date = $this->cycle->dates->first();
            if ($this->metadate_id && isset($first_date->date)) {
                $start_date->setTimestamp($first_date->date);
                return $start_date;
            }

            if ($this->course_id && isset($first_date->date)) {
                $start_date->setTimestamp($first_date->date);
                return $start_date;
            }
        }

        if ($this->begin) {
            $start_date->setTimestamp($this->begin);
            return $start_date;
        }

        return null;
    }

    public function getEndDate()
    {
        $end_date = new DateTime();
        if (
            count($this->appointments) > 0
            && isset($this->appointments->last()->appointment->end_time)
        ) {
            $end_date->setTimestamp($this->appointments->last()->appointment->end_time);
            return $end_date;
        }

        if ($this->termin_id) {
            $end_date->setTimestamp($this->date->end_time);
            return $end_date;
        }

        if ($this->metadate_id) {
            $date = $this->cycle->dates->last();
            if (!isset($date)) {
                return null;
            }

            $end_date->setTimestamp($date->end_time);
            return $end_date;
        }

        if ($this->course_id) {
            $date = $this->course->dates->last();
            if (!isset($date)) {
                return null;
            }

            $end_date->setTimestamp($date->end_time);
            return $end_date;
        }

        if ($this->end) {
            $end_date->setTimestamp($this->end);
            return $end_date;
        }

        return null;
    }

    public function getStartSemester()
    {
        $start_date = $this->getStartDate();
        if ($start_date instanceof DateTime) {
            return Semester::findByTimestamp($start_date->getTimestamp());
        }
        return null;
    }

    public function getEndSemester()
    {
        $end_date = $this->getEndDate();
        if ($end_date instanceof DateTime) {
            return Semester::findByTimestamp($end_date->getTimestamp());
        }
        return null;
    }

    public function getRepetitionEndDate()
    {
        $repetition_interval = $this->getRepetitionInterval();

        if (!$repetition_interval) {
            //There is no repetition.
            return null;
        }

        return $this->getEndDate();
    }

    /**
     * Retrieves the time intervals by looking at metadate objects
     * and other time interval sources and returns them grouped by metadate.
     *
     * @param bool $with_preparation_and_subsequent_time Whether to include the preparation
     *     and subsequent time into the time intervals (true) or not (false).
     *     Defaults to false.
     *
     * @return mixed[][][] A three-dimensional array with
     *     the following structure:
     *     - The first dimension has the metadate-id as index. For single dates
     *       an empty string is used as index.
     *     - The second dimension contains two elements:
     *       - 'metadate' => The metadate object. This is only set, if the
     *                       request is for a metadate.
     *       - 'intervals' => The time intervals.
     *     - The third dimension contains a time interval
     *       in the following format:
     *       [
     *           'begin' => The begin timestamp
     *           'end' => The end timestamp
     *           'range' => The name of the range class that provides the range_id.
     *               This is usually the name of the SORM class.
     *           'range_id' => The ID of the single date or ResourceRequestAppointment.
     *       ]
     */
    public function getGroupedTimeIntervals($with_preparation_and_subsequent_time = false, $with_past_intervals = true)
    {
        $now = time();
        if (count($this->appointments)) {
            $time_intervals = [
                '' => [
                    'metadate'  => null,
                    'intervals' => []
                ]
            ];
            foreach ($this->appointments as $appointment) {
                if (!$with_past_intervals && $appointment->appointment->end_time < $now) {
                    continue;
                }
                if ($with_preparation_and_subsequent_time) {
                    $interval = [
                        'begin' => $appointment->appointment->date - $this->preparation_time,
                        'end'   => $appointment->appointment->end_time + $this->subsequent_time
                    ];
                } else {
                    $interval = [
                        'begin' => $appointment->appointment->date,
                        'end'   => $appointment->appointment->end_time
                    ];
                }

                $date         = CourseDate::find($appointment->appointment_id);
                $booked_rooms = [];
                $booking_ids  = [];
                foreach ($date->room_bookings as $booking) {
                    $booked_rooms[] = $booking->resource_id;
                    $booking_ids[]  = $booking->id;
                }
                $interval['range']                 = 'CourseDate';
                $interval['range_id']              = $appointment->appointment_id;
                $interval['booked_rooms']          = $booked_rooms;
                $interval['booking_ids']           = $booking_ids;
                $time_intervals['']['intervals'][] = $interval;
            }

            if (empty($time_intervals['']['intervals'])) {
                return [];
            } else {
                return $time_intervals;
            }
        } elseif ($this->termin_id) {
            if (!$with_past_intervals && $this->date->end_time < $now) {
                return [];
            }
            if ($with_preparation_and_subsequent_time) {
                $interval = [
                    'begin' => $this->date->date - $this->preparation_time,
                    'end'   => $this->date->end_time + $this->subsequent_time
                ];
            } else {
                $interval = [
                    'begin' => $this->date->date,
                    'end'   => $this->date->end_time
                ];
            }

            $date         = CourseDate::find($this->termin_id);
            $booked_rooms = [];
            $booking_ids  = [];
            foreach ($date->room_bookings as $booking) {
                $booked_rooms[] = $booking->resource_id;
                $booking_ids[]  = $booking->id;
            }
            $interval['range']        = 'CourseDate';
            $interval['range_id']     = $this->termin_id;
            $interval['booked_rooms'] = $booked_rooms;
            $interval['booking_ids']  = $booking_ids;

            if (!empty($interval)) {
                return [
                    '' => [
                        'metadate'  => null,
                        'intervals' => [$interval]
                    ]
                ];
            } else {
                return [];
            }
        } elseif ($this->metadate_id) {
            $time_intervals = [
                $this->metadate_id => [
                    'metadate'  => $this->cycle,
                    'intervals' => []
                ]
            ];
            foreach ($this->cycle->dates as $date) {
                if (!$with_past_intervals && $date->end_time < $now) {
                    continue;
                }
                if ($with_preparation_and_subsequent_time) {
                    $interval = [
                        'begin' => $date->date - $this->preparation_time,
                        'end'   => $date->end_time + $this->subsequent_time
                    ];
                } else {
                    $interval = [
                        'begin' => $date->date,
                        'end'   => $date->end_time
                    ];
                }
                $booked_rooms = [];
                $booking_ids  = [];
                foreach ($date->room_bookings as $booking) {
                    $booked_rooms[] = $booking->resource_id;
                    $booking_ids[]  = $booking->id;
                }
                $interval['range']                                 = 'CourseDate';
                $interval['range_id']                              = $date->id;
                $interval['booked_rooms']                          = $booked_rooms;
                $interval['booking_ids']                           = $booking_ids;
                $time_intervals[$this->metadate_id]['intervals'][] = $interval;
            }
            return $time_intervals;
        } elseif ($this->course_id) {
            $time_intervals = [];
            if ($this->course->cycles) {
                foreach ($this->course->cycles as $cycle) {
                    $time_intervals[$cycle->id] = [
                        'metadate'  => $cycle,
                        'intervals' => []
                    ];
                    if ($cycle->dates) {
                        foreach ($cycle->dates as $date) {
                            if (!$with_past_intervals && $date->end_time < $now) {
                                continue;
                            }
                            if ($with_preparation_and_subsequent_time) {
                                $interval = [
                                    'begin' => $date->date - $this->preparation_time,
                                    'end'   => $date->end_time + $this->subsequent_time
                                ];
                            } else {
                                $interval = [
                                    'begin' => $date->date,
                                    'end'   => $date->end_time
                                ];
                            }
                            $booked_rooms = [];
                            $booking_ids  = [];
                            foreach ($date->room_bookings as $booking) {
                                $booked_rooms[] = $booking->resource_id;
                                $booking_ids[]  = $booking->id;
                            }
                            $interval['range'] = CourseDate::class;
                            $interval['range_id']     = $date->id;
                            $interval['booked_rooms'] = $booked_rooms;
                            $interval['booking_ids']  = $booking_ids;
                            $time_intervals[$cycle->id]['intervals'][] = $interval;
                        }
                    }
                }
            }
            if ($this->course->dates) {
                $time_intervals[''] = [
                    'metadate'  => null,
                    'intervals' => []
                ];
                foreach ($this->course->dates as $date) {
                    if (!$with_past_intervals && $date->end_time < $now) {
                        continue;
                    }
                    if ($date->cycle instanceof SeminarCycleDate) {
                        //Metadates are already handled above.
                        continue;
                    }
                    if ($with_preparation_and_subsequent_time) {
                        $interval = [
                            'begin' => $date->date - $this->preparation_time,
                            'end'   => $date->end_time + $this->subsequent_time
                        ];
                    } else {
                        $interval = [
                            'begin' => $date->date,
                            'end'   => $date->end_time
                        ];
                    }

                    $booked_rooms = [];
                    $booking_ids  = [];
                    foreach ($date->room_bookings as $booking) {
                        $booked_rooms[] = $booking->resource_id;
                        $booking_ids[]  = $booking->id;
                    }
                    $interval['range']                 = 'CourseDate';
                    $interval['range_id']              = $date->id;
                    $interval['booked_rooms']          = $booked_rooms;
                    $interval['booking_ids']           = $booking_ids;
                    $time_intervals['']['intervals'][] = $interval;
                }

                if (empty($time_intervals['']['intervals'])) {
                    unset($time_intervals['']);
                }
            }
            return $time_intervals;
        } elseif ($this->begin && $this->end) {
            if (!$with_past_intervals && $this->end < $now) {
                return [];
            }
            if ($with_preparation_and_subsequent_time) {
                $interval = [
                    'begin' => $this->begin - $this->preparation_time,
                    'end'   => $this->end + $this->subsequent_time
                ];
            } else {
                $interval = [
                    'begin' => $this->begin,
                    'end'   => $this->end
                ];
            }
            $interval['range']        = 'User';
            $interval['range_id']     = $this->user_id;
            $interval['booked_rooms'] = [];
            $interval['booking_ids']  = [];

            return [
                '' => [
                    'metadate'  => null,
                    'intervals' => [$interval]
                ]
            ];
        } else {
            return [];
        }
    }

    /**
     * Retrieves the time intervals for this request.
     *
     * @param bool $with_preparation_and_subsequent_time Whether the preparation time
     *     of the request shall be prepended to the begin timestamp and the subsequent
     *     time shall be appended to the end timestamp of the request (true)
     *     or whether it should not be included at all (false).
     *     Defaults to false.
     *
     * @param bool $with_range Whether to include data of the Stud.IP range
     *     and its corresponding ID to the request (true) or not (false).
     *     Defaults to false.
     *
     * @param bool $with_past_intervals Whether to include past intervals (true)
     *     or only include intervals from the current time and the future (false).
     *     Defaults to true.
     *
     * @return string[][] A two-dimensional array of unix timestamps.
     *     The first dimension contains one entry for each date,
     *     the second dimension contains the start and end timestamp
     *     for the date.
     *     The second dimension uses the array keys 'begin' and 'end'
     *     for start and end date.
     *     If the @with_range parameter is set to true, the second array
     *     dimension also contains the key 'range' for specifying the
     *     range type and 'range_id' for specifying the ID of the
     *     range object.
     *     The range can be "CourseDate", "ResourceRequestAppointment"
     *     or "User". The last two can only be present for simple requests
     *     that are not bound to a course. The range "CourseDate"
     *     can only occur on course-bound requests.
     */
    public function getTimeIntervals($with_preparation_and_subsequent_time = false, $with_range = false, $with_past_intervals = true)
    {
        $now = time();
        if (count($this->appointments)) {
            $time_intervals = [];
            foreach ($this->appointments as $appointment) {
                if (!$with_past_intervals && $appointment->appointment->end_time < $now) {
                    continue;
                }
                if ($with_preparation_and_subsequent_time) {
                    $interval = [
                        'begin' => $appointment->appointment->date - $this->preparation_time,
                        'end'   => $appointment->appointment->end_time + $this->subsequent_time
                    ];
                } else {
                    $interval = [
                        'begin' => $appointment->appointment->date,
                        'end'   => $appointment->appointment->end_time
                    ];
                }
                if ($with_range) {
                    $date = CourseDate::find($appointment->appointment_id);
                    $booked_rooms = [];
                    $booking_ids  = [];
                    foreach ($date->room_bookings as $booking) {
                        $booked_rooms[] = $booking->resource_id;
                        $booking_ids[]  = $booking->id;
                    }
                    $interval['range']        = ResourceRequestAppointment::class;
                    $interval['range_id']     = $appointment->appointment_id;
                    $interval['booked_rooms'] = $booked_rooms;
                    $interval['booking_ids']  = $booking_ids;

                }
                $time_intervals[] = $interval;
            }
            return $time_intervals;
        } elseif ($this->termin_id) {
            if (!$with_past_intervals && $this->date->end_time < $now) {
                return [];
            }
            if ($with_preparation_and_subsequent_time) {
                $interval = [
                    'begin' => $this->date->date - $this->preparation_time,
                    'end'   => $this->date->end_time + $this->subsequent_time
                ];
            } else {
                $interval = [
                    'begin' => $this->date->date,
                    'end'   => $this->date->end_time
                ];
            }
            if ($with_range) {
                $booked_rooms = [];
                $booking_ids  = [];
                if ($this->date->room_bookings) {
                    foreach ($this->date->room_bookings as $booking) {
                        $booked_rooms[] = $booking->resource_id;
                        $booking_ids[]  = $booking->id;
                    }
                }
                $interval['range']        = CourseDate::class;
                $interval['range_id']     = $this->termin_id;
                $interval['booked_rooms'] = $booked_rooms;
                $interval['booking_ids']  = $booking_ids;
            }
            return [$interval];
        } elseif ($this->metadate_id) {
            $time_intervals = [];
            foreach ($this->cycle->dates as $date) {
                if (!$with_past_intervals && $date->end_time < $now) {
                    continue;
                }
                if ($with_preparation_and_subsequent_time) {
                    $interval = [
                        'begin' => $date->date - $this->preparation_time,
                        'end'   => $date->end_time + $this->subsequent_time
                    ];
                } else {
                    $interval = [
                        'begin' => $date->date,
                        'end'   => $date->end_time
                    ];
                }
                if ($with_range) {
                    $booked_rooms = [];
                    $booking_ids  = [];
                    if ($date->room_bookings) {
                        foreach ($date->room_bookings as $booking) {
                            $booked_rooms[] = $booking->resource_id;
                            $booking_ids[]  = $booking->id;
                        }
                    }

                    $interval['range']        = CourseDate::class;
                    $interval['range_id']     = $date->id;
                    $interval['booked_rooms'] = $booked_rooms;
                    $interval['booking_ids']  = $booking_ids;
                }
                $time_intervals[] = $interval;
            }
            return $time_intervals;
        } elseif ($this->course_id) {
            $time_intervals = [];
            if ($this->course->dates) {
                foreach ($this->course->dates as $date) {
                    if (!$with_past_intervals && $date->end_time < $now) {
                        continue;
                    }
                    if ($with_preparation_and_subsequent_time) {
                        $interval = [
                            'begin' => $date->date - $this->preparation_time,
                            'end'   => $date->end_time + $this->subsequent_time
                        ];
                    } else {
                        $interval = [
                            'begin' => $date->date,
                            'end'   => $date->end_time
                        ];
                    }
                    if ($with_range) {
                        $booked_rooms = [];
                        $booking_ids  = [];
                        if ($date->room_bookings) {
                            foreach ($date->room_bookings as $booking) {
                                $booked_rooms[] = $booking->resource_id;
                                $booking_ids[]  = $booking->id;
                            }
                        }

                        $interval['range']        = CourseDate::class;
                        $interval['range_id']     = $date->id;
                        $interval['booked_rooms'] = $booked_rooms;
                        $interval['booking_ids']  = $booking_ids;
                    }
                    $time_intervals[] = $interval;
                }
            }
            return $time_intervals;
        } elseif ($this->begin && $this->end) {
            if (!$with_past_intervals && $this->end < $now) {
                return [];
            }
            if ($with_preparation_and_subsequent_time) {
                $interval = [
                    'begin' => $this->begin - $this->preparation_time,
                    'end'   => $this->end + $this->subsequent_time
                ];
            } else {
                $interval = [
                    'begin' => $this->begin,
                    'end'   => $this->end
                ];
            }
            if ($with_range) {
                $interval['range']    = 'User';
                $interval['range_id'] = $this->user_id;
            }
            return [$interval];
        } else {
            return [];
        }
    }


    /**
     * Returns a string representation of the time intervals for this request.
     */
    public function getTimeIntervalStrings()
    {
        $strings   = [];
        $intervals = $this->getTimeIntervals(false, true);
        foreach ($intervals as $interval) {
            $room = '';

            if ($interval['range'] === 'CourseDate') {
                $date = call_user_func([$interval['range'], 'find'], $interval['range_id']);
                if ($date->room_bookings) {
                    $room_names = [];
                    foreach ($date->room_bookings as $room_booking) {
                        if (!empty($room_booking->resource)) {
                            $room_names[] = $room_booking->resource->name;
                        }
                    }
                    $room = implode(', ', $room_names);
                }
            }

            $same_day = date('Ymd', $interval['begin']) === date('Ymd', $interval['end']);
            if ($same_day) {
                $strings[] = strftime('%a. %x %R', $interval['begin'])
                    . ' - ' . strftime('%R', $interval['end'])
                    . ($room ? ', '. $room : '');
            } else {
                $strings[] = strftime('%a. %x %R', $interval['begin'])
                    . ' - ' . strftime('%a %x %R', $interval['end'])
                    . ($room ? ', '. $room : '');
            }

        }
        return $strings;
    }


    /**
     * Filters the time intervals for this request
     * by a specified time range.
     *
     * @see ResourceRequest::getTimeIntervals for the return format.
     */
    public function getTimeIntervalsInTimeRange(DateTime $begin, DateTime $end)
    {
        $all_time_intervals = $this->getTimeIntervals();

        $included_intervals = [];
        foreach ($all_time_intervals as $interval) {
            $interval_in_range = (
                (
                    $interval['begin'] >= $begin->getTimestamp()
                    &&
                    $interval['begin'] <= $end->getTimestamp()
                )
                ||
                (
                    $interval['end'] >= $begin->getTimestamp()
                    &&
                    $interval['end'] <= $end->getTimestamp()
                )
            );
            if ($interval_in_range) {
                $included_intervals[] = $interval;
            }
        }

        return $included_intervals;
    }


    /**
     * Returns a string representation of the ResourceRequest's type.
     */
    public function getType()
    {
        if (count($this->appointments)) {
            return 'appointments';
        } elseif ($this->termin_id) {
            return 'date';
        } elseif ($this->metadate_id) {
            return 'cycle';
        } elseif ($this->course_id) {
            return 'course';
        }
        return null;
    }

    /**
     * Returns a string representation of the status of the ResourceRequest.
     */
    public function getStatus()
    {
        switch ($this->closed) {
            case self::STATE_OPEN:
                return 'open';
            case self::STATE_PENDING:
                return 'pending';
            case self::STATE_CLOSED:
                return 'closed';
            case self::STATE_DECLINED:
                return 'declined';
            default:
                return '';
        }
    }


    /**
     * Returns a textual representation of the status of the ResourceRequest.
     */
    public function getStatusText()
    {
        if ($this->isNew()) {
            return _('Diese Anfrage wurde noch nicht gespeichert.');
        }
        if ($this->closed == self::STATE_OPEN) {
            return _('Die Anfrage wurde noch nicht bearbeitet.');
        } else if ($this->closed == self::STATE_DECLINED) {
            return _('Die Anfrage wurde bearbeitet und abgelehnt.');
        } else {
            return _('Die Anfrage wurde bearbeitet.');
        }
    }


    /**
     * Returns a textual representation of the dates for which the request
     * has been created.
     *
     * @param bool $as_array True, if an array with a string for each date
     *     (single or cycle date) shall be returned, false otherwise.
     *
     * @returns string|array Depending on the parameter $as_array, the text
     *     is returned as one string or as an array of strings for each date
     *     (single or cycle date).
     */
    public function getDateString($as_array = false, $with_past_intervals = true)
    {
        $now = time();
        $strings = [];
        $resource_name = '';
        if (count($this->appointments) > 0) {
            // Filter invalid/not matching items
            $appointments = $this->appointments->filter(
                function (ResourceRequestAppointment $appointment) use ($with_past_intervals, $now): bool {
                    if (!$appointment->appointment) {
                        return false;
                    }
                    return $with_past_intervals
                        || $appointment->appointment->end_time > $now;
                }
            );

            // Sort by appointment date
            $appointments->uasort(
                function (ResourceRequestAppointment $a, ResourceRequestAppointment $b): int {
                    return $a->appointment->date - $b->appointment->date;
                }
            );

            // Get readable string for each appointment
            $strings = $appointments->map(
                function (ResourceRequestAppointment $appointment): string {
                    return $appointment->appointment->getFullName('include-room');
                },
            );
        } elseif ($this->termin_id) {
            if ($this->date) {
                if ($with_past_intervals || $this->date->end_time >= $now) {
                    $strings[] = $this->date->getFullName('include-room');
                }
            }
        } elseif ($this->metadate_id) {
            if ($this->cycle) {
                $strings = $this->cycle->dates->filter(function ($date) use($with_past_intervals, $now) {
                    return $with_past_intervals || $date->end_time >= $now;
                })->map(function($date) {
                    return $date->getFullName('include-room');
                });
            }
        } elseif ($this->course_id) {
            $strings = $this->course->getAllDatesInSemester()->toStringArray();
        } elseif ($this->begin && $this->end) {
            $begin_date = date('Ymd', $this->begin);
            $end_date   = date('Ymd', $this->end);
            if($this->resource) {
                $resource_name = $this->resource->getFullName();
            }
            if ($begin_date == $end_date) {
                $strings[] = strftime('%a., %x, %R', $this->begin) . ' - '
                           . strftime('%R', $this->end) . ' ' . $resource_name;
            } else {
                //Begin and end are on differnt dates
                $strings[] = strftime('%a., %x, %R', $this->begin) . ' - '
                    . strftime('%a., %x, %R', $this->end) . ' ' . $resource_name;
            }
        }

        if ($as_array) {
            return $strings;
        } else {
            return implode(';', $strings);
        }
    }


    /**
     * Returns a human-readable string describing the type of the request.
     *
     * @param bool $short If this parameter is set to true, only the
     *     type of the request is returned without any information about the
     *     appointments. Otherwise, appointment information like the
     *     date or the repetition are appended. Defaults to false.
     * @return string
     */
    public function getTypeString($short = false)
    {
        if (count($this->appointments) > 1) {
            if ($short) {
                return _('Einzeltermine');
            } else {
                return sprintf(_('Einzeltermine (%sx)'), count($this->appointments));
            }
        } elseif (count($this->appointments) === 1) {
            $date = $this->appointments[0]->appointment;
            if ($short || !$date) {
                return _('Einzeltermin');
            } else {
                return sprintf(_('Einzeltermin (%s)'), $date->getFullName());
            }
        } elseif ($this->date) {
            if ($short) {
                return _('Einzeltermin');
            } else {
                return sprintf(_('Einzeltermin (%s)'), $this->date->getFullName());
            }
        } elseif ($this->cycle) {
            if ($short) {
                return _('Regelmäßige Termine');
            } else {
                return sprintf(
                    _('Regelmäßige Termine (%s)'),
                    $this->cycle->toString('full')
                );
            }
        } elseif ($this->course) {
            if ($short) {
                return _('Alle Termine der Veranstaltung');
            } else {
                return sprintf(
                    _('Alle Termine der Veranstaltung (%sx)'),
                    count($this->course->dates)
                );
            }
        } else {
            return _('Einfache Anfrage');
        }
    }


    /**
     * Returns an array of date objects which are affected
     * by this ResourceRequest.
     */
    public function getAffectedDates()
    {
        $dates = [];
        switch ($this->getType()) {
            case 'date':
                $dates[] = $this->date;
                break;
            case 'cycle':
                $dates = $this->cycle->dates->getArrayCopy();
                break;
            case 'course':
                $dates = $this->course->dates->getArrayCopy();
                break;
        }
        return $dates;
    }


    /**
     * @param array $excluded_property_names
     * Returns all resource property definitions for all properties
     * which can be applied for this ResourceRequest by looking at the
     * Resource category. If no resource category ID is set for the request
     * an empty array is returned.
     */
    public function getAvailableProperties($excluded_property_names = [])
    {
        if (!$this->category_id) {
            //Without a category-ID we cannot find any property!
            return [];
        }
        if (count($excluded_property_names)) {
            return ResourcePropertyDefinition::findBySql(
                "INNER JOIN resource_category_properties
                USING (property_id)
                WHERE requestable = '1' AND category_id = :category_id
                AND name NOT IN ( :excluded_property_names )",
                [
                    'category_id'             => $this->category_id,
                    'excluded_property_names' => $excluded_property_names
                ]
            );
        } else {
            return ResourcePropertyDefinition::findBySql(
                "INNER JOIN resource_category_properties
                USING (property_id)
                WHERE requestable = '1' AND category_id = :category_id",
                [
                    'category_id' => $this->category_id
                ]
            );
        }
    }


    /**
     * Returns a "compressed" array of resource request properties.
     * @param array $excluded_property_names
     * @return array An associative array where the keys represent the
     *     property names and the values represent the property states.
     *     Note that the value can be an array in case of range properties.
     */
    public function getPropertyData($excluded_property_names = [])
    {
        $data = [];
        foreach ($this->properties as $property) {
            if ($property->definition->range_search) {
                //Assume that a minimum value is requested:
                $data[$property->name] = [$property->state];
            } else {
                $data[$property->name] = $property->state;
            }
        }
        return $data;
    }

    /**
     * @param $name
     * @return bool
     */
    public function propertyExists($name)
    {
        $db = DBManager::get();

        $exists_stmt = $db->prepare(
            "SELECT TRUE FROM resource_request_properties
            INNER JOIN resource_property_definitions rpd
                ON resource_request_properties.property_id = rpd.property_id
            WHERE resource_request_properties.request_id = :request_id
                AND rpd.name = :name");

        $exists_stmt->execute(
            [
                'request_id' => $this->id,
                'name'       => $name
            ]
        );

        $exists = $exists_stmt->fetchColumn(0);

        return (bool)$exists;
    }


    /**
     * @param $name
     * Returns the state of the property specified by $name.
     */
    public function getProperty($name)
    {
        if (!$this->propertyExists($name)) {
            //A property with the name $name does not exist for this
            //resource request object.
            //In that case we can only return null, since resource requests
            //store only those properties which are requested:

            return null;
        }

        $db = DBManager::get();

        $value_stmt = $db->prepare(
            "SELECT resource_request_properties.state FROM resource_request_properties
            INNER JOIN resource_property_definitions rpd
                ON resource_request_properties.property_id = rpd.property_id
            WHERE resource_request_properties.request_id = :request_id
                AND rpd.name = :name");

        $value_stmt->execute(
            [
                'request_id' => $this->id,
                'name'       => $name
            ]
        );

        $value = $value_stmt->fetchColumn(0);

        if (!$value) {
            return null;
        }

        return $value;
    }


    /**
     * @param $name
     * @return ResourceRequestProperty
     * @throws InvalidResourceCategoryException If this resource category
     *     doesn't match the category of the resource request object.
     * @throws ResourcePropertyException If the name of the
     *     resource request property is not defined for this resource category.
     */
    public function getPropertyObject($name)
    {
        if (!$this->propertyExists($name)) {
            //A property with the name $name does not exist for this
            //resource object. If it is a mandatory property
            //we can still try to create it:

            $property = $this->category->createDefinedResourceRequestProperty(
                $this,
                $name
            );

            $property->store();
            return $property;
        }

        return ResourceRequestProperty::findOneBySql(
            "INNER JOIN resource_property_definitions rpd
                ON resource_request_properties.property_id = rpd.property_id
            WHERE resource_request_properties.request_id = :request_id
                AND rpd.name = :name",
            [
                'request_id' => $this->id,
                'name'       => $name
            ]
        );
    }


    /**
     * @param string $name
     * @param string $state
     * @return bool True, if the property state could be set, false otherwise.
     */
    public function setProperty($name, $state = '')
    {
        if (!$this->propertyExists($name)) {
            //A property with the name $name does not exist for this
            //resource object. If it is a mandatory property
            //we can still try to create it:

            if ($this->category) {
                $property = $this->category->createDefinedResourceRequestProperty(
                    $this,
                    $name,
                    $state
                );
                return $property->store();
            }
            return false;
        }

        $property = $this->getPropertyObject($name);

        if ($property) {
            $property->state = $state;
            if ($property->isDirty()) {
                return $property->store();
            }
            return true;
        }
    }


    /**
     * Sets or unsets the properties for this resource request.
     *
     * @param array $property_list The properties which shall be set
     *     or unset. The array has the following structure:
     *     [
     *         property_name => property_value
     *     ]
     *
     * @param bool $accept_null_values True, if a value of null
     *     shall be used when setting the property.
     *     If $accept_null_values is set to false all properties
     *     with a value equal to null will be deleted.
     *
     * @return null
     */
    public function updateProperties($property_list = [], $accept_null_values = false)
    {
        //Delete all properties first then re-create them
        //from the $property_list array:
        $this->properties->delete();
        if (is_array($property_list)) {
            foreach ($property_list as $name => $state) {
                if ($state or $accept_null_values) {
                    //State is set or null values are allowed:
                    //create/update the property
                    $this->setProperty($name, $state);
                }
            }
        }
        $this->resetRelation('properties');
    }


    public function deletePropertyIfExists($name = '')
    {
        if (!$this->propertyExists($name)) {
            return true;
        } else {
            $property = $this->getPropertyObject($name);
            return $property->delete();
        }
    }


    public function getRangeName()
    {
        if ($this->getRangeType() === 'course') {
            $name = $this->getRangeObject()->getFullName();
            $name .= ' (' . implode(',', $this->getRangeObject()->getMembersWithStatus('dozent', true)->limit(3)->getValue('nachname')) . ')';
        } else {
            $range_object = $this->getRangeObject();
            if ($range_object instanceof User) {
                if (get_visibility_by_id($range_object->id)) {
                    $name = $range_object->getFullName();
                } else if ($this->user_id === $GLOBALS['user']->id) {
                    $name = $range_object->getFullName();
                } else {
                    $current_user = User::findCurrent();
                    if ($current_user instanceof User) {
                        //If the current user has at least autor permissions
                        //(which are required to see all requests), they can
                        //see the name of the requester.
                        if ($this->resource_id && ($this->resource instanceof Resource)
                            && $this->resource->userHasPermission($current_user, 'autor')) {
                            $name = $range_object->getFullName();
                        } else if (ResourceManager::userHasGlobalPermission($current_user, 'autor')) {
                            $name = $range_object->getFullName();
                        } else {
                            return '';
                        }
                    } else {
                        return '';
                    }
                }
            } else {
                $name = $range_object->getFullName();
            }
            if ($this->comment) {
                $name .= " \n" . $this->comment;
            }
        }
        return $name;
    }


    public function isSimpleRequest()
    {
        return !$this->course_id && !$this->metadate_id && !$this->termin_id;
    }


    public function getRangeId()
    {
        //Check if the request belongs to a course:
        if ($this->termin_id) {
            return $this->date->range_id;
        } elseif ($this->metadate_id) {
            return $this->cycle->seminar_id;
        } elseif ($this->course_id) {
            return $this->course_id;
        }

        //The request does not belong to a course and therefore
        //belongs to a user:
        return $this->user_id;
    }


    public function getRangeType()
    {
        if ($this->course_id || $this->termin_id || $this->metadate_id) {
            return 'course';
        }
        return 'user';
    }

    /**
     * @return Course|User
     */
    public function getRangeObject()
    {
        if ($this->course_id) {
            return $this->course;
        }
        if ($this->termin_id) {
            return $this->date->course;
        }
        if ($this->metadate_id) {
            return $this->cycle->course;
        }
        return $this->user;
    }


    /**
     * This method sends a notification mail to all room administrators
     * that informs them of this new request.
     */
    public function sendNewRequestMail()
    {
        //First we must get all users who have admin permissions in the
        //resource management system. Depending wheter a resource_id is set
        //for this resource request either all admins of a resource or
        //all admins of the resource management system must be informed.

        $now         = time();
        if ($this->resource_id) {
            //The resource-ID is set for this request:
            //Get all admins of the resource and the resource management system.
            $admin_users = User::findBySql(
                "user_id IN (
                    SELECT user_id FROM resource_permissions
                    WHERE (
                        resource_id = :resource_id
                        OR resource_id = 'global'
                    )
                    AND perms = 'admin'
                    UNION
                    SELECT user_id FROM resource_temporary_permissions
                    WHERE resource_id = :resource_id
                    AND perms = 'admin'
                    AND begin <= :now AND end >= :now
                )",
                [
                    'resource_id' => $this->resource_id,
                    'now'         => $now
                ]
            );
        } else {
            //Get all admins of the resource management system.
            $admin_users = User::findBySql(
                "user_id IN (
                    SELECT user_id FROM resource_permissions
                    WHERE resource_id = 'global'
                    AND perms = 'admin'
                    UNION
                    SELECT user_id FROM resource_temporary_permissions
                    WHERE resource_id = 'global'
                    AND perms = 'admin'
                    AND begin <= :now AND end >= :now
                    GROUP BY user_id
                )",
                [
                    'now' => $now
                ]
            );
        }

        if (!$admin_users) {
            return;
        }

        $factory = new Flexi\Factory(
            $GLOBALS['STUDIP_BASE_PATH'] . '/locale/'
        );

        foreach ($admin_users as $user) {
            $user_lang_path = getUserLanguagePath($user->id);

            $template = $factory->open(
                $user_lang_path . '/LC_MAILS/new_resource_request.php'
            );
            $template->set_attribute('request', $this);

            if ($this->resource instanceof Resource) {
                $resource = $this->resource->getDerivedClassInstance();
                if ($resource instanceof Room) {
                    $template->set_attribute('requested_room', $resource->name);
                } else {
                    $template->set_attribute('requested_resource', $resource->name);
                }
            }

            $mail_text = $template->render();

            setLocaleEnv($user->preferred_language);

            if ($this->resource) {
                $resource = $this->resource->getDerivedClassInstance();
                $template->set_attribute('derived_resource', $resource);
                $mail_title = sprintf(
                    _('%1$s: Neue Anfrage in der Raumverwaltung'),
                    $resource->getFullName()
                );
            } else {
                $mail_title = sprintf(
                    _('Neue Anfrage in der Raumverwaltung')
                );
            }

            Message::send(
                User::findCurrent()->id,
                $user->username,
                $mail_title,
                $mail_text
            );

            restoreLanguage();
        }
    }


    /**
     * @param array $bookings
     * This method sends a mail to inform the requester that
     * the request has been closed.
     */
    public function sendCloseRequestMailToRequester($bookings = [])
    {
        $factory = new Flexi\Factory(
            $GLOBALS['STUDIP_BASE_PATH'] . '/locale/'
        );

        $requester_lang      = $this->user->preferred_language;
        $requester_lang_path = getUserLanguagePath($this->user->id);
        setLocaleEnv($requester_lang);

        $template = $factory->open(
            $requester_lang_path . '/LC_MAILS/close_resource_request.php'
        );
        $template->set_attribute('request', $this);
        if ($this->course) {
            $lecturers      = CourseMember::findByCourseAndStatus(
                $this->course->id,
                'dozent'
            );
            $lecturer_names = [];
            foreach ($lecturers as $lecturer) {
                if ($lecturer->user instanceof User) {
                    $lecturer_names[] = $lecturer->user->getFullName();
                }
            }

            $lecturer_names = implode(', ', $lecturer_names);
            $template->set_attribute('lecturer_names', $lecturer_names);
        }
        if (is_array($bookings)) {
            $booked_rooms          = [];
            $booked_time_intervals = [];
            $metadates             = [];
            $single_dates          = [];
            foreach ($bookings as $booking) {
                if (!($booking instanceof ResourceBooking)) {
                    continue;
                }
                $booked_rooms[] = $booking->resource->name;
                if ($booking->assigned_course_date instanceof CourseDate) {
                    $single_date = $booking->assigned_course_date;
                    $metadate    = $single_date->cycle;
                    if ($metadate instanceof SeminarCycleDate) {
                        $metadates[$metadate->id] = $metadate;
                    } else {
                        $single_dates[$single_date->id] = $single_date;
                    }
                } else {
                    $time_intervals = $booking->getTimeIntervals();
                    foreach ($time_intervals as $time_interval) {
                        $booked_time_intervals[] = $time_interval->__toString();
                    }
                }
            }
            $booked_rooms = array_unique($booked_rooms);
            sort($booked_rooms);
            $template->set_attribute('booked_rooms', implode(', ', $booked_rooms));
            $template->set_attribute('metadates', $metadates);
            $template->set_attribute('single_dates', $single_dates);
            $template->set_attribute('booked_time_intervals', $booked_time_intervals);
        }

        $mail_title = _('Ihre Anfrage wurde bearbeitet!');
        $mail_text  = $template->render();

        Message::send(
            User::findCurrent()->id,
            $this->user->username,
            $mail_title,
            $mail_text
        );

        restoreLanguage();
    }


    /**
     * @param array $bookings
     * This method sends mails to the lecurers of the course (if any)
     * where this request has been assigned to. The sent mail informs them
     * about the closing of the request.
     */
    public function sendCloseRequestMailToLecturers($bookings = [])
    {
        //Notify each lecturer of the course:
        if ($this->course) {
            $lecturers = CourseMember::findByCourseAndStatus(
                $this->course->id,
                'dozent'
            );

            if ($lecturers) {
                $factory = new Flexi\Factory(
                    $GLOBALS['STUDIP_BASE_PATH'] . '/locale/'
                );

                $lecturer_names = [];
                foreach ($lecturers as $lecturer) {
                    if ($lecturer->user instanceof User) {
                        $lecturer_names[] = $lecturer->user->getFullName();
                    }
                }
                $lecturer_names = implode(', ', $lecturer_names);

                $booked_rooms          = [];
                $booked_time_intervals = [];
                $metadates             = [];
                $single_dates          = [];
                if (is_array($bookings)) {
                    foreach ($bookings as $booking) {
                        if (!($booking instanceof ResourceBooking)) {
                            continue;
                        }
                        $booked_rooms[] = $booking->resource->name;
                        if ($booking->assigned_course_date instanceof CourseDate) {
                            $single_date = $booking->assigned_course_date;
                            $metadate    = $single_date->cycle;
                            if ($metadate instanceof SeminarCycleDate) {
                                $metadates[$metadate->id] = $metadate;
                            } else {
                                $single_dates[$single_date->id] = $single_date;
                            }
                        } else {
                            $time_intervals = $booking->getTimeIntervals();
                            foreach ($time_intervals as $time_interval) {
                                $booked_time_intervals[] = $time_interval->__toString();
                            }
                        }
                    }
                }
                $booked_rooms = array_unique($booked_rooms);
                sort($booked_rooms);
                $booked_rooms = implode(', ', $booked_rooms);

                foreach ($lecturers as $lecturer) {
                    $lec_lang      = $lecturer->user->preferred_language;
                    $lec_lang_path = getUserLanguagePath($lecturer->user->id);

                    setLocaleEnv($lec_lang);

                    $template = $factory->open(
                        $lec_lang_path . '/LC_MAILS/close_resource_request.php'
                    );
                    $template->set_attribute('request', $this);
                    $template->set_attribute('lecturer_names', $lecturer_names);
                    $template->set_attribute('booked_rooms', $booked_rooms);
                    $template->set_attribute('metadates', $metadates);
                    $template->set_attribute('single_dates', $single_dates);
                    $template->set_attribute('booked_time_intervals', $booked_time_intervals);

                    $mail_title = _('Bearbeitung einer Anfrage!');
                    $mail_text  = $template->render();

                    Message::send(
                        User::findCurrent()->id,
                        $lecturer->user->username,
                        $mail_title,
                        $mail_text
                    );

                    restoreLanguage();
                }
            }
        }
    }


    /**
     * This method sends a mail to inform the requester
     * about the denial of the request.
     */
    public function sendRequestDeniedMail()
    {
        $users = [];
        //Get the user who made the request:
        if ($this->user) {
            $users[$this->user->id] = $this->user->username;
        }
        $range_object = $this->getRangeObject();
        if ($range_object instanceof Course && $this->reply_recipients === ResourceRequest::REPLY_LECTURER) {
            foreach ($range_object->getMembersWithStatus('dozent') as $lecturer) {
                $users[$lecturer->user_id] = $lecturer->username;
            }
        }
        if (count($users) === 0) {
            //No mail to send.
            return;
        }

        $mail_title = _('Raumanfrage wurde abgelehnt');
        if ($range_object instanceof Course) {
            $mail_title .= ': ' . $range_object->getFullName();
        }

        foreach ($users as $user_id => $username) {
            //Load the mail template:
            $factory = new Flexi\Factory(
                $GLOBALS['STUDIP_BASE_PATH'] . '/locale/'
            );
            $user_lang_path = getUserLanguagePath($user_id);
            $template       = $factory->open(
                $user_lang_path . '/LC_MAILS/request_denied_mail.inc.php'
            );

            $mail_text  = $template->render(
                [
                    'request' => $this,
                    'range_object' => $range_object
                ]
            );

            //Send the mail:
            Message::send(
                User::findCurrent()->id,
                $username,
                $mail_title,
                $mail_text
            );
        }

    }


    public function isReadOnlyForUser(User $user)
    {
        $resource = $this->resource;
        if (!$resource) {
            //We cannot continue with the permission check.
            return false;
        }
        $resource = $resource->getDerivedClassInstance();

        return !$resource->userHasPermission($user, 'autor')
            && ($this->user_id != $user->id);
    }

    protected function convertToEventData(array $time_intervals, User $user)
    {
        $booking_plan_request_bg = ColourValue::find('Resources.BookingPlan.Request.Bg');
        $booking_plan_request_fg = ColourValue::find('Resources.BookingPlan.Request.Fg');
        $booking_plan_preparation_bg = ColourValue::find('Resources.BookingPlan.PreparationTime.Bg');
        $booking_plan_preparation_fg = ColourValue::find('Resources.BookingPlan.PreparationTime.Fg');

        $user_is_resource_autor = false;
        $user_is_resource_user = $this->resource->userHasPermission($user);
        if ($this->resource_id && $this->resource instanceof Resource) {
            $user_is_resource_autor = $this->resource->userHasPermission(
                $user,
                'autor'
            );
        }
        $request_is_editable = $user_is_resource_autor || ($user->id == $this->user_id);

        $request_api_urls  = [];
        $request_view_urls = [];

        if ($request_is_editable) {
            $request_api_urls = [
                'resize' => URLHelper::getURL(
                    'dispatch.php/resources/ajax/move_request/'. $this->id,
                    ['quiet' => true]
                ),
                'move' => URLHelper::getURL(
                    'dispatch.php/resources/ajax/move_request/'. $this->id,
                    ['quiet' => true]
                )
            ];

            $request_view_urls = [
                'edit' => URLHelper::getURL(
                    'dispatch.php/resources/room_request/edit/'
                    . $this->id
                )
            ];
            if (
                $this->resource_id
                && $this->resource instanceof Resource
                && $this->resource->userHasBookingRights($user)
            ) {
                $request_view_urls['edit'] = URLHelper::getURL(
                    'dispatch.php/resources/room_request/resolve/'. $this->id
                );
            }
        }

        $events = [];

        foreach ($time_intervals as $interval) {
            $real_begin = $interval['begin'];
            $real_end   = $interval['end'];
            if ($this->preparation_time) {
                $real_begin += $this->preparation_time;
                $begin = new DateTime();
                $begin->setTimestamp($interval['begin']);
                $end   = new DateTime();
                $end->setTimestamp($real_begin);
                $events[] = new Studip\Calendar\EventData(
                    $begin,
                    $end,
                    $user_is_resource_user ? _('Rüstzeit') : '',
                    ['preparation-time'],
                    $booking_plan_preparation_fg->__toString(),
                    $booking_plan_preparation_bg->__toString(),
                    $request_is_editable,
                    '',
                    '',
                    ResourceRequest::class,
                    $this->id,
                    Resource::class,
                    $this->resource_id,
                    $request_view_urls,
                    $request_api_urls
                );
            }
            if ($this->subsequent_time) {
                $real_end -= $this->subsequent_time;
                $begin = new DateTime();
                $begin->setTimestamp($real_end);
                $end   = new DateTime();
                $end->setTimestamp($interval['end']);
                $events[] = new Studip\Calendar\EventData(
                    $begin,
                    $end,
                    $user_is_resource_user ? _('Rüstzeit') : '',
                    ['preparation-time'],
                    $booking_plan_preparation_fg->__toString(),
                    $booking_plan_preparation_bg->__toString(),
                    $request_is_editable,
                    '',
                    '',
                    ResourceRequest::class,
                    $this->id,
                    Resource::class,
                    $this->resource_id,
                    $request_view_urls,
                    $request_api_urls
                );
            }

            $begin = new DateTime();
            $begin->setTimestamp($real_begin);
            $end = new DateTime();
            $end->setTimestamp($real_end);

            $events[] = new Studip\Calendar\EventData(
                $begin,
                $end,
                $user_is_resource_user ? $this->getRangeName() : '',
                ['resource-request'],
                $booking_plan_request_fg->__toString(),
                $booking_plan_request_bg->__toString(),
                $request_is_editable,
                ResourceRequest::class,
                $this->id,
                Resource::class,
                $this->resource_id,
                Resource::class,
                $this->resource_id,
                $request_view_urls,
                $request_api_urls
            );
        }

        return $events;
    }


    public function getAllEventData()
    {
        return $this->convertToEventData(
            $this->getTimeIntervals(true),
            User::findCurrent()
        );
    }


    public function getEventDataForTimeRange(DateTime $begin, DateTime $end)
    {
        $intervals      = $this->getTimeIntervals(true);
        $time_intervals = [];

        $begin_timestamp = $begin->getTimestamp();
        $end_timestamp   = $end->getTimestamp();

        foreach ($intervals as $interval) {
            if ((($interval['begin'] >= $begin_timestamp)
                    && ($interval['begin'] <= $end_timestamp)) ||
                (($interval['end'] >= $begin_timestamp)
                    && ($interval['end'] <= $end_timestamp)) ||
                (($interval['begin'] < $begin_timestamp)
                    && ($interval['end'] > $end_timestamp))
            ) {
                $time_intervals[] = $interval;
            }
        }

        return $this->convertToEventData($time_intervals, User::findCurrent());
    }


    public function getFilteredEventData(
        $user_id = null,
        $range_id = null,
        $range_type = null,
        $begin = null,
        $end = null
    )
    {
        $intervals      = $this->getTimeIntervals(true);
        $time_intervals = [];

        if ($begin && $end) {
            $begin_timestamp = $begin;
            $end_timestamp   = $end;
            if ($begin instanceof DateTime) {
                $begin_timestamp = $begin->getTimestamp();
            }
            if ($end instanceof DateTime) {
                $end_timestamp = $end->getTimestamp();
            }

            foreach ($intervals as $interval) {
                if ((($interval['begin'] >= $begin_timestamp)
                        && ($interval['begin'] <= $end_timestamp)) ||
                    (($interval['end'] >= $begin_timestamp)
                        && ($interval['end'] <= $end_timestamp)) ||
                    (($interval['begin'] < $begin_timestamp)
                        && ($interval['end'] > $end_timestamp))
                ) {
                    $time_intervals[] = $interval;
                }
            }
        } else {
            $time_intervals = $intervals;
        }

        if ($user_id) {
            $user = User::find($user_id);
        } else {
            $user = User::findCurrent();
        }

        return $this->convertToEventData($time_intervals, $user);
    }

    public function getPriority()
    {

        $result = $this->getTimeIntervals();
        if (count($result) === 0) {
            return null;
        }
        $first = $result[0];
        return round(($first['begin'] - time()) / 86400);
    }

    public function getLoggingInfoText()
    {
        $props = '';
        foreach ($this->getPropertyData() as $name => $state) {
            $props .= $name . '=' . json_encode($state) . ' ';
        }
        $info['Anfrage'] = $this->getType();
        $info['Status'] = $this->getStatus();
        if ($this->category) {
            $info['Raumtyp'] = $this->category->name;
        }
        if ($this->termin_id) {
            $info['Termin'] = $this->termin_id;
        }
        if ($this->metadate_id) {
            $info['Metadate'] = $this->metadate_id;
        }
        if ($props) {
            $info['Eigenschaften'] = $props;
        }
        if ($this->comment) {
            $info['Kommentar'] = $this->comment;
        }
        $txt = '';
        foreach ($info as $n => $m) {
            $txt .= $n . ': ' . $m . ', ';
        }
        return trim($txt, ' ,');
    }
}
