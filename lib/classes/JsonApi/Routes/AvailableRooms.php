<?php

namespace Studip\JsonApi\Routes;

use JsonApi\NonJsonApiController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AvailableRooms extends NonJsonApiController
{
    public function __invoke(Request $request, Response $response, $args)
    {
        $raw_time_ranges = \Request::get('time_ranges');
        if ($raw_time_ranges) {
            $raw_time_ranges = json_decode($raw_time_ranges, true);
        } else {
            $raw_time_ranges = [];
        }
        $course_date_ids = \Request::get('course_date_ids');
        if ($course_date_ids) {
            $course_date_ids = explode(',', $course_date_ids);
        }
        $current_user = \User::findCurrent();
        if (empty($raw_time_ranges)) {
            //No time ranges given.
            return $response->withStatus(400, 'No time ranges given.');
        }
        //Convert the time ranges to the appropriate format:
        $time_ranges = [];
        foreach ($raw_time_ranges as $raw_time_range) {
            $start_str = $raw_time_range['start'] ?? '';
            $end_str = $raw_time_range['end'] ?? '';
            if (!$start_str || !$end_str) {
                //Invalid time range.
                return $response->withStatus(400, 'Invalid time range.');
            }
            //The timestamps are either in the extended RFC3339 format or in unix timestamps.
            $timezone = new \DateTime();
            $start_datetime = \DateTime::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $start_str, $timezone->getTimezone());
            $end_datetime   = \DateTime::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $end_str, $timezone->getTimezone());
            if (!$start_datetime || !$end_datetime) {
                //Try unix timestamps as fallback.
                $start_datetime = \DateTime::createFromFormat('U', $start_str, $timezone->getTimezone());
                $end_datetime   = \DateTime::createFromFormat('U', $end_str, $timezone->getTimezone());
            }
            if (!$start_datetime || !$end_datetime) {
                //Invalid date or time format.
                return $response->withStatus(400, 'Invalid date or time format.');
            }
            $time_ranges[] = [
                'begin' => $start_datetime,
                'end' => $end_datetime
            ];
        }

        if (!\ResourceManager::userHasGlobalPermission($current_user, 'autor')
            && !\ResourceManager::userHasResourcePermissions($current_user, 'autor')) {
            //The user must not book any room.
            throw new \AccessDeniedException();
        }

        //Collect the booking-IDs for the course date:
        $booking_ids = [];
        if (!empty($course_date_ids)) {
            $course_dates = \CourseDate::findMany($course_date_ids);
            if ($course_dates) {
                foreach ($course_dates as $course_date) {
                    foreach ($course_date->room_bookings as $booking) {
                        $booking_ids[] = $booking->id;
                    }
                }
            }
        }

        $available_rooms = \RoomManager::findRooms(
            '',
            null,
            null,
            [],
            $time_ranges,
            'name ASC',
            false,
            [],
            true,
            $booking_ids
        );

        $bookable_rooms = [];
        foreach ($available_rooms as $room) {
            $all_ranges_bookable = true;
            foreach ($time_ranges as $time_range) {
                if (empty($time_range['begin']) || empty($time_range['end'])) {
                    //Invalid time range. We cannot continue.
                    return $response->withStatus(400, 'Invalid time range.');
                }
                if (!$room->userHasBookingRights($current_user, $time_range['begin'], $time_range['end'])) {
                    $all_ranges_bookable = false;
                    break;
                }
            }
            if ($all_ranges_bookable) {
                $bookable_rooms[$room->id] = $room;
            }
        }

        $separable_rooms = \SeparableRoom::findBySQL(
            "JOIN `separable_room_parts` srp
                ON `separable_rooms`.`id` = `srp`.`separable_room_id`
                WHERE `srp`.`room_id` IN ( :room_ids )
                GROUP BY `separable_rooms`.`id`",
            [
                'room_ids' => array_keys($bookable_rooms)
            ]
        );

        $selectable_room_data = [];

        foreach ($separable_rooms as $separable_room) {
            //Check if all the room parts are available and bookable. If so, include the separable room
            //in the $selectable_room_data array.

            $unavailable_parts = \SeparableRoomPart::countBySQL(
                    "`separable_room_id` = :separable_room_id
                AND `room_id` NOT IN ( :room_ids )",
                    [
                        'separable_room_id' => $separable_room->id,
                        'room_ids' => array_keys($bookable_rooms)
                    ]
                ) > 0;

            if (!$unavailable_parts) {
                //The separable room is fully available. Include it in the list of bookable rooms
                //before its room parts.
                $selectable_room_data[] = [
                    'id' => sprintf('separable_room-%s', $separable_room->id),
                    'name' => sprintf(
                        '%1$s (%2$s)',
                        $separable_room->name,
                        _('Teilbarer Raum')
                    ),
                    'separable_room_id' => $separable_room->id,
                    'info_text' => sprintf('%1$s: %2$s', $separable_room->name, $separable_room->description)
                ];
            }

            //Add the room parts from the $bookable_rooms array:
            $room_part_data = [];
            foreach ($separable_room->parts as $part) {
                if (in_array($part->room_id, array_keys($bookable_rooms))) {
                    $room = $bookable_rooms[$part->room_id];

                    $room_part_data[] = [
                        'id' => sprintf('room-%s', $room->id),
                        'name' => studip_interpolate(
                            _('%{room_name} (%{seats} Sitzplätze) [Teil von %{separable_room_name}]'),
                            [
                                'room_name' => $room->getFullName(),
                                'seats' => $room->seats ?? 0,
                                'separable_room_name' => $separable_room->name
                            ]
                        ),
                        'separable_room_id' => $separable_room->id,
                        'info_text' => sprintf('%1$s: %2$s', $separable_room->name, $separable_room->description)
                    ];

                    unset($bookable_rooms[$part->room_id]);
                }
            }

            //Sort $room_part_data by name:
            uasort($room_part_data, function ($a, $b) {
                if ($a['name'] > $b['name']) {
                    return 1;
                } elseif ($a['name'] < $b['name']) {
                    return -1;
                } else {
                    return 0;
                }
            });

            $selectable_room_data = array_merge($selectable_room_data, $room_part_data);
        }

        //Add all remaining rooms:
        foreach ($bookable_rooms as $room) {
            $selectable_room_data[] = [
                'id' => sprintf('room-%s', $room->id),
                'name' => studip_interpolate(
                    _('%{room_name} (%{seats} Sitzplätze)'),
                    [
                        'room_name' => $room->getFullName(),
                        'seats' => $room->seats ?? 0
                    ]
                )
            ];
        }

        $response->getBody()->write(json_encode($selectable_room_data));
        return $response;
    }
}
