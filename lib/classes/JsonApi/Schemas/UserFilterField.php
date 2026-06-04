<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class UserFilterField extends SchemaProvider
{
    const TYPE = 'user-filter-fields';

    const REL_USERS = 'users';

    protected ?array $allowedIncludes = [
        self::REL_USERS,
    ];

    public function getId($resource): ?string
    {
        return get_class($resource) . '_' . $resource->getId();
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'type' => get_class($resource),
            'typeparam' => property_exists($resource, 'datafield_id') ? $resource->datafield_id : null,
            'id' => $resource->getId(),
            'compare-operator' => $resource->getCompareOperator(),
            'compare-operator-text' => $resource->getCompareOperatorAsText(),
            'name' => $resource->getName(),
            'valid-compare-operators' => $resource->getValidCompareOperators(),
            'valid-values' => $resource->getValidValues(),
            'value' => $resource->getValue()
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addUsersRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_USERS)
        );

        return $relationships;
    }

    private function addUsersRelationship(
        array $relationships,
              $resource,
              $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_USERS),
            ]
        ];
        if ($includeData) {
            $related = $resource->getUsers();
            $relation[self::RELATIONSHIP_DATA] = $related;
        }

        return array_merge($relationships, [self::REL_USERS => $relation]);
    }
}
