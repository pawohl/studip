<?php

namespace JsonApi\Routes\InstituteMemberships;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\Users\Authority;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ByUserIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!$user = \User::find($args['id'])) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowUser($this->getUser($request), $user)) {
            throw new AuthorizationFailedException();
        }

        $institutes = $user->institute_memberships;
        if (!$GLOBALS['perm']->have_profile_perm('user', $user->id)) {
            $institutes = $institutes->filter(fn($membership) => $membership->inst_perms !== 'user');
        }
        $total = count($institutes);
        list($offset, $limit) = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse($institutes->limit($offset, $limit), $total);
    }
}
