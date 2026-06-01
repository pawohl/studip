<?php

namespace JsonApi\Routes\Admission;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

/**
 * Shows a single admission rule.
 */
class AdmissionRulesShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $chunks = explode('_', $args['id']);
        $classname = $chunks[0];
        $id = $chunks[1] ?? null;

        $rule = \AdmissionRule::getRule($classname, $id);
        if (!$rule) {
            throw new RecordNotFoundException();
        }

        return $this->getContentResponse($rule);
    }
}
