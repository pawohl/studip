<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class CalendarDateAssignment extends SchemaProvider
{
    const TYPE = 'calendar-events';
    const REL_OWNER = 'owner';

    protected ?array $allowedIncludes = [
        self::REL_OWNER,
    ];

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'title' => $resource->calendar_date->title,
            'description' => $resource->calendar_date->description,
            'start' => date('c', $resource->calendar_date->begin),
            'end' => date('c', $resource->calendar_date->end),
            'categories' => $resource->calendar_date->getCategoryAsString(),
            'location' => $resource->calendar_date->location,
            'mkdate' => date('c', $resource->calendar_date->mkdate),
            'chdate' => date('c', $resource->calendar_date->chdate),
            'recurrence' => $resource->calendar_date->getRepetitionAsString(),
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $owner = $resource->user ?? $resource->course;

        if ($owner) {
            $link = $this->createLinkToResource($owner);
            $relationships = [
                self::REL_OWNER => [self::RELATIONSHIP_LINKS => [Link::RELATED => $link], self::RELATIONSHIP_DATA => $owner],
            ];
        }

        return $relationships;
    }
}
