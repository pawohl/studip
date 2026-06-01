<?php

namespace JsonApi\Routes\Courseware;

use Courseware\Bookmark;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays the user's bookmarked structural elements.
 */
class UsersBookmarkedStructuralElementsIndex extends JsonApiController
{
    use CoursewareInstancesHelper;

    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!($user = \User::find($args['id']))) {
            throw new RecordNotFoundException();
        }
        $actor = $this->getUser($request);
        if (!Authority::canIndexBookmarksOfAUser($actor, $user)) {
            throw new AuthorizationFailedException();
        }

        $resources = array_column(Bookmark::findUsersBookmarks($user), 'element');
        $total = count($resources);
        [$offset, $limit] = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(array_slice($resources, $offset, $limit), $total);
    }
}
