<?php

namespace JsonApi\Routes\Mvv;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class ModuleComponentsShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $component = \Modulteil::find($args['id']);
        if (!$component) {
            throw new RecordNotFoundException('Could not find module component.');
        }

        return $this->getContentResponse($component);
    }
}
