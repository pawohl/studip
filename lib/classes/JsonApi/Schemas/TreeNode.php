<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaContainerInterface;
use Neomerx\JsonApi\Schema\Link;

class TreeNode extends SchemaProvider
{
    const REL_CHILDREN = 'children';
    const REL_COURSEINFO = 'courseinfo';
    const REL_COURSES = 'courses';
    const REL_INSTITUTE = 'institute';
    const REL_PARENT = 'parent';

    protected ?array $allowedIncludes = [
        self::REL_CHILDREN,
        self::REL_COURSEINFO,
        self::REL_COURSES,
        self::REL_INSTITUTE,
        self::REL_PARENT,
    ];

    const TYPE = 'tree-node';

    public function getId($resource): ?string
    {
        return get_class($resource) . '_' . $resource['id'];
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $schema = [
            'id' => (string) $resource->getId(),
            'name' => (string) $resource->getName(),
            'description' => (string) $resource->getDescription(),
            'description-formatted' => (string) formatReady($resource->getDescription()),
            'has-children' => (bool) $resource->hasChildNodes(),
            'ancestors' => (array) $resource->getAncestors(),
            'classname' => get_class($resource),
            'visible' => true,
            'editable' => true,
            'assignable' => true
        ];

        // Some special options for sem_tree entries.
        if (get_class($resource) === 'StudipStudyArea') {
            if ($GLOBALS['SEM_TREE_TYPES'][$resource->type]['hidden'] ?? false) {
                $schema['visible'] = false;
            }
            if ($GLOBALS['SEM_TREE_TYPES'][$resource->type]['editable'] ?? false) {
                $schema['editable'] = false;
            }
            if (!\Config::get()->SEM_TREE_ALLOW_BRANCH_ASSIGN && $resource->hasChildNodes()) {
                $schema['assignable'] = false;
            }
        }

        return $schema;
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addChildrenRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_CHILDREN));

        if (property_exists($resource, 'courses') || method_exists($resource, 'getCourses')) {
            $relationships = $this->addCourseInfoRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_COURSEINFO));
            $relationships = $this->addCoursesRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_COURSES));
        }
        $relationships = $this->addInstituteRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_INSTITUTE));
        $relationships = $this->addParentRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_PARENT));

        return $relationships;
    }

    private function addChildrenRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_CHILDREN] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_CHILDREN),
            ]
        ];

        if ($includeData) {
            $children = $resource->getChildNodes();
            $relationships[self::REL_CHILDREN][self::RELATIONSHIP_DATA] = $children;
        }

        return $relationships;
    }


    private function addCourseInfoRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_COURSEINFO] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_COURSEINFO),
            ],
        ];

        if ($includeData) {
            $children = $resource->courses;
            $relationships[self::REL_COURSES][self::RELATIONSHIP_DATA] = $children;
        }

        return $relationships;
    }

    private function addCoursesRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_COURSES] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_COURSES)
            ]
        ];

        if ($includeData) {
            $courses = $resource->courses;
            $relationships[self::REL_COURSES][self::RELATIONSHIP_DATA] = $courses;
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

    private function addParentRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_PARENT] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_PARENT)
            ],
        ];

        if ($includeData) {
            $relationships[self::REL_PARENT][self::RELATIONSHIP_DATA] = $resource->getParent();
        }

        return $relationships;
    }
}
