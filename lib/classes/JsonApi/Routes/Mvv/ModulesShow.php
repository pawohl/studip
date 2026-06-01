<?php

namespace JsonApi\Routes\Mvv;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class ModulesShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $module = \Modul::find($args['id']);
        if (!$module) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowModule($this->getUser($request), $module)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse($module);
    }
}
