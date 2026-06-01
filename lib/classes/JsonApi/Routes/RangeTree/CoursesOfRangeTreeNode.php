<?php

namespace JsonApi\Routes\RangeTree;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class CoursesOfRangeTreeNode extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $node = \RangeTreeNode::find($args['id']);
        if (!$node) {
            throw new RecordNotFoundException();
        }

        list($offset, $limit) = $this->getOffsetAndLimit();
        $courses = $node->getCourses();

        return $this->getPaginatedContentResponse(
            $courses->limit($offset, $limit),
            count($courses)
        );
    }
}
