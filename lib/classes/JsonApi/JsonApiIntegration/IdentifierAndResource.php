<?php
namespace JsonApi\JsonApiIntegration;

use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Parser\EditableContextInterface;
use Neomerx\JsonApi\Contracts\Schema\PositionInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaContainerInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaInterface;

class IdentifierAndResource extends \Neomerx\JsonApi\Parser\IdentifierAndResource
{
    private SchemaInterface $schema;

    public function __construct(
        EditableContextInterface $context,
        PositionInterface $position,
        FactoryInterface $factory,
        SchemaContainerInterface $container,
        $data
    ) {
        parent::__construct($context, $position, $factory, $container, $data);

        $this->schema = $container->getSchema($data);
    }

    public function getRelationships(): iterable
    {
        $this->getContext()->setPosition($this->getPosition());

        $this->schema->checkAllowedIncludes($this->getContext());

        return parent::getRelationships();
    }
}
