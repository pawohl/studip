<?php

namespace JsonApi\Schemas;

use JsonApi\Errors\InternalServerError;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class BlubberStatusgruppeThread extends BlubberThread
{
    const REL_STATUSGRUPPE = 'group';

    protected array $allowedIncludes = [
        self::REL_STATUSGRUPPE,
    ];

    /**
     * In dieser Methode können Relationships zu anderen Objekten
     * spezifiziert werden.
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = parent::getRelationships($resource, $context);

        $relationships[self::REL_STATUSGRUPPE] = [
            self::RELATIONSHIP_DATA => \Statusgruppen::build(
                [
                    'statusgruppe_id' => $resource['metadata']['statusgruppe_id']
                ],
                false
            )
        ];

        return $relationships;
    }
}
