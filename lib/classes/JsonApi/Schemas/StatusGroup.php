<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class StatusGroup extends SchemaProvider
{
    const TYPE = 'status-groups';

    const REL_RANGE = 'range';

    protected ?array $allowedIncludes = [
        self::REL_RANGE,
    ];

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $stringOrNull = function ($item) {
            return trim($item) != '' ? (string) $item : null;
        };

        $dateOrNull = function ($item) {
            return $item ? date('c', $item) : null;
        };

        return [
            'name' => (string) $resource['name'],
            'full-name' => $resource->getFullName(),
            'description' => $stringOrNull($resource['description']),
            'female-name' => $stringOrNull($resource['name_w']),
            'male-name' => $stringOrNull($resource['name_m']),
            'position' => (int) $resource['position'],
            'size' => (int) $resource['size'],

            'selfassign' => (bool) $resource['selfassign'],
            'selfassign-start' => $dateOrNull($resource['selfassign_start']),
            'selfassign-end' => $dateOrNull($resource['selfassign_end']),

            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addRangeRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_RANGE)
        );

        return $relationships;
    }

    private function addRangeRelationship(
        array $relationships,
        \Statusgruppen $resource,
        $includeData
    ) {
        $relationships[self::REL_RANGE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->range)
            ],
            self::RELATIONSHIP_DATA => $resource->range,
        ];

        return $relationships;
    }
}
