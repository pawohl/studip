<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class SeminarCycleDate extends SchemaProvider
{
    const TYPE = 'seminar-cycle-dates';
    const REL_OWNER = 'owner';

    protected array $allowedIncludes = [
        self::REL_OWNER,
    ];

    public function getId($entry): ?string
    {
        return $entry->id;
    }

    public function getAttributes($entry, ContextInterface $context): iterable
    {
        $course = \Course::find($entry->seminar_id);

        return [
            'title' => self::createTitle($course),
            'description' => mb_strlen(trim($entry->description)) ? $entry->description : null,

            'start' => sprintf('%02d:%02d', $entry->start_hour, $entry->start_minute),
            'end' => sprintf('%02d:%02d', $entry->end_hour, $entry->end_minute),
            'weekday' => (int) $entry->weekday,

            'recurrence' => $this->getRecurring($entry),

            'locations' => self::createLocation($entry),
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($entry, ContextInterface $context): iterable
    {
        $relationships = [];

        if ($course = \Course::find($entry->seminar_id)) {
            $link = $this->createLinkToResource($course);
            $relationships = [
                self::REL_OWNER => [self::RELATIONSHIP_LINKS => [Link::RELATED => $link], self::RELATIONSHIP_DATA => $course],
            ];
        }

        return $relationships;
    }

    private function getRecurring($entry)
    {
        $dateFn = function ($date) {
            return self::icalDate($date['date']);
        };

        $recurring = [
            'FREQ' => 'WEEKLY',
            'INTERVAL' => $entry->cycle + 1,
            'DTSTART' => $dateFn($entry->dates->first()),
            'UNTIL' => $dateFn($entry->dates->last()),
        ];

        if (count($entry->exdates)) {
            $recurring['EXDATES'] = $entry->exdates->map($dateFn);
        }

        return $recurring;
    }

    private static function icalDate($dateTime0)
    {
        return date('c', $dateTime0);
    }

    private static function createTitle($course)
    {
        if (!isset($course)) {
            return null;
        }

        if ($course->veranstaltungsnummer) {
            $title = sprintf('%s %s', $course->veranstaltungsnummer, $course->name);
        } else {
            $title = $course->name;
        }

        return $title;
    }

    private static function createLocation(\SeminarCycleDate $entry)
    {
        // check, if the date is assigned to a room
        if ($rooms = $entry->getMostBookedRooms()) {
            return array_unique(getPlainRooms($rooms));
        } elseif ($rooms = $entry->getMostUsedFreetextRoomNames()) {
            return $rooms;
        }

        return [];
    }
}
