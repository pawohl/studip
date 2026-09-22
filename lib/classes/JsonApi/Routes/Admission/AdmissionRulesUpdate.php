<?php

namespace JsonApi\Routes\Admission;

use JsonApi\Errors\AuthorizationFailedException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;

/**
 * Update an admission rule.
 */
class AdmissionRulesUpdate extends JsonApiController
{
    use ValidationTrait;

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $json = $this->validate($request);
        $user = $this->getUser($request);

        [$type, $id] = explode('_', $args['id']);

        $rule = \AdmissionRule::getRule($type, $id);

        if (!$rule) {
            throw new RecordNotFoundException();
        }

        $courseSetId = \DBManager::get()->fetchColumn(
            "SELECT `set_id` FROM `courseset_rule` WHERE `rule_id` = :rule AND `type` = :type",
            ['rule' => $rule->getId(), 'type' => get_class($rule)]
        );

        if (!Authority::canEditAdmissionRules($user, $courseSetId)) {
            throw new AuthorizationFailedException();
        }

        $payload = self::arrayGet($json, 'data.attributes.payload');

        $rule->setAllData($payload);

        $rule->store();

        return $this->getContentResponse($rule);
    }

    protected function validateResourceDocument($json, $data)
    {
        if (!self::arrayHas($json, 'data')) {
            return 'Missing `data` member at document´s top level.';
        }
        if (!self::arrayHas($json, 'data.attributes')) {
            return 'Missing `attributes` member of data block.';
        }
        if (!self::arrayHas($json, 'data.attributes.payload')) {
            return 'Missing `payload` member of attributes block.';
        }
    }

}
