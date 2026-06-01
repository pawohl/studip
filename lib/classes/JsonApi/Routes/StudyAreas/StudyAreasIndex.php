<?php

namespace JsonApi\Routes\StudyAreas;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;

/**
 * Zeigt einen bestimmten Studienbereich an.
 */
class StudyAreasIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $root = \StudipStudyArea::getRootArea();
        $studyAreas =  $this->mapTree($root);

        list($offset, $limit) = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(
            array_slice($studyAreas, $offset, $limit),
            count($studyAreas)
        );
    }

    private function mapTree(\StudipStudyArea $node)
    {
        $level = [];
        $child_nodes = $node->getChildNodes();
        foreach ($child_nodes as $child_node) {
            $level[] = $child_node;
            $level = array_merge($level, $this->mapTree($child_node));
        }
        return $level;
    }
}
