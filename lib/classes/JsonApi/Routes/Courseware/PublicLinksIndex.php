<?php

namespace JsonApi\Routes\Courseware;

use Courseware\PublicLink;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays all PublicLinks
 */
class PublicLinksIndex extends JsonApiController
{

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $user = $this->getUser($request);
        if (!Authority::canIndexPublicLinks($user)) {
            throw new AuthorizationFailedException();
        }

        $resources = PublicLink::findBySQL('user_id = ? ORDER BY mkdate', [$user->id]);

        return $this->getContentResponse($resources);
    }
}