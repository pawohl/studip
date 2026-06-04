<?php

namespace JsonApi\Routes\Avatar;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AvatarOfRangeShow extends JsonApiController
{
    use AvatarHelpers;
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $range_id = $args['id'];
        $range_type = $args['type'];

        $user = $this->getUser($request);

        $range = self::getRange($range_id, $range_type);
        if (!$range) {
            throw new RecordNotFoundException('Unknown range given');
        }

        $class = self::getAvatarClassForRange($range);

        if (!Authority::canShowAvatarOfRange($user, $range)) {
            throw new AuthorizationFailedException();
        }

        $resource = $class::getAvatar($range_id);

        return $this->getContentResponse($resource);
    }
}
