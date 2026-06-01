<?php

namespace JsonApi\Routes\Courseware;

use Courseware\StructuralElement;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Neomerx\JsonApi\Contracts\Http\ResponsesInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays one StructuralElement.
 */
class StructuralElementsShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        /** @var ?StructuralElement $resource*/
        $resource = StructuralElement::find($args['id']);
        if (!$resource) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);
        if (!Authority::canShowStructuralElement($user, $resource)) {
            throw new AuthorizationFailedException();
        }

        $last = \UserConfig::get($user->id)->getValue('COURSEWARE_LAST_ELEMENT');

        if ($resource->user) {
            $last['global'] = $args['id'];
        } else if ($resource->course) {
            $last[$resource->course->id] = $args['id'];
        } else {
            throw new RecordNotFoundException();
        }

        \UserConfig::get($user->id)->store('COURSEWARE_LAST_ELEMENT', $last);

        $meta = [ 'can-visit' => $resource->canVisit($user) ];

        return $this->getContentResponse($resource, ResponsesInterface::HTTP_OK, [], $meta);
    }
}
