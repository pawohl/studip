<?php

namespace JsonApi\Routes\Courseware;

use Courseware\Template;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays all Templates
 */
class TemplatesIndex extends JsonApiController
{

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!Authority::canIndexTemplates($this->getUser($request))) {
            throw new AuthorizationFailedException();
        }

        $resources = Template::findBySQL('1 ORDER BY mkdate', []);

        return $this->getContentResponse($resources);
    }
}
