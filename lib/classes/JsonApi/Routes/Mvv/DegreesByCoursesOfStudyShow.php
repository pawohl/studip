<?php

namespace JsonApi\Routes\Mvv;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class DegreesByCoursesOfStudyShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $course_of_study = \Studiengang::find($args['id']);
        if (empty($course_of_study->abschluss)) {
            throw new RecordNotFoundException('Could not find degree.');
        }

        return $this->getContentResponse($course_of_study->abschluss);
    }
}
