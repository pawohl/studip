<?php
namespace JsonApi\Schemas;

use Fach;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Subject extends SchemaProvider
{
    const REL_DEPARTMENTS = 'departments';

    protected array $allowedIncludes = [
        self::REL_DEPARTMENTS,
    ];
    const TYPE = 'subjects';

    /**
     * @param Fach $resource
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * @param Fach $resource
     */
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

        $relationships = $this->addDepartmentsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_DEPARTMENTS));

        return $relationships;
    }

    private function addDepartmentsRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_DEPARTMENTS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_DEPARTMENTS),
            ],
        ];

        if ($includeData) {
            // use institute schema
            if (!empty($resource->departments)) {
                $institutes = \Institute::findMany($resource->departments->pluck('id'));
                $relationships[self::REL_DEPARTMENTS][self::RELATIONSHIP_DATA] = $institutes;
            }
        }

        return $relationships;
    }
}
