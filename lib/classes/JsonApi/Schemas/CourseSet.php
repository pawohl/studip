<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class CourseSet extends SchemaProvider
{
    const TYPE = 'course-sets';

    const REL_RULES = 'admission-rules';
    const REL_INSTITUTES = 'institutes';
    const REL_SEMESTER = 'semester';
    const REL_COURSES = 'courses';
    const REL_OWNER = 'owner';

    protected ?array $allowedIncludes = [
        self::REL_RULES,
        self::REL_INSTITUTES,
        self::REL_SEMESTER,
        self::REL_COURSES,
        self::REL_OWNER,
    ];

    public function getId($courseset): ?string
    {
        return $courseset->getId();
    }

    public function getAttributes($courseset, ContextInterface $context): iterable
    {
        return [
            'name' => $courseset->getName(),
            'infotext' => $courseset->getInfoText(),
            'private' => (bool) $courseset->getPrivate(),
            'algorithm' => $courseset->getAlgorithm(),
            'algorithm-run' => (bool) $courseset->hasAlgorithmRun(),
            'num-applicants' => (int) $courseset->getNumApplicants(),
            'userlists' => (array) $courseset->getUserLists(),
            'chdate' => date('c', $courseset->getChdate()),
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addOwnerRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_OWNER)
        );

        $relationships = $this->addInstitutesRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_INSTITUTES)
        );

        $relationships = $this->addCoursesRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_COURSES)
        );

        $relationships = $this->addRulesRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_RULES)
        );

        $relationships = $this->addSemesterRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_SEMESTER)
        );

        return $relationships;
    }

    private function addRulesRelationship(
        array $relationships,
              $resource,
              $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_RULES),
            ]
        ];
        if ($includeData) {
            $related = $resource->getAdmissionRules();
            $relation[self::RELATIONSHIP_DATA] = $related;
        }

        return array_merge($relationships, [self::REL_RULES => $relation]);
    }

    private function addOwnerRelationship(
        array $relationships,
              $resource,
              $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_OWNER),
            ]
        ];
        if ($includeData) {
            $related = $resource->getUserId() ? \User::find($resource->getUserId()) : null;
            $relation[self::RELATIONSHIP_DATA] = $related;
        }

        return array_merge($relationships, [self::REL_OWNER => $relation]);
    }

    private function addInstitutesRelationship(
        array $relationships,
              $resource,
              $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_INSTITUTES),
            ]
        ];
        if ($includeData) {
            $related = $resource->getInstituteIds() ? \Institute::findMany(array_keys($resource->getInstituteIds())) : [];
            $relation[self::RELATIONSHIP_DATA] = $related;
        }

        return array_merge($relationships, [self::REL_INSTITUTES => $relation]);
    }

    private function addCoursesRelationship(
        array $relationships,
              $resource,
              $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_COURSES),
            ]
        ];
        if ($includeData) {
            $related = \Course::findMany($resource->getCourses(), "ORDER BY `VeranstaltungsNummer`, `Name`");
            $relation[self::RELATIONSHIP_DATA] = $related;
        }

        return array_merge($relationships, [self::REL_COURSES => $relation]);
    }

    private function addSemesterRelationship(
        array $relationships,
              $resource,
              $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_SEMESTER),
            ]
        ];
        if ($includeData) {
            $related = $resource->getSemester() ? \Semester::find($resource->getSemester()) : null;
            $relation[self::RELATIONSHIP_DATA] = $related;
        }

        return array_merge($relationships, [self::REL_SEMESTER => $relation]);
    }
}
