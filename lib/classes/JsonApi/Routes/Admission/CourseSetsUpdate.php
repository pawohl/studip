<?php

namespace JsonApi\Routes\Admission;

use JsonApi\Errors\AuthorizationFailedException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;

/**
 * Updates an existing courseset.
 */
class CourseSetsUpdate extends JsonApiController
{
    use ValidationTrait;

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $cs = new \CourseSet($args['id']);

        if (!$cs->getChdate()) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canUpdateCourseSet($this->getUser($request), $cs)) {
            throw new AuthorizationFailedException();
        }

        $json = $this->validate($request);

        $cs->setName(self::arrayGet($json, 'data.attributes.name'));
        $cs->clearAdmissionRules();

        foreach (self::arrayGet($json, 'data.attributes.rules') as $oneRule) {
            [$classname, $id] = explode('_', $oneRule['id']);

            $rule = \AdmissionRule::getRule($classname, $id);
            $rule->setAllData($oneRule['attributes']['payload']);
            $cs->addAdmissionRule($rule);
        }

        $cs->setPrivate(self::arrayGet($json, 'data.attributes.private'));
        $cs->setInfoText(self::arrayGet($json, 'data.attributes.infotext'));
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
