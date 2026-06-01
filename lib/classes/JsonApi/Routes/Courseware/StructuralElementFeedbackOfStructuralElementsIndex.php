<?php

namespace JsonApi\Routes\Courseware;

use Courseware\StructuralElement;
use Courseware\StructuralElementFeedback;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays the feedback of a StructuralElement.
 */
class StructuralElementFeedbackOfStructuralElementsIndex extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        /** @var ?Block $block */
        $structuralElement = StructuralElement::find($args['id']);
        if (!$structuralElement) {
            throw new RecordNotFoundException();
        }
        if (!Authority::canIndexStructuralElementFeedback($this->getUser($request), $structuralElement)) {
            throw new AuthorizationFailedException();
        }
        /** @var StructuralElementFeedback[] $resources */
        $resources = $structuralElement->feedback;

        return $this->getContentResponse($resources);
    }
}
