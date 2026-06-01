<?php

namespace JsonApi\Routes\Institutes;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

/**
 * Returns all institute-memberships of the institute.
 */
class InstituteMembershipsIndex extends JsonApiController
{
    protected $allowedFilteringParameters = ['permission'];

    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $institute = \Institute::find($args['id']);
        if (!$institute) {
            throw new RecordNotFoundException();
        }

        $this->validateFilters();

        $user = $this->getUser($request);
        $memberships = $this->getMemberships($institute, $user, $this->getFilters());

        list($offset, $limit) = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse($memberships->limit($offset, $limit), count($memberships));
    }

    private function getMemberships(\Institute $institute, \User $user, array $filters)
    {
        $memberships = $institute->members;

        $visibleMemberships = Authority::canEditInstitute($user, $institute)
            ? $memberships
            : $memberships->filter(function ($membership) use ($user) {
                return $membership->user_id === $user->id || get_visibility_by_id($membership->user_id);
            });

        return isset($filters['permission'])
            ? $visibleMemberships->filter(function ($membership) use ($filters) {
                return $membership->inst_perms === $filters['permission'];
            })
            : $visibleMemberships;
    }

    private function validateFilters()
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?? [];

        if (array_key_exists('permission', $filtering)) {
            if (!in_array($filtering['permission'], ['user', 'autor', 'tutor', 'dozent', 'admin'])) {
                throw new BadRequestException('Filter `permission` must be one of `user`, `autor`, `tutor`, `dozent`, `admin`.');
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
