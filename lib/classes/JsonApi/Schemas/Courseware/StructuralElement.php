<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Identifier;
use Neomerx\JsonApi\Schema\Link;

class StructuralElement extends SchemaProvider
{
    const TYPE = 'courseware-structural-elements';

    const REL_ANCESTORS = 'ancestors';
    const REL_CHILDREN = 'children';
    const REL_CONTAINERS = 'containers';
    const REL_COURSE = 'course';
    const REL_DESCENDANTS = 'descendants';
    const REL_EDITBLOCKER = 'edit-blocker';
    const REL_EDITOR = 'editor';
    const REL_IMAGE = 'image';
    const REL_OWNER = 'owner';
    const REL_PARENT = 'parent';
    const REL_USER = 'user';
    const REL_TASK = 'task';
    const REL_UNIT = 'unit';
    const REL_FEEDBACKELEMENT = 'feedback-element';

    protected ?array $allowedIncludes = [
        self::REL_ANCESTORS,
        self::REL_CHILDREN,
        self::REL_CONTAINERS,
        self::REL_COURSE,
        self::REL_DESCENDANTS,
        self::REL_EDITBLOCKER,
        self::REL_EDITOR,
        self::REL_IMAGE,
        self::REL_OWNER,
        self::REL_PARENT,
        self::REL_USER,
        self::REL_TASK,
        self::REL_UNIT,
        self::REL_FEEDBACKELEMENT,
    ];

