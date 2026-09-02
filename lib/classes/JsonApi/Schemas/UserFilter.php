<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;

class UserFilter extends SchemaProvider
{
    const TYPE = 'user-filters';

    public function getId($userfilter): ?string
    {
        return $userfilter->getId();
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $fields = array_map(
            fn($field) => [
                'attributes' => [
                    'type' => get_class($field),
                    'typeparam' => property_exists($field, 'datafield_id') ? $field->datafield_id : null,
                    'id' => $field->getId(),
                    'compare-operator' => $field->getCompareOperator(),
                    'value' => $field->getValue(),
                ]
            ],
            array_values($resource->getFields())
        );

        $resource->show_user_count = true;
        return [
            'provisional_id' => $resource->getId() ?: $resource->generateId('admissionrules'),
            'text' => $resource->toString(),
            'fields' => $fields
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        return [];
    }
}
