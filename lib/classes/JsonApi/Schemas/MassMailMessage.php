<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class MassMailMessage extends SchemaProvider
{
    const TYPE = 'mass-mail-messages';
    const REL_FILTERS = 'filters';
    const REL_SENDER = 'sender';
    const REL_AUTHOR = 'author';
    const REL_RECIPIENTS = 'recipients';
    const REL_FOLDER = 'folder';
    const REL_TOKENS = 'tokens';

    protected ?array $allowedIncludes = [
        self::REL_FILTERS,
        self::REL_SENDER,
        self::REL_AUTHOR,
        self::REL_RECIPIENTS,
        self::REL_FOLDER,
        self::REL_TOKENS,
    ];

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'send-date' => date('d.m.Y H:i', $resource->send_at_date),
            'target' => \MassMail\MassMailMessage::getTargets()[$resource->target],
            'config' => $resource->config,
            'exclude-users' => $resource->exclude_users,
            'cc' => $resource->cc,
            'subject' => (string) $resource->subject,
            'message' => (string) $resource->message,
            'is-template' => (bool) $resource->is_template,
            'locked' => (bool) $resource->locked,
            'sent' => (bool) $resource->sent,
            'protected' => (bool) $resource->protected,
            'mkdate' => date('d.m.Y H:i', $resource->mkdate),
            'chdate' => date('d.m.Y H:i', $resource->chdate)
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getAuthorRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_AUTHOR));
        $relationships = $this->getSenderRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_SENDER));

        return $relationships;
    }

    private function getAuthorRelationship(array $relationships, \MassMail\MassMailMessage $message, $includeData)
    {
        $relationships[self::REL_AUTHOR] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($message->author),
            ]
        ];

        if ($includeData) {
            $relationships[self::REL_AUTHOR][self::RELATIONSHIP_DATA] = $message->author;
        }

        return $relationships;
    }

    private function getSenderRelationship(array $relationships, \MassMail\MassMailMessage $message, $includeData)
    {
        if ($message->sender_id && $message->sender) {
            $relationships[self::REL_SENDER] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($message->sender),
                ]
            ];

            if ($includeData) {
                $relationships[self::REL_SENDER][self::RELATIONSHIP_DATA] = $message->sender;
            }
        }

        return $relationships;
    }

}
