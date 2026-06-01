<?php
namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class ComponentVersion extends SchemaProvider
{
    const REL_SECTIONS = 'component-sections';
    const REL_START_SEMESTER = 'start-semester';
    const REL_END_SEMESTER = 'end-semester';

    protected array $allowedIncludes = [
        self::REL_SECTIONS,
        self::REL_START_SEMESTER,
        self::REL_END_SEMESTER,
    ];
    const TYPE = 'component-versions';

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'display-name' => (string) $resource->getDisplayName(),
            'code' => (string) $resource->code,
            'date' => (string) $resource->beschlussdatum,
            'version-number' => (string) $resource->fassung_nr,
            'version-type' => (string) $resource->fassung_typ,
            'description' => (string) $resource->beschreibung,
            'status' => (string) $resource->stat,
            'status-name' => \Config::get()->MVV_STGTEILVERSION['STATUS']['values'][$resource->stat]['name'],
            'type' => get_class($resource)
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        if ($semester = $this->getStartSemester($resource)) {
            $relationships[self::REL_START_SEMESTER] = $semester;
        }
        if ($semester = $this->getEndSemester($resource)) {
            $relationships[self::REL_END_SEMESTER] = $semester;
        }

        $relationships = $this->addSectionsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_SECTIONS));

        return $relationships;
    }

    private function getStartSemester(\StgteilVersion $version)
    {
        $semester = \Semester::find($version->start_sem);
        if (!$semester) {
            return null;
        }

        return [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($semester),
            ],
            self::RELATIONSHIP_DATA => $semester,
        ];
    }

    private function getEndSemester(\StgteilVersion $version)
    {
        $semester = \Semester::find($version->end_sem);
        if (!$semester) {
            return null;
        }

        return [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($semester),
            ],
            self::RELATIONSHIP_DATA => $semester,
        ];
    }

    private function addSectionsRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_SECTIONS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_SECTIONS),
            ],
        ];

        if ($includeData) {
            $relationships[self::REL_SECTIONS][self::RELATIONSHIP_DATA] = $resource->abschnitte;
        }

        return $relationships;
    }
}
