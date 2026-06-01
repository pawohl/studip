<?php

namespace JsonApi\Routes\UserFilters;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

/**
 * Shows a single UserFilter.
 */
class UserFiltersShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $userfilter = new \UserFilter($args['id']);

        // The userfilter object has a new ID -> new object not yet existing in database.
        if ($userfilter->getId() !== $args['id']) {
            throw new RecordNotFoundException();
        }

        return $this->getContentResponse($userfilter);
    }
}
