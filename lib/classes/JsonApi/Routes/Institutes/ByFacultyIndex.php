<?php

namespace JsonApi\Routes\Institutes;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class ByFacultyIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    public function __invoke(Request $request, Response $response, $args)
    {
        $institute = \Institute::find($args['id']);

        if (!$institute) {
            throw new RecordNotFoundException();
        }

        $institutes = $institute->sub_institutes;
        $total = count($institutes);
        [$offset, $limit] = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse($institutes->limit($offset, $limit), $total);
    }
}
