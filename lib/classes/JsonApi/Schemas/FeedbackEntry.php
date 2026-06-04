<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class FeedbackEntry extends SchemaProvider
{
    const TYPE = 'feedback-entries';
    const REL_AUTHOR = 'author';
    const REL_FEEDBACK = 'feedback-element';

    protected ?array $allowedIncludes = [
        self::REL_AUTHOR,
        self::REL_FEEDBACK,
    ];

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $attributes = [
            'comment' => (string) $resource['comment'],
            'rating' => 0 === $resource->feedback->mode ? null : $resource['rating'],
            'anonymous' => (bool) $resource['anonymous'],
            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];

        return $attributes;
    }

    /**
     * In dieser Methode können Relationships zu anderen Objekten
     * spezifiziert werden.
     *
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getAuthorRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_AUTHOR)
        );
        $relationships = $this->getFeedbackElementRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_FEEDBACK)
        );

        return $relationships;
    }

    private function getAuthorRelationship(array $relationships, \FeedbackEntry $resource, $includeData): array
    {
        $userId = $resource['user_id'];
        $related = $includeData ? \User::find($userId) : \User::build(['id' => $userId], false);
        $relationships[self::REL_AUTHOR] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($related),
            ],
            self::RELATIONSHIP_DATA => $related,
        ];

        return $relationships;
    }

    private function getFeedbackElementRelationship(
        array $relationships,
        \FeedbackEntry $resource,
        $includeData
    ): array {
        $related = $includeData
            ? $resource->feedback
            : \FeedbackElement::build(['id' => $resource->feedback_id], false);

        $relationships[self::REL_FEEDBACK] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($related),
            ],
            self::RELATIONSHIP_DATA => $related,
        ];

        return $relationships;
    }
}
