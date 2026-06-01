<?php

namespace JsonApi\Routes\Mvv;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class SubjectsShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $subject = \Fach::find($args['id']);
        if (!$subject) {
            throw new RecordNotFoundException('Could not find subject.');
        }

        return $this->getContentResponse($subject);
    }
}