    /**
     * {@inheritdoc}
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $user = $this->currentUser;

        return [
            'position' => (int) $resource['position'],
            'title' => (string) $resource['title'],
            'purpose' => (string) $resource['purpose'],
            'payload' => $resource['payload']->getIterator(),
            'public' => (int) $resource['public'],
            'permission-type' => (string) $resource['permission_type'],
            'visible' => (string) $resource['visible'],
            'visible-all' => (bool) $resource['visible_all'],
            'visible-start-date' => $resource['visible_start_date'] ? date('c', $resource['visible_start_date']) : null,
            'visible-end-date' => $resource['visible_end_date'] ? date('c', $resource['visible_end_date']) : null,
            'writable' => (string) $resource['writable'],
            'writable-all' => (bool) $resource['writable_all'],
            'writable-start-date' => $resource['writable_start_date'] ? date('c', $resource['writable_start_date']) : null,
            'writable-end-date' => $resource['writable_end_date'] ? date('c', $resource['writable_end_date']) : null,
            'visible-approval' => json_decode($resource['visible_approval']),
            'writable-approval' => json_decode($resource['writable_approval']),
            'content-approval' => $resource['content_approval']->getIterator(),
            'copy-approval' => $resource['copy_approval']->getIterator(),
            'can-edit' => $resource->canEdit($user),
            'can-visit' => $resource->canVisit($user),
            'is-link' => (int) $resource['is_link'],
            'commentable' => (bool) $resource['commentable'],
            'target-id' => (int)  $resource['target_id'],
            'external-relations' => $resource['external_relations']->getIterator(),
            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param StructuralElement $resource
     * @param ContextInterface $context
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addChildrenRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_CHILDREN)
        );

        $relationships = $this->addContainersRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_CONTAINERS)
        );

        $relationships = $this->addRangeRelationship($relationships, $resource, $context);

        $relationships = $this->addOwnerRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_OWNER)
        );

        $relationships = $this->addEditorRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_EDITOR)
        );

        $relationships = $this->addEditBlockerRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_EDITBLOCKER)
        );

        $relationships = $this->addParentRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_PARENT)
        );

        $relationships = $this->addAncestorsRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_ANCESTORS)
        );

        $relationships = $this->addDescendantsRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_DESCENDANTS)
        );

        $relationships = $this->addImageRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_IMAGE)
        );

        $relationships = $this->addTaskRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_TASK)
        );

        $relationships = $this->addUnitRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_UNIT)
        );

        $relationships = $this->addFeedbackElementRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_FEEDBACKELEMENT)
        );

        return $relationships;
    }

    private function addAncestorsRelationship(array $relationships, $resource, $includeData)
    {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_ANCESTORS),
            ],
        ];

        if ($includeData) {
            $related = $resource->findAncestors();
            $relation[self::RELATIONSHIP_DATA] = $related;
        }

        $relationships[self::REL_ANCESTORS] = $relation;

        return $relationships;
    }

    private function addChildrenRelationship(array $relationships, $resource, bool $includeData): array
    {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_CHILDREN),
            ],
        ];

        if ($includeData) {
            $user = $this->currentUser;
            $relation[self::RELATIONSHIP_DATA] = $resource->children->filter(function ($child) use ($user) {
                return $child->canRead($user);
            });
        }

        $relationships[self::REL_CHILDREN] = $relation;

        return $relationships;
    }

    private function addContainersRelationship(array $relationships, $resource, bool $includeData): array
    {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_CONTAINERS),
            ],
        ];

        if ($includeData) {
            $relation[self::RELATIONSHIP_DATA] = $resource->containers;
        } else {
            $relation[self::RELATIONSHIP_DATA] = function () use ($resource) {
                $sql = 'SELECT id FROM cw_containers WHERE structural_element_id = ?';
                $containers = \DBManager::get()->fetchAll($sql, [$resource->id], function ($container) {
                    return new Identifier($container['id'], \JsonApi\Schemas\Courseware\Container::TYPE);
                });

                return $containers;
            };
        }
        $relationships[self::REL_CONTAINERS] = $relation;

        return $relationships;
    }

    private function addDescendantsRelationship(array $relationships, $resource, $includeData)
    {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_DESCENDANTS),
            ],
        ];

        if ($includeData) {
            $user = $this->currentUser;
            $related = $resource->findDescendants($user);
            $relation[self::RELATIONSHIP_DATA] = $related;
        }

        $relationships[self::REL_DESCENDANTS] = $relation;

        return $relationships;
    }

    private function addImageRelationship(array $relationships, $resource, $includeData)
    {
        $image = $resource->image;
        $relation = [
            self::RELATIONSHIP_DATA => $image ?: null,
        ];

        if ($image) {
            $relation[self::RELATIONSHIP_META] = [
                'download-url' => $resource->getImageUrl(),
            ];
        }

        $relationships[self::REL_IMAGE] = $relation;

        return $relationships;
    }

    private function addEditBlockerRelationship(array $relationships, $resource, bool $includeData): array
    {
        $relation = [
            self::RELATIONSHIP_LINKS_SELF => true,
        ];
        if ($resource['edit_blocker_id']) {
            $relation[self::RELATIONSHIP_LINKS] = [
                Link::RELATED => $this->createLinkToUser($resource['edit_blocker_id']),
            ];
            $relation[self::RELATIONSHIP_DATA] = $includeData
                ? $resource->edit_blocker
                : new Identifier($resource['edit_blocker_id'], \JsonApi\Schemas\User::TYPE);
        } else {
            $relation[self::RELATIONSHIP_DATA] = null;
        }
        $relationships[self::REL_EDITBLOCKER] = $relation;

        return $relationships;
    }

    private function addEditorRelationship(array $relationships, $resource, bool $includeData): array
    {
        $relation = [];
        if ($resource['editor_id']) {
            $relation[self::RELATIONSHIP_LINKS] = [
                Link::RELATED => $this->createLinkToUser($resource['editor_id']),
            ];
            $relation[self::RELATIONSHIP_DATA] = $includeData
                ? $resource->editor
                : new Identifier($resource['editor_id'], \JsonApi\Schemas\User::TYPE);
        } else {
            $relation[self::RELATIONSHIP_DATA] = null;
        }
        $relationships[self::REL_EDITOR] = $relation;

        return $relationships;
    }

    private function addOwnerRelationship(array $relationships, $resource, bool $includeData): array
    {
        $relation = [];
        if ($resource['owner_id']) {
            $relation[self::RELATIONSHIP_LINKS] = [
                Link::RELATED => $this->createLinkToUser($resource['owner_id']),
            ];
            $relation[self::RELATIONSHIP_DATA] = $includeData
                ? $resource->owner
                : new Identifier($resource['owner_id'], \JsonApi\Schemas\User::TYPE);
        } else {
            $relation[self::RELATIONSHIP_DATA] = null;
        }
        $relationships[self::REL_OWNER] = $relation;

        return $relationships;
    }

    private function addParentRelationship(array $relationships, $resource, bool $includeData): array
    {
        $relation = [];

        if ($resource['parent_id']) {
            $relation[self::RELATIONSHIP_LINKS] = [
                Link::RELATED => $this->createLinkToStructuralElement($resource['parent_id']),
            ];
            $relation[self::RELATIONSHIP_DATA] = $includeData
                ? $resource->parent
                : new Identifier($resource['parent_id'], self::TYPE);
        } else {
            $relation[self::RELATIONSHIP_DATA] = null;
        }
        $relationships[self::REL_PARENT] = $relation;

        return $relationships;
    }

    private function addRangeRelationship(array $relationships, $resource, $context): array
    {
        if ($resource['range_type'] === 'course') {
            $includeData = $this->shouldInclude($context, self::REL_COURSE);
            $relationships[self::REL_COURSE] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToCourse($resource['range_id']),
                ],
                self::RELATIONSHIP_DATA => $includeData
                    ? $resource->course
                    : new Identifier($resource['range_id'], \JsonApi\Schemas\Course::TYPE),
            ];
        } elseif ($resource['range_type'] === 'user') {
            $includeData = $this->shouldInclude($context, self::REL_USER);
            $relationships[self::REL_USER] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToUser($resource['range_id']),
                ],
                self::RELATIONSHIP_DATA => $includeData
                    ? $resource->user
                    : new Identifier($resource['range_id'], \JsonApi\Schemas\User::TYPE),
            ];
        }

        return $relationships;
    }

    private function addTaskRelationship(
        array $relationships,
        $resource,
        bool $shouldInclude
    ): array {
        $relationships[self::REL_TASK] = $resource->task
            ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->task),
                ],
                self::RELATIONSHIP_DATA => $resource->task,
            ]
            : [
                self::RELATIONSHIP_DATA => null,
            ];

        return $relationships;
    }

    private function addUnitRelationship(array $relationships, $resource, $includeData): array
    {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_UNIT),
            ],
        ];

        $related = $resource->findUnit();
        $relation[self::RELATIONSHIP_DATA] = $related;

        $relationships[self::REL_UNIT] = $relation;

        return $relationships;
    }

    private function addFeedbackElementRelationship(array $relationships, $resource, $includeData): array
    {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_FEEDBACKELEMENT),
            ],
        ];

        $feedback = $resource->getFeedbackElement();
        $relation[self::RELATIONSHIP_DATA] = $feedback;
        $relationships[self::REL_FEEDBACKELEMENT] = $relation;


        return $relationships;
    }

    private static $memo = [];

    private function createLinkToCourse($rangeId)
    {
        if (isset(self::$memo['course' . $rangeId])) {
            return self::$memo['course' . $rangeId];
        }

        $course = \Course::build(['id' => $rangeId], false);
        $link = $this->createLinkToResource($course);
        self::$memo['course' . $rangeId] = $link;

        return $link;
    }

    private function createLinkToStructuralElement($structuralElementId)
    {
        if (isset(self::$memo['structuralelement' . $structuralElementId])) {
            return self::$memo['structuralelement' . $structuralElementId];
        }

        $structuralElement = \Courseware\StructuralElement::build(['id' => $structuralElementId], false);
        $link = $this->createLinkToResource($structuralElement);
        self::$memo['structuralelement' . $structuralElementId] = $link;

        return $link;
    }

    private function createLinkToUser($rangeId)
    {
        if (isset(self::$memo['user' . $rangeId])) {
            return self::$memo['user' . $rangeId];
        }

        $course = \User::build(['id' => $rangeId], false);
        $link = $this->createLinkToResource($course);
        self::$memo['user' . $rangeId] = $link;

        return $link;
    }
}
