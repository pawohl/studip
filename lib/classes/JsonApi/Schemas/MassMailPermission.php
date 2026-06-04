<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class MassMailPermission extends SchemaProvider
{
    const TYPE = 'mass-mail-permissions';
    const REL_INSTITUTE = 'institute';
    const REL_ALLOWED_DEGREES = 'allowed-degrees';
    const REL_ALLOWED_SUBJECTS = 'allowed-subjects';
    const REL_ALLOWED_INSTITUTES = 'allowed-institutes';

    protected ?array $allowedIncludes = [
        self::REL_INSTITUTE,
        self::REL_ALLOWED_DEGREES,
        self::REL_ALLOWED_SUBJECTS,
        self::REL_ALLOWED_INSTITUTES,
    ];

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $user = $this->currentUser;

        return [
            'min-perm' => $resource->min_perm,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate)
        ];
    }

    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    /**
     * @param \MassMail\MassMailPermission $resource
     */
    public function getResourceMeta($resource)
    {
        return [
            'allowed-degrees-count' => count($resource->allowed_degrees),
            'allowed-subjects-count' => count($resource->allowed_subjects),
            'allowed-institutes-count' => count($resource->allowed_institutes)
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getInstituteRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_INSTITUTE));
        $relationships = $this->getAllowedDegreesRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_ALLOWED_DEGREES));
        $relationships = $this->getAllowedSubjectsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_ALLOWED_SUBJECTS));
        $relationships = $this->getAllowedInstitutesRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_ALLOWED_INSTITUTES));

        return $relationships;
    }

    private function getInstituteRelationship(array $relationships, \MassMail\MassMailPermission $permission, $includeData)
    {
        $relationships[self::REL_INSTITUTE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($permission->institute),
            ]
        ];

        if ($includeData) {
            $relationships[self::REL_INSTITUTE][self::RELATIONSHIP_DATA] = $permission->institute;
        }

        return $relationships;
    }

    private function getAllowedDegreesRelationship(array $relationships, \MassMail\MassMailPermission $permission, $includeData)
    {

        $relationships[self::REL_ALLOWED_DEGREES] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($permission, self::REL_ALLOWED_DEGREES),
            ]
        ];

        if ($includeData) {
            $relationships[self::REL_ALLOWED_DEGREES][self::RELATIONSHIP_DATA] = $permission->allowed_degrees;
        }

        return $relationships;
    }

    private function getAllowedSubjectsRelationship(array $relationships, \MassMail\MassMailPermission $permission, $includeData)
    {
        $relationships[self::REL_ALLOWED_SUBJECTS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($permission, self::REL_ALLOWED_SUBJECTS),
            ]
        ];

        if ($includeData) {
            $relationships[self::REL_ALLOWED_SUBJECTS][self::RELATIONSHIP_DATA] = $permission->allowed_subjects;
        }

        return $relationships;
    }

    private function getAllowedInstitutesRelationship(array $relationships, \MassMail\MassMailPermission $permission, $includeData)
    {
        $relationships[self::REL_ALLOWED_INSTITUTES] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($permission, self::REL_ALLOWED_INSTITUTES),
            ]
        ];

        if ($includeData) {
            $relationships[self::REL_ALLOWED_INSTITUTES][self::RELATIONSHIP_DATA] = $permission->allowed_institutes;
        }

        return $relationships;
    }
}
