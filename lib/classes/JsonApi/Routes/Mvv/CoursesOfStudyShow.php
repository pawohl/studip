<?php

namespace JsonApi\Routes\Mvv;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class CoursesOfStudyShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $course_of_study = \Studiengang::find($args['id']);
        if (!$course_of_study) {
            throw new RecordNotFoundException();
        }
        if (!Authority::canShowCourseOfStudy($user = $this->getUser($request), $course_of_study)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse($course_of_study);
    }
}
