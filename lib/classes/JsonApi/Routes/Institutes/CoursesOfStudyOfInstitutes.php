<?php

namespace JsonApi\Routes\Institutes;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JsonApi\JsonApiController;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\Routes\Mvv\Authority as MvvAuthority;

class CoursesOfStudyOfInstitutes extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    public function __invoke(Request $request, Response $response, $args)
    {
        $institute = \Institute::find($args['id']);
        if (!$institute) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);

        [$offset, $limit] = $this->getOffsetAndLimit();
        $coursesOfStudy = $institute->courses_of_study->filter(
            fn(\Studiengang $c) => MvvAuthority::canShowCourseOfStudy($user, $c)
        );
        $total = count($coursesOfStudy);

        return $this->getPaginatedContentResponse(
            $coursesOfStudy->limit($offset, $limit),
            $total
        );
    }
}
