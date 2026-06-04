<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Routes\Courseware\Authority;
use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class PeerReview extends SchemaProvider
{
    public const TYPE = 'courseware-peer-reviews';

    public const REL_PROCESS = 'process';
    public const REL_REVIEWER = 'reviewer';
    public const REL_SUBMITTER = 'submitter';
    public const REL_TASK = 'task';

    protected ?array $allowedIncludes = [
        self::REL_PROCESS,
        self::REL_REVIEWER,
        self::REL_SUBMITTER,
        self::REL_TASK,
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
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $user = $this->currentUser;
        $assessment = null;
        if ($resource->assessment && Authority::canShowPeerReviewAssessment($user, $resource)) {
            $assessment = $resource->assessment->getIterator();
        }
        return [
            'assessment' => $assessment,
            'is-reviewer' => $resource->isReviewer($user),
            'is-submitter' => $resource->isSubmitter($user),
            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships[self::REL_PROCESS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->process),
            ],
            self::RELATIONSHIP_DATA => $resource->process,
        ];

        $user = $this->currentUser;

        if (Authority::canShowPeerReviewReviewer($user, $resource)) {
            $reviewer = $resource->getReviewer();
            $relationships[self::REL_REVIEWER] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($reviewer),
                ],
                self::RELATIONSHIP_DATA => $reviewer,
            ];
        } else {
            $relationships[self::REL_REVIEWER] = [
                self::RELATIONSHIP_DATA => null,
            ];
        }

        if (Authority::canShowPeerReviewSubmitter($user, $resource)) {
            $submitter = $resource->getSubmitter();
            $relationships[self::REL_SUBMITTER] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($submitter),
                ],
                self::RELATIONSHIP_DATA => $submitter,
            ];
        } else {
            $relationships[self::REL_SUBMITTER] = [
                self::RELATIONSHIP_DATA => null,
            ];
        }

        $relationships[self::REL_TASK] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->task),
            ],
            self::RELATIONSHIP_DATA => $resource->task,
        ];

        return $relationships;
    }
}
