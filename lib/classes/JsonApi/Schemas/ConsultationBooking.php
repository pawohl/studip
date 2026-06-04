<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class ConsultationBooking extends SchemaProvider
{
    const TYPE = 'consultation-bookings';

    const REL_SLOT = 'slot';
    const REL_USER = 'user';

    protected ?array $allowedIncludes = [
        self::REL_SLOT,
        self::REL_USER,
    ];

    /**
     * @param \ConsultationBooking $resource
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * @param \ConsultationBooking $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $attributes = [
            'reason' => $resource->reason,

            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate),
        ];

        return $attributes;
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getSlotRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_SLOT));
        $relationships = $this->getUserRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_USER));

        return $relationships;
    }

    // #### PRIVATE HELPERS ####

    private function getSlotRelationship(array $relationships, \ConsultationBooking $resource, $includeData)
    {
        $slot = $resource->slot;

        $relationships[self::REL_SLOT] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_SLOT),
            ],
            self::RELATIONSHIP_DATA => $includeData ? $slot : \ConsultationSlot::build(['id' => $slot->id], false),
        ];

        return $relationships;
    }

    private function getUserRelationship($relationships, \ConsultationBooking $resource, $includeData)
    {
        $user = $resource->user;

        $relationships[self::REL_USER] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_USER),
            ],
            self::RELATIONSHIP_DATA => $includeData ? $user : \User::build(['id' => $user->id], false),
        ];

        return $relationships;
    }
}
