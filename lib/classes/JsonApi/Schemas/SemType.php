<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class SemType extends SchemaProvider
{
    const REL_SEM_CLASS = 'sem-class';

    protected array $allowedIncludes = [
        self::REL_SEM_CLASS,
    ];
    const TYPE = 'sem-types';



    public function getId($resource): ?string
    {
        return $resource['id'];
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'name' => $resource['name'],
            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        // SemClass
        $related = $resource->getClass();
        $relationships[self::REL_SEM_CLASS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($related)
            ],
            self::RELATIONSHIP_DATA => $related,
        ];

        return $relationships;
    }
}
