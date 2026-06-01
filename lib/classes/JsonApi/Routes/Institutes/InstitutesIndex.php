<?php

namespace JsonApi\Routes\Institutes;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JsonApi\JsonApiController;

class InstitutesIndex extends JsonApiController
{
    protected $allowedFilteringParameters = ['is-faculty'];

    protected $allowedPagingParameters = ['offset', 'limit'];

    public function __invoke(Request $request, Response $response, $args)
    {
        [$offset, $limit] = $this->getOffsetAndLimit();

        $filters = $this->getFilters();

        if (!isset($filters['is-faculty'])) {
            $condition = '1';
        } elseif ($filters['is-faculty']) {
            $condition = 'fakultaets_id = Institut_id';
        } else {
            $condition = 'fakultaets_id != Institut_id';
        }

        $institutes = \Institute::findBySql("{$condition} ORDER BY Name LIMIT ? OFFSET ?", [$limit, $offset]);
        $total = \Institute::countBySql($condition);

        return $this->getPaginatedContentResponse($institutes, $total);
    }

    private function getFilters()
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?? [];

        $filters = [];

        if (isset($filtering['is-faculty'])) {
            $filters['is-faculty'] = (bool) $filtering['is-faculty'];
        }

        return $filters;
    }
}
