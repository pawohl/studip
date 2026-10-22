<?php

/**
 * CalendarCourseDate is a specialisation of CourseDate for
 * course dates that are displayed in the personal calendar.
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
 * @property int|null $number_of_participants database column
 * @property SimpleORMapCollection<Folder> $folders has_many Folder
 * @property SimpleORMapCollection<RoomRequest> $room_requests has_many RoomRequest
 * @property SimpleORMapCollection<ResourceRequestAppointment> $resource_request_appointments has_many ResourceRequestAppointment
 * @property User $author belongs_to User
 * @property Course $course belongs_to Course
 * @property SeminarCycleDate|null $cycle belongs_to SeminarCycleDate
 * @property ResourceBooking $room_booking has_one ResourceBooking
 * @property SimpleORMapCollection<CourseTopic> $topics has_and_belongs_to_many CourseTopic
 * @property SimpleORMapCollection<Statusgruppen> $statusgruppen has_and_belongs_to_many Statusgruppen
 * @property SimpleORMapCollection<User> $dozenten has_and_belongs_to_many User
 */
class CalendarCourseDate extends CourseDate
{
    public static function getEvents(DateTime $begin, DateTime $end, string $range_id): array
    {
        $events = [];
        parent::findEachBySQL(
            function ($e) use (&$events, $range_id) {
                if (self::checkRelated($e, $range_id)) {
                    $events[] = $e;
                }
            },
            "JOIN `seminar_user`
               ON `seminar_user`.`seminar_id` = `termine`.`range_id`
             WHERE `seminar_user`.`user_id` = :user_id
               AND `seminar_user`.`bind_calendar` = '1'
               AND `termine`.`date` BETWEEN :begin AND :end
               AND (
                   IFNULL(`termine`.`metadate_id`, '') = ''
                   OR `termine`.`metadate_id` NOT IN (
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
        return $events;
    }

    /**
     * Checks if given user is the responsible lecturer or is member of a
     * related group.
     *
     * @global object $perm The global perm object.
     * @param CalendarCourseDate $event The course event to check against.
     * @param string $user_id The id of the user.
     * @return boolean
     */
    protected static function checkRelated(CalendarCourseDate $event, string $user_id): bool
    {
        $check_related = false;
        $permission = $GLOBALS['perm']->get_studip_perm($event->range_id, $user_id);
        switch ($permission) {
            case 'dozent' :
                $related_persons = $event->dozenten->pluck('user_id');
                if (count($related_persons) > 0) {
                    $check_related = in_array($user_id, $related_persons);
                } else {
                    $check_related = true;
                }
                break;
            case 'tutor' :
                $check_related = true;
                break;
            default :
                $group_ids = $event->statusgruppen->pluck('statusgruppe_id');
                if (count($group_ids) > 0) {
                    $member = StatusgruppeUser::findBySQL(
                        'statusgruppe_id IN(?) AND user_id = ?',
                        [$group_ids, $user_id]);
                    $check_related = count($member) > 0;
                } else {
                    $check_related = true;
                }
        }
        return $check_related;
    }

    public function toEventData(string $user_id): \Studip\Calendar\EventData
    {
        $event_data = parent::toEventData($user_id);
        //The title is different from ordinary course dates:
        //It contains the names of the lecturers and the rooms, too. Furthermore,
        //if the course date is part of a regular date which has a description,
        //the description shall be appended to the title.
        if ($this->course) {
            $lecturer_names = array_map(
                fn($lecturer) => $lecturer->user->nachname,
                CourseMember::findByCourseAndStatus($this->range_id, 'dozent')
            );
            sort($lecturer_names);
            $event_data->title = studip_interpolate(
                '%{course_name} (%{lecturer_names})',
                [
                    'course_name' => $this->course->getFullName(),
                    'lecturer_names' => implode(', ', $lecturer_names)
                ]
            );
            if ($this->cycle) {
                // Add the room, if available:
                $room_name = $this->cycle->getMostBookedRoom()?->getFullName()
                    ?? $this->cycle->getMostUsedFreetextRoomName();
                if ($room_name) {
                    $event_data->title .= "\n" . $room_name;
                }
                if ($this->cycle->description) {
                    $event_data->title .= sprintf(' (%s)', $this->cycle->description);
                }
            }
        }
        return $event_data;
    }
}
