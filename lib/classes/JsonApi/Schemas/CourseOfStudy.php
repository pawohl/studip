<?php
namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class CourseOfStudy extends SchemaProvider
{
    const REL_SECTIONS = 'sections';
    const REL_INSTITUTE = 'institute';
    const REL_COMPONENTS = 'components';
    const REL_DEGREE = 'degree';
    const REL_END_SEMESTER = 'end-semester';
    const REL_START_SEMESTER = 'start-semester';

    protected array $allowedIncludes = [
        self::REL_SECTIONS,
        self::REL_INSTITUTE,
        self::REL_COMPONENTS,
        self::REL_DEGREE,
        self::REL_END_SEMESTER,
        self::REL_START_SEMESTER,
    ];
    const TYPE = 'courses-of-study';

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'display-name' => (string) $resource->getDisplayName(),
            'name' => (string) $resource->name,
            'short-name' => (string) $resource->name_kurz,
            'type' => (string) $resource->typ,
            'status' => (string) $resource->stat,
            'status-name' => \Config::get()->MVV_STUDIENGANG['STATUS']['values'][$resource->stat]['name'] ?? '',
            'classname' => get_class($resource)
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $institute = \Institute::find($resource->institut_id);
        if ($institute) {
            $relationships[self::REL_INSTITUTE] = $this->getInstitute($resource, $this->shouldInclude($context, self::REL_INSTITUTE));
        }

        if ($semester = $this->getStartSemester($resource)) {
            $relationships[self::REL_START_SEMESTER] = $semester;
        }
        if ($semester = $this->getEndSemester($resource)) {
            $relationships[self::REL_END_SEMESTER] = $semester;
        }

        $relationships = $this->addSectionsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_SECTIONS));
        $relationships = $this->addComponentsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_COMPONENTS));
        $relationships = $this->addDegreeRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_DEGREE));

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getInstitute(\Studiengang $course_of_study, $shouldInclude)
    {
        $institute = \Institute::find($course_of_study->institut_id);
        return $institute
            ?  [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($institute),
                ],
                self::RELATIONSHIP_DATA => $institute,
            ]
            : [
                self::RELATIONSHIP_DATA => null,
            ];
    }

    private function getStartSemester(\Studiengang $course_of_study)
    {
        $semester = \Semester::find($course_of_study->start);
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

    private function getEndSemester(\Studiengang $course_of_study)
    {
        $semester = \Semester::find($course_of_study->end);
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
            $relationships[self::REL_SECTIONS][self::RELATIONSHIP_DATA] = $resource->stgteil_bezeichnungen;
        }

        return $relationships;
    }

    private function addComponentsRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_COMPONENTS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_COMPONENTS),
            ],
        ];

        if ($includeData) {
            $relationships[self::REL_COMPONENTS][self::RELATIONSHIP_DATA] = $resource->studiengangteile;
        }

        return $relationships;
    }

    private function addDegreeRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_DEGREE] = [
            self::RELATIONSHIP_LINKS_SELF => $this->createLinkToResource($resource->abschluss),
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_DEGREE),
            ],
        ];

        if ($includeData) {
            $relationships[self::REL_DEGREE][self::RELATIONSHIP_DATA] = $resource->abschluss;
        }

        return $relationships;
    }
}
