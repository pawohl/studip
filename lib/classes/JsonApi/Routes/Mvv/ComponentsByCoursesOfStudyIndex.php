<?php

namespace JsonApi\Routes\Mvv;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class ComponentsByCoursesOfStudyIndex extends JsonApiController
{
    protected $allowedPagingParameters = [
        'offset',
        'limit'
    ];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $course_of_study = \Studiengang::find($args['id']);
        if (!$course_of_study) {
            throw new RecordNotFoundException();
        }
        [$offset, $limit] = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(
            $course_of_study->studiengangteile->limit($offset, $limit),
            count($course_of_study->studiengangteile)
        );
    }
}
