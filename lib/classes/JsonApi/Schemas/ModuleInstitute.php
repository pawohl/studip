<?php
namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class ModuleInstitute extends SchemaProvider
{
    const REL_MODULE = 'modules';
    const REL_INSTITUTE = 'institutes';

    protected array $allowedIncludes = [
        self::REL_MODULE,
        self::REL_INSTITUTE,
    ];
    const TYPE = 'module-institutes';

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'name' => (string) $resource->name,
            'short-name' => (string) $resource->name_kurz,
            'description' => (string) $resource->beschreibung,
            'type' => get_class($resource)
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addModuleRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_MODULE));
        $relationships = $this->addInstituteRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_INSTITUTE));

        return $relationships;
    }

    private function addModuleRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_MODULE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_MODULE),
            ],
        ];

        if ($includeData) {
            $relationships[self::REL_MODULE][self::RELATIONSHIP_DATA] = $resource->module;
        }

        return $relationships;
    }

    private function addInstituteRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_INSTITUTE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_INSTITUTE),
            ],
        ];

        if ($includeData) {
            $relationships[self::REL_INSTITUTE][self::RELATIONSHIP_DATA] = $resource->institute;
        }

        return $relationships;
    }
}
