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
class CoursesUnitsIndex extends JsonApiController
{
    use CoursewareInstancesHelper;

    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $course = \Course::find($args['id']);
        if (!$course) {
            throw new RecordNotFoundException();
        }
        $user = $this->getUser($request);
        if (!Authority::canIndexUnitsOfACourse($user, $course)) {
            throw new AuthorizationFailedException();
        }

        $resources = Unit::findCoursesUnits($course);
        $readable_resources = [];
        foreach ($resources as $resource) {
            if ($resource->canRead($user)) {
               $readable_resources[] = $resource;
            }
        }
        $total = count($readable_resources);
        [$offset, $limit] = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(array_slice($readable_resources, $offset, $limit), $total);
    }
}
