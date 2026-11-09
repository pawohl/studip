<?php

namespace JsonApi\Routes\Admission;

use JsonApi\Errors\AuthorizationFailedException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;

/**
 * Create a new courseset.
 */
class CourseSetsCreate extends JsonApiController
{
    use ValidationTrait;

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $json = $this->validate($request);

        if (!Authority::canCreateCourseSets($this->getUser($request), self::arrayGet($json, 'data.attributes'))) {
            throw new AuthorizationFailedException();
        }

        $cs = new \CourseSet();
        $cs->setName(self::arrayGet($json, 'data.attributes.name'));

        foreach (self::arrayGet($json, 'data.attributes.rules') as $oneRule) {
            $rule = \AdmissionRule::getRule($oneRule['attributes']['type']);
            $rule->setAllData($oneRule['attributes']['payload']);
            $cs->addAdmissionRule($rule);
        }

        $cs->setPrivate(self::arrayGet($json, 'data.attributes.private'));
        $cs->setAlgorithm('RandomAlgorithm');
        $cs->setInstitutes(self::arrayGet($json, 'data.attributes.institutes'));
        $cs->setCourses(self::arrayGet($json, 'data.attributes.courses'));
        $cs->setUserlists(self::arrayGet($json, 'data.attributes.userlists'));

        $cs->store();

        return $this->getCreatedResponse($cs);
    }

    protected function validateResourceDocument($json, $data)
    {
        if (!self::arrayHas($json, 'data')) {
            return 'Missing `data` member at document´s top level.';
        }
        if (!self::arrayHas($json, 'data.attributes')) {
            return 'Missing `attributes` member of data block.';
        }
        if (!self::arrayHas($json, 'data.attributes.name')) {
            return 'Missing `name` member of data block.';
        }
    }

}
