<?php

namespace JsonApi\Routes\Courses;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

/**
 * Zeigt eine bestimmte Veranstaltung an.
 */
class CoursesShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $course = \Course::find($args['id']);
        if (!$course) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowCourse($this->getUser($request), $course)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse($course);
    }
}
