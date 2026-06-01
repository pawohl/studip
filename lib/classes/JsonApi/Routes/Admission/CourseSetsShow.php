<?php

namespace JsonApi\Routes\Admission;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

/**
 * Shows a single courseset.
 */
class CourseSetsShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $courseset = new \CourseSet($args['id']);
        if (!$courseset) {
            throw new RecordNotFoundException();
        }

        return $this->getContentResponse($courseset);
    }
}
