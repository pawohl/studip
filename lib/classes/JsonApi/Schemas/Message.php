<?php

namespace JsonApi\Schemas;

use MessageUser;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Message extends SchemaProvider
{
    const TYPE = 'messages';
    const REL_SENDER = 'sender';
    const REL_RECIPIENTS = 'recipients';

    protected ?array $allowedIncludes = [
        self::REL_SENDER,
        self::REL_RECIPIENTS,
    ];

    /**
     * @param \Message $resource
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * @param \Message $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $user = $this->currentUser;

        return [
            'subject'  => $resource->subject,
            'message'  => $resource->message,
            'mkdate'   => date('c', $resource->mkdate),
            'is-read'  => $resource->isRead($user->id),
            'priority' => $resource->priority,
            'tags'     => $resource->getTags(),
        ];
    }

    /**
     * @param \Message $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getSenderRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_SENDER));
        $relationships = $this->getRecipientsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_RECIPIENTS));

        return $relationships;
    }

    private function getSenderRelationship(array $relationships, \Message $message, $includeData): array
    {
        $data = $message->getSender();

        if ($data) {
            $relationships[self::REL_SENDER] = [
                // self::RELATIONSHIP_LINKS_SELF => true,
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($data),
                ],
                self::RELATIONSHIP_DATA => $data,
            ];
        }

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getRecipientsRelationship(array $relationships, \Message $message, $includeData): array
    {
        $recipients = $message->receivers->pluck('user');
        $recipients = array_filter($recipients);

        $relationships[self::REL_RECIPIENTS] = [
            // self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_DATA => $recipients,
        ];

        return $relationships;
    }
}
