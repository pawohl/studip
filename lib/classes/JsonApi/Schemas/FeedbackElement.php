<?php

namespace JsonApi\Schemas;

use JsonApi\Errors\InternalServerError;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class FeedbackElement extends SchemaProvider
{
    public const TYPE = 'feedback-elements';
    public const REL_AUTHOR = 'author';
    public const REL_COURSE = 'course';
    public const REL_ENTRIES = 'entries';
    public const REL_RANGE = 'range';

    protected ?array $allowedIncludes = [
        self::REL_AUTHOR,
        self::REL_COURSE,
        self::REL_ENTRIES,
        self::REL_RANGE,
    ];



    public function getId($resource): ?string
    {
        return (string) $resource->id;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $attributes = [
            'question' => (string) $resource['question'],
            'description' => (string) $resource['description'],
            'mode' => (int) $resource['mode'],
            'results-visible' => (bool) $resource['results_visible'],
            'is-commentable' => (bool) $resource['commentable'],
            'anonymous-entries' => (bool) $resource['anonymous_entries'],
            'average-rating' => $resource->getAverageRating(),
            'has-entries' => $resource->hasEntries(),

            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate'])
        ];

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getResourceMeta($resource)
    {
        return $resource['mode'] === 0
            ? null
            : [
                'ratings' => [
                    'count' => \FeedbackEntry::countBySql('feedback_id = ?', [$resource->id]),
                    'mean' => (float) $resource->getMeanOfRating()
                ]
            ];
    }

    /**
     * In dieser Methode können Relationships zu anderen Objekten
     * spezifiziert werden.
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getAuthorRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_AUTHOR));
        $relationships = $this->getCourseRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_COURSE));
        $relationships = $this->getEntriesRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_ENTRIES));
        $relationships = $this->getRangeRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_RANGE));

        return $relationships;
    }

    private function getAuthorRelationship(array $relationships, \FeedbackElement $resource, bool $includeData): array
    {
        $userId = $resource['user_id'];
        $related = $includeData ? \User::find($userId) : \User::build(['id' => $userId], false);
        $relationships[self::REL_AUTHOR] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($related)
            ],
            self::RELATIONSHIP_DATA => $related
        ];

        return $relationships;
    }

    private function getCourseRelationship(array $relationships, \FeedbackElement $resource, bool $includeData): array
    {
        if ($courseId = $resource['course_id']) {
            $related = $includeData ? \Course::find($courseId) : \Course::build(['id' => $courseId], false);
            $relationships[self::REL_COURSE] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($related)
                ],
                self::RELATIONSHIP_DATA => $related
            ];
        }

        return $relationships;
    }

    private function getEntriesRelationship(array $relationships, \FeedbackElement $resource, bool $includeData): array
    {
        $relationships[self::REL_ENTRIES] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_ENTRIES)
            ],
            self::RELATIONSHIP_DATA => $resource->entries
        ];

        return $relationships;
    }

    private function getRangeRelationship(array $relationships, \FeedbackElement $resource, bool $includeData): array
    {
        $range = $resource->getRange();
        try {
            $link = $this->createLinkToResource($range);
            $relationships[self::REL_RANGE] = [
                self::RELATIONSHIP_LINKS => [Link::RELATED => $link],
                self::RELATIONSHIP_DATA => $range
            ];
        } catch (\InvalidArgumentException $e) {
            // don't show this relation
        } catch (InternalServerError $ise) {
            // don't show this relation
        }

        return $relationships;
    }
}
