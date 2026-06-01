<?php

namespace JsonApi\Routes\RangeTree;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use RangeTreeNode;

class ChildrenOfRangeTreeNode extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!RangeTreeNode::getNode($args['id'])) {
            throw new RecordNotFoundException();
        }

        [$offset, $limit] = $this->getOffsetAndLimit();
        $total = \RangeTreeNode::countByParent_id($args['id']);
        $children = \RangeTreeNode::findByParent_id(
            $args['id'],
            "LIMIT {$offset}, {$limit}"
        );

        return $this->getPaginatedContentResponse($children, $total);
    }
}
