<?php

namespace JsonApi\Routes\Courseware;

use Courseware\Unit;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays the course's courseware units.
 */
class UsersUnitsIndex extends JsonApiController
{
    use CoursewareInstancesHelper;

    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $user = \User::find($args['id']);
        if (!$user) {
            throw new RecordNotFoundException();
        }
        $request_user = $this->getUser($request);
        if (!Authority::canIndexUnitsOfAUser($request_user, $user)) {
            throw new AuthorizationFailedException();
        }

        $resources = Unit::findUsersUnits($user);
        $total = count($resources);
        [$offset, $limit] = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(array_slice($resources, $offset, $limit), $total);
    }
}
