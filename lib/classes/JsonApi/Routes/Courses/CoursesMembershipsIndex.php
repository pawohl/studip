<?php

namespace JsonApi\Routes\Courses;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Returns all comments of the blubber starting with the newest.
 * Returns an empty array if blubber_id is from a comment.
 */
class CoursesMembershipsIndex extends JsonApiController
{
    use CourseMembershipsTrait;

    protected $allowedFilteringParameters = ['permission'];

    protected $allowedPagingParameters = ['offset', 'limit'];

    public function __invoke(Request $request, Response $response, $args)
    {
        if (!($course = \Course::find($args['id']))) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canIndexMemberships($user = $this->getUser($request), $course)) {
            throw new AuthorizationFailedException();
        }

        $this->validateFilters();

        $memberships = $this->getCourseMemberships($course, $user, $this->getFilters());

        [$offset, $limit] = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse($memberships->limit($offset, $limit), count($memberships));
    }

    private function validateFilters()
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?? [];

        if (array_key_exists('permission', $filtering)) {
            if (!in_array($filtering['permission'], ['user', 'autor', 'tutor', 'dozent'])) {
                throw new BadRequestException('Filter `permission` must be one of `user`, `autor`, `tutor`, `dozent`.');
            }
        }
    }

    private function getFilters()
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?? [];

        $filters['permission'] = $filtering['permission'] ?? null;

        return $filters;
    }
}
