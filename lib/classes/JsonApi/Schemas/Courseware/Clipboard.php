<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Clipboard extends SchemaProvider
{
    const TYPE = 'courseware-clipboards';

    const REL_USER = 'user';
    const REL_STRUCTURAL_ELEMENT = 'structural-element';
    const REL_CONTAINER = 'container';
    const REL_BLOCK = 'block';

    protected ?array $allowedIncludes = [
        self::REL_USER,
        self::REL_STRUCTURAL_ELEMENT,
        self::REL_CONTAINER,
        self::REL_BLOCK,
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
            'name' => (string) $resource->name,
            'description' => (string) $resource->description,
            'block-id' => (int) $resource->block_id,
            'container-id' => (int) $resource->container_id,
            'structural-element-id' => (int) $resource->structural_element_id,
            'object-type' => (string) $resource->object_type,
            'object-kind' => (string) $resource->object_kind,
            'backup' => $resource->backup,
            'mkdate'    => date('c', $resource->mkdate),
            'chdate'    => date('c', $resource->chdate),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships[self::REL_USER] = $resource->user
            ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->user),
                ],
                self::RELATIONSHIP_DATA => $resource->user,
            ]
            : [self::RELATIONSHIP_DATA => null];

        $relationships[self::REL_BLOCK] = $resource->block
        ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->block),
                ],
                self::RELATIONSHIP_DATA => $resource->block,
            ]
            : [self::RELATIONSHIP_DATA => null];

        $relationships[self::REL_CONTAINER] = $resource->container
            ? [
                    self::RELATIONSHIP_LINKS => [
                        Link::RELATED => $this->createLinkToResource($resource->container),
                    ],
                    self::RELATIONSHIP_DATA => $resource->container,
                ]
                : [self::RELATIONSHIP_DATA => null];

        $relationships[self::REL_STRUCTURAL_ELEMENT] = $resource->structural_element
            ? [
                    self::RELATIONSHIP_LINKS => [
                        Link::RELATED => $this->createLinkToResource($resource->structural_element),
                    ],
                    self::RELATIONSHIP_DATA => $resource->structural_element,
                ]
                : [self::RELATIONSHIP_DATA => null];

        return $relationships;
    }
}
