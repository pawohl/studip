<?php

namespace JsonApi\Routes\Courseware;

use Courseware\Unit;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays all PublicLinks
 */
class UnitsIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $user = $this->getUser($request);
        if (!Authority::canIndexUnits($user)) {
            throw new AuthorizationFailedException();
        }

        list($offset, $limit) = $this->getOffsetAndLimit();
        $resources = Unit::findBySQL('1 ORDER BY mkdate LIMIT ? OFFSET ?', [$limit, $offset]);

        return $this->getPaginatedContentResponse($resources, count($resources));
    }
}