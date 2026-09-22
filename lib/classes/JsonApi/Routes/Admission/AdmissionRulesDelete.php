<?php

namespace JsonApi\Routes\Admission;

use JsonApi\Errors\AuthorizationFailedException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

/**
 * Deletes an admission rule.
 */
class AdmissionRulesDelete extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $user = $this->getUser($request);

        [$type, $id] = explode('_', $args['id']);

        $rule = \AdmissionRule::getRule($type, $id);
        $courseSetId = \DBManager::get()->fetchColumn(
            "SELECT `set_id` FROM `courseset_rule` WHERE `rule_id` = :rule AND `type` = :type",
            ['rule' => $rule->getId(), 'type' => get_class($rule)]
        );

        if (!$rule) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canEditAdmissionRules($user, $courseSetId)) {
            throw new AuthorizationFailedException();
        }

        $rule->delete();

        return $this->getCodeResponse(204);
    }
}
