<?php

namespace JsonApi\Routes\Courseware;

use Courseware\StructuralElement;
use Courseware\StructuralElementComment;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays the user progress of a structural element.
 */
class StructuralElementCommentsOfStructuralElementsIndex extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!($structural_element = StructuralElement::find($args['id']))) {
            throw new RecordNotFoundException();
        }
        if (!Authority::canIndexStructuralElementComments($this->getUser($request), $structural_element)) {
            throw new AuthorizationFailedException();
        }
        $resources = StructuralElementComment::findBySql('structural_element_id = ?', [$structural_element->id]);

        return $this->getContentResponse($resources);
    }
}
