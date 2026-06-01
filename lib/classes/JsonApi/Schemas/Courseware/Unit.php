<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Unit extends SchemaProvider
{
    const TYPE = 'courseware-units';

    const REL_CREATOR= 'creator';
    const REL_RANGE = 'range';
    const REL_STRUCTURAL_ELEMENT = 'structural-element';
    const REL_FEEDBACK_ELEMENT = 'feedback-element';

    protected array $allowedIncludes = [
        self::REL_CREATOR,
        self::REL_RANGE,
        self::REL_STRUCTURAL_ELEMENT,
        self::REL_FEEDBACK_ELEMENT,
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
            'content-type' => (string) $resource['content_type'],
            'position' => (int) $resource['position'],
            'public' => (int) $resource['public'],
            'permission-scope' => (string) $resource['permission_scope'],
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
            'config' => json_decode($resource['config']),
            'can-read' => $resource->canRead($user),
            'can-edit' => $resource->canEdit($user),
            'can-edit-content' => $resource->canEditContent($user),
            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships[self::REL_CREATOR] = $resource->creator
            ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->creator),
                ],
                self::RELATIONSHIP_DATA => $resource->creator,
            ]
            : [self::RELATIONSHIP_DATA => null];

        $relationships[self::REL_STRUCTURAL_ELEMENT] = $resource->structural_element
            ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->structural_element),
                ],
                self::RELATIONSHIP_DATA => $resource->structural_element,
            ]
            : [self::RELATIONSHIP_DATA => null];

        $rangeType = $resource->range_type;
        $range = $resource->$rangeType;

        $relationships[self::REL_RANGE] = $range
            ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($range),
                ],
                self::RELATIONSHIP_DATA => $range,
            ]
            : [self::RELATIONSHIP_DATA => null];

        $feedback = $resource->getFeedbackElement();
        $relationships[self::REL_FEEDBACK_ELEMENT] = $feedback
            ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($feedback),
                ],
                self::RELATIONSHIP_DATA => $feedback,
            ]
            : [self::RELATIONSHIP_DATA => null];

        return $relationships;
    }
}
