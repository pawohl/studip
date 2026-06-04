<?php

namespace JsonApi\Schemas\Courseware;

use Courseware\Task as TaskModel;
use JsonApi\Routes\Courseware\Authority as CoursewareAuthority;
use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class Task extends SchemaProvider
{
    const TYPE = 'courseware-tasks';

    const REL_FEEDBACK = 'task-feedback';
    const REL_PEER_REVIEWS = 'peer-reviews';
    const REL_SOLVER = 'solver';
    const REL_STRUCTURAL_ELEMENT = 'structural-element';
    const REL_TASK_GROUP = 'task-group';

    protected ?array $allowedIncludes = [
        self::REL_FEEDBACK,
        self::REL_PEER_REVIEWS,
        self::REL_SOLVER,
        self::REL_STRUCTURAL_ELEMENT,
        self::REL_TASK_GROUP,
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
            'progress' => (float) $resource->getTaskProgress(),
            'submission-date' => date('c', $resource['submission_date']),
            'submitted' => (bool) $resource['submitted'],
            'renewal' => empty($resource['renewal']) ? null : (string) $resource['renewal'],
            'renewal-date' => date('c', $resource['renewal_date']),
            'visible' => (bool) $resource['visible'],
            'can-peer-review' => $resource->userIsAPeerReviewer($user),
            'can-solve' => $resource->userIsASolver($user),
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

        $feedback = $resource->getFeedback();
        $relationships[self::REL_FEEDBACK] = $feedback
            ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($feedback),
                ],
                self::RELATIONSHIP_DATA => $feedback,
            ]
            : [self::RELATIONSHIP_DATA => null];

        $relationships = $this->addPeerReviews(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_PEER_REVIEWS)
        );

        $user = $this->currentUser;

        if (CoursewareAuthority::canShowTaskSolver($user, $resource)) {
            $relationships[self::REL_SOLVER] = $resource['solver_id']
                ? [
                    self::RELATIONSHIP_LINKS => [
                        Link::RELATED => $this->createLinkToResource($resource->solver),
                    ],
                    self::RELATIONSHIP_DATA => $resource->solver,
                ]
                : [self::RELATIONSHIP_DATA => null];
        } else {
            $relationships[self::REL_SOLVER] = [
                self::RELATIONSHIP_DATA => null,
            ];
        }

        $relationships[self::REL_STRUCTURAL_ELEMENT] = $resource['structural_element_id']
            ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource['structural_element']),
                ],
                self::RELATIONSHIP_DATA => $resource['structural_element'],
            ]
            : [self::RELATIONSHIP_DATA => null];

        $relationships[self::REL_TASK_GROUP] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource['task_group']),
            ],
            self::RELATIONSHIP_DATA => $resource['task_group'],
        ];

        return $relationships;
    }

    private function addPeerReviews(array $relationships, TaskModel $resource, bool $includeData): array
    {
        $relationships[self::REL_PEER_REVIEWS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_PEER_REVIEWS),
            ],
        ];

        if ($includeData) {
            $relationships[self::REL_PEER_REVIEWS][self::RELATIONSHIP_DATA] = $resource->isPeerReviewed()
                ? $resource->peer_reviews->filter(
                    fn($review) => CoursewareAuthority::canShowPeerReview($this->currentUser, $review)
                )
                : [];
        }

        return $relationships;
    }
}
