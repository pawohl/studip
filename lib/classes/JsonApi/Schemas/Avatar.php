<?php

namespace JsonApi\Schemas;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Avatar extends SchemaProvider
{
    public const TYPE = 'avatar';
    const REL_RANGE = 'range';

    protected ?array $allowedIncludes = [
        self::REL_RANGE,
    ];

    public function getId($resource): ?string
    {
        return $resource->getId();
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'type' => $resource::AVATAR_TYPE,
            'customized' => $resource->is_customized(),
            'is-nobody' => $resource->isNobody(),
        ];
    }
    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    public function getResourceMeta($resource)
    {
        return [
            'url' => [
                'normal' => $resource->getURL(\Avatar::NORMAL),
                'medium' => $resource->getURL(\Avatar::MEDIUM),
                'small' => $resource->getURL(\Avatar::SMALL),
            ]
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];
        $range = self::getRange($resource->getId(),  $resource::AVATAR_TYPE);
        $relationships[self::REL_RANGE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($range),
            ],
            self::RELATIONSHIP_DATA => $range,
        ];
        return $relationships;
    }

    private function getRange(String $range_id, String $range_type)
    {
        switch ($range_type) {
            case 'course':
            case 'studygroup':
                return \Course::build(['id' => $range_id], false);
            case 'user':
                return \User::build(['id' => $range_id], false);
            case 'institute':
                return \Institute::build(['id' => $range_id], false);
        }
        return null;
    }
}
