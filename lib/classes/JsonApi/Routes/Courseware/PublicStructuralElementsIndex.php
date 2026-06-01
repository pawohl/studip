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
class PublicStructuralElementsIndex extends JsonApiController
{

        /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        
        $publicLink = PublicLink::find($args['link_id']);

        if (!$publicLink) {
            throw new AuthorizationFailedException();
        }

        $root = StructuralElement::find($publicLink->structural_element_id);
        if (!$root) {
            throw new RecordNotFoundException();
        }

        if (!$publicLink->canVisitElement($root)) {
            throw new AuthorizationFailedException();
        }

        $resources = array_merge([$root], $root->findDescendants());

        return $this->getContentResponse($resources);
    }
}