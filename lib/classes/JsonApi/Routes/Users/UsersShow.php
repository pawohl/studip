<?php

namespace JsonApi\Routes\Users;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

class UsersShow extends JsonApiController
{
    /**
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response, array $args)
    {
        $routeName = RouteContext::fromRequest($request)
            ->getRoute()
            ->getName();
        if ($routeName === 'get-myself') {
            $observedUser = $this->getUser($request);
        } else {
            $observedUser = \User::find($args['id']);
        }
        if (!$observedUser) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowUser($this->getUser($request), $observedUser)) {
            // absichtlich keine AuthorizationFailedException
            // damit unsichtbare Nutzer nicht ermittelt werden können
            throw new RecordNotFoundException();
        }

        return $this->getContentResponse($observedUser);
    }
}
