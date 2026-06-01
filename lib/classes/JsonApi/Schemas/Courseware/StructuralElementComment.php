<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class StructuralElementComment extends SchemaProvider
{
    const TYPE = 'courseware-structural-element-comments';

    const REL_USER = 'user';
    const REL_STRUCTURAL_ELEMENT = 'structural-element';

    protected array $allowedIncludes = [
        self::REL_USER,
        self::REL_STRUCTURAL_ELEMENT,
    ];

    /**
     * {@inheritdoc}
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'comment' => (string) $resource['comment'],
            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships[self::REL_STRUCTURAL_ELEMENT] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->structural_element),
            ],
            self::RELATIONSHIP_DATA => $resource->structural_element,
        ];

        $relationships[self::REL_USER] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->user),
            ],
            self::RELATIONSHIP_DATA => $resource->user,
        ];

        return $relationships;
    }
}
