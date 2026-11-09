<?php

namespace JsonApi\Routes\UserFilters;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;

/**
 * Updates an existing UserFilter.
 */
class UserFiltersUpdate extends JsonApiController
{
    use ValidationTrait;

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $filter = new \UserFilter($args['id']);

        if ($filter['id'] !== $args['id']) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);

        if (!Authority::canEditUserFilters($user, $filter)) {
            throw new AuthorizationFailedException();
        }

        $json = $this->validate($request);

        $filter->fields = [];

        foreach (self::arrayGet($json, 'data.attributes.filters') as $one) {
            $classname = '\\' . $one['attributes']['type'];

            if (is_subclass_of($classname, \UserFilterField::class)) {
                $field = !empty($one['attributes']['typeparam'])
                    ? new $classname($one['attributes']['typeparam'])
                    : new $classname();
                $field->setValue($one['attributes']['value']);
                $field->setCompareOperator($one['attributes']['compare-operator']);
                $filter->addField($field);
            } else {
                throw new BadRequestException();
            }
        }

        $filter->id = '';

        return $this->getCreatedResponse($filter);
    }

    protected function validateResourceDocument($json, $data)
    {
        if (!self::arrayHas($json, 'data')) {
            return 'Missing `data` member at document´s top level.';
        }
        if (!self::arrayHas($json, 'data.attributes')) {
            return 'Missing `attributes` member of data block.';
        }
        if (!self::arrayHas($json, 'data.attributes.filters')) {
            return 'Missing `filters` member of attributes block.';
        }
    }

}
