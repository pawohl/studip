<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Schema\Link;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;

class ScheduleEntry extends SchemaProvider
{
    const TYPE = 'schedule-entries';
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
        return [
            'title' => $entry->label,
            'description' => mb_strlen(trim($entry->content)) ? $entry->content : null,

            'start' => $this->formatTime($entry->start_time),
            'end' => $this->formatTime($entry->end_time),
            'weekday' => (int) $entry->dow,

            'color' => '',
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($entry, ContextInterface $context): iterable
    {
        $link = $this->createLinkToResource($entry->user);

        $relationships = [
            self::REL_OWNER => [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $link,
                ],
                self::RELATIONSHIP_DATA => $entry->user,
            ],
        ];

        return $relationships;
    }

    private function formatTime($time)
    {
        return sprintf('%02d:%02d', (int) ($time / 100), (int) ($time % 100));
    }
}
