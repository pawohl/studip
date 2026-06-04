<?php
namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class ComponentSection extends SchemaProvider
{
    const REL_MODULES = 'modules';

    protected ?array $allowedIncludes = [
        self::REL_MODULES,
    ];
    const TYPE = 'component-sections';

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'display-name' => (string) $resource->getDisplayName(),
            'comment' => $resource->kommentar,
            'position' => $resource->position,
            'cp' => $resource->kp,
            'caption' => $resource->ueberschrift,
            'type' => get_class($resource)
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addModulesRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_MODULES));

        return $relationships;
    }

    private function addModulesRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_MODULES] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_MODULES),
            ],
        ];

        if ($includeData) {
            $relationships[self::REL_MODULES][self::RELATIONSHIP_DATA] = $resource->module;
        }

        return $relationships;
    }
}
