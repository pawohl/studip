<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Message extends SchemaProvider
{
    const TYPE = 'messages';
    const REL_SENDER = 'sender';
    const REL_RECIPIENTS = 'recipients';

    protected array $allowedIncludes = [
        self::REL_SENDER,
        self::REL_RECIPIENTS,
    ];

    public function getId($message): ?string
    {
        return $message->id;
    }

    public function getAttributes($message, ContextInterface $context): iterable
    {
        $user = $this->currentUser;

        return [
            'subject' => $message->subject,
            'message' => $message->message,
            'mkdate' => date('c', $message->mkdate),
            'is-read' => (bool) $message->isRead($user->id),
            'priority' => $message->priority,
            'tags' => $message->getTags(),
        ];
    }

    public function getRelationships($message, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getSenderRelationship($relationships, $message, $this->shouldInclude($context, self::REL_SENDER));
        $relationships = $this->getRecipientsRelationship($relationships, $message, $this->shouldInclude($context, self::REL_RECIPIENTS));

        return $relationships;
    }

    private function getSenderRelationship(array $relationships, \Message $message, $includeData)
    {
        $userId = $message->getSender()->id;

        $data = null;
        if ($userId) {
            $data = $includeData ? \User::find($userId) : \User::build(['id' => $userId], false);

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
    private function getRecipientsRelationship(array $relationships, \Message $message, $includeData)
    {
        $relationships[self::REL_RECIPIENTS] = [
            // self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_DATA => $message->receivers->map(function ($i) { return $i->user; }),
        ];

        return $relationships;
    }
}
