<?php

namespace JsonApi\Routes;

use JsonApi\Errors\AuthorizationFailedException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JsonApi\JsonApiController;
use JsonApi\Errors\RecordNotFoundException;

class StatusgroupShow extends JsonApiController
{
    public function __invoke(Request $request, Response $response, $args)
    {
        $group = \Statusgruppen::find($args['id']);
        if (!$group) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);
        $range = $group->range;

        if ($range && !$range->isAccessibleToUser($user->id)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse($group);
    }
}
