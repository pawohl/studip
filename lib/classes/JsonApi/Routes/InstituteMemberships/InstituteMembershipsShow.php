<?php

namespace JsonApi\Routes\InstituteMemberships;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class InstituteMembershipsShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!$membership = \InstituteMember::find($args['id'])) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);
        if ($user->id !== $membership->user_id && !get_visibility_by_id($membership->user_id)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse($membership);
    }
}
