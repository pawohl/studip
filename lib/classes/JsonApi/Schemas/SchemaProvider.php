<?php

namespace JsonApi\Schemas;

use JsonApi\Errors\InternalServerError;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Http\Query\BaseQueryParserInterface;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Contracts\Schema\LinkInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaContainerInterface;
use Neomerx\JsonApi\Exceptions\JsonApiException;
use Neomerx\JsonApi\Schema\BaseSchema;
use Neomerx\JsonApi\Schema\ErrorCollection;

abstract class SchemaProvider extends BaseSchema
{
    /** @var SchemaContainerInterface */
    protected $schemaContainer;

    /** @var ?\User */
    protected $currentUser;

    /**
     * A list of allowed includes for this schema in input parameters.
     * Empty array means clients are not allowed to specify includes and 'null' means all paths are allowed.
     */
    protected ?array $allowedIncludes = null;

    public function __construct(FactoryInterface $factory, SchemaContainerInterface $schemaContainer, ?\User $user)
    {
        $this->schemaContainer = $schemaContainer;
        $this->currentUser = $user;

        parent::__construct($factory);
    }

    const TYPE = '';

    public function getType(): string
    {
        return static::TYPE;
    }

    /**
     * @inheritdoc
     */
    public function isAddSelfLinkInRelationshipByDefault(string $relationshipName): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isAddRelatedLinkInRelationshipByDefault(string $relationshipName): bool
    {
        return false;
    }

    /**
     * @param mixed $resource
     */
    public function createLinkToResource($resource): LinkInterface
    {
        if (!$this->schemaContainer->hasSchema($resource)) {
            throw new InternalServerError('Cannot create links to objects without schema.');
        }

        return $this->schemaContainer->getSchema($resource)->getSelfLink($resource);
    }

    public function checkAllowedIncludes(ContextInterface $context): void
    {
        if ($this->allowedIncludes === null) {
            return;
        }

        $errors = new ErrorCollection();
        $level = $context->getPosition()->getLevel();
        $path = $level ? $context->getPosition()->getPath() . '.' : '';

        foreach ($context->getIncludePaths() as $include) {
            if (str_starts_with($include, $path)) {
                $components = explode('.', $include);

                if (!in_array($components[$level], $this->allowedIncludes)) {
                    $errors->addQueryParameterError(
                        BaseQueryParserInterface::PARAM_INCLUDE,
                        sprintf('Include path %s is not allowed.', $components[$level])
                    );
                }
            }
        }

        if ($errors->count()) {
            throw new JsonApiException($errors, JsonApiException::HTTP_CODE_BAD_REQUEST);
        }
    }

    /**
     * @param ContextInterface $context
     * @param string $key
     *
     * @return bool true, if the given relationship should be included in the response
     */
    public function shouldInclude(ContextInterface $context, string $key): bool
    {
        $path = $context->getPosition()->getLevel() ? $context->getPosition()->getPath() . '.' : '';

        return in_array($path . $key, $this->getAllowedIncludePaths($context));
    }

    /**
     * @param ContextInterface $context
     * @return array
     */
    public function getAllowedIncludePaths(ContextInterface $context): array
    {
        $allowedIncludePaths = [];

        foreach ($context->getIncludePaths() as $path) {
            $carry = '';
            foreach (explode('.', $path) as $p) {
                $allowedIncludePaths[] = $carry . $p;
                $carry .= "{$p}.";
            }
        }

        return $allowedIncludePaths;
    }
}
