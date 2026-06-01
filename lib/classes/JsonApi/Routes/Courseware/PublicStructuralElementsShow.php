<?php

namespace JsonApi\Routes\Courseware;

use Courseware\StructuralElement;
use Courseware\PublicLink;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Neomerx\JsonApi\Contracts\Http\ResponsesInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays one StructuralElement.
 */
class PublicStructuralElementsShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $resource = StructuralElement::find($args['id']);
        $publicLink = PublicLink::find($args['link_id']);

        if (!$publicLink) {
            throw new AuthorizationFailedException();
        }

        /** @var ?StructuralElement $resource*/
        if (!$resource) {
            throw new RecordNotFoundException();
        }

        if (!$publicLink->canVisitElement($resource)) {
            throw new AuthorizationFailedException();
        }

        $meta = [];

        return $this->getContentResponse($resource, ResponsesInterface::HTTP_OK, [], $meta);
    }
}
