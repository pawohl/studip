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
 * Displays all descendants of a structural element.
 */
class DescendantsOfPublicStructuralElementsIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        /** @var ?StructuralElement $resource */
        $resource = StructuralElement::find($args['id']);
        $publicLink = PublicLink::find($args['link_id']);

        if (!$publicLink) {
            throw new RecordNotFoundException();
        }
        if (!$resource) {
            throw new RecordNotFoundException();
        }

        if (!$publicLink->canVisitElement($resource)) {
            throw new AuthorizationFailedException();
        }

        $descendants = $resource->findDescendants();

        [$offset, $limit] = $this->getOffsetAndLimit();
        $page = array_slice($descendants, $offset, $limit);
        $total = count($descendants);

        return $this->getPaginatedContentResponse(
            $page,
            $total
        );
    }
}
