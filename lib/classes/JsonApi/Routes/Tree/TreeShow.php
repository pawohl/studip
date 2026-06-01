<?php

namespace JsonApi\Routes\Tree;

use JsonApi\Errors\BadRequestException;
use Neomerx\JsonApi\Contracts\Http\ResponsesInterface;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class TreeShow extends JsonApiController
{
    protected $allowUnrecognizedParams = true;
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        list($classname, $id) = explode('_', $args['id']);

        $node = $classname::getNode($id);
        if (!$node) {
            throw new RecordNotFoundException();
        }

        return $this->getContentResponse($node);
    }

}
