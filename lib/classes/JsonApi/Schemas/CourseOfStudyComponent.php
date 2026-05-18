<?php
namespace JsonApi\Schemas;

use JsonApi\Routes\Mvv\Authority;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class CourseOfStudyComponent extends SchemaProvider
{
    const REL_SUBJECT = 'subject';
    const REL_VERSIONS = 'versions';
    const TYPE = 'courses-of-study-components';

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'display-name' => (string) $resource->getDisplayName(),
            'title-supplement' => (string) $resource->zusatz,
            'cp' => (string) $resource->kp,
            'semesters' => (string) $resource->semester,
            'classname' => get_class($resource)
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        if ($resource->fach) {
            $relationships[self::REL_SUBJECT] = $this->getSubject($resource, $this->shouldInclude($context, self::REL_SUBJECT));
        }

        $relationships = $this->addVersionsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_VERSIONS));

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getSubject(\StudiengangTeil $component, $shouldInclude)
    {
        return $component->fach
            ?  [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($component->fach),
                ],
                self::RELATIONSHIP_DATA => $component->fach,
            ]
            : [
                self::RELATIONSHIP_DATA => null,
            ];
    }

    private function addVersionsRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_VERSIONS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_VERSIONS),
            ],
        ];

        if ($includeData) {
            $relationships[self::REL_VERSIONS][self::RELATIONSHIP_DATA] = $resource->versionen->filter(
                fn($version) => Authority::canShowComponentVersion($this->currentUser, $version)
            );
        }

        return $relationships;
    }
}
