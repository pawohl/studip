<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class CourseEvent extends SchemaProvider
{
    const TYPE = 'course-events';
    const REL_OWNER = 'owner';

    protected array $allowedIncludes = [
        self::REL_OWNER,
    ];

    /**
     * @param \CourseDate|\CourseExDate $resource
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * @param \CourseDate|\CourseExDate $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'title' => isset($resource->course) ? $resource->course->getFullName() : '',
            'description' => $resource->getDescription(),
            'start' => date('c', $resource->date),
            'end' => date('c', $resource->end_time),
            'type' => (int) $resource->date_typ,
            'categories' => $GLOBALS['TERMIN_TYP'][$resource->date_typ]['name'] ?? null,
            'location' => $resource->raum ?? '',
            'is-cancelled' => $resource instanceof \CourseExDate,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate),
            'recurrence' => isset($resource->cycle) ? $resource->cycle->toString() : '',
        ];
    }

    /**
     * @param \CourseDate|\CourseExDate $resource
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $owner = $resource->course;
        if ($owner) {
            $link = $this->createLinkToResource($owner);
            $relationships = [
                self::REL_OWNER => [self::RELATIONSHIP_LINKS => [Link::RELATED => $link], self::RELATIONSHIP_DATA => $owner],
            ];
        }

        return $relationships;
    }
}
