<?php

namespace JsonApi\Routes\UserFilters;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Errors\BadRequestException;

/**
 * Shows a single UserFilterField.
 */
class UserFilterFieldsShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        [$class, $id] = explode('_', $args['id']);

        $classname = '\\' . $class;

        if (is_subclass_of($classname, \UserFilterField::class)) {
            $field = new $classname($id);

            // The userfilter object has a new ID -> new object not yet existing in database.
            if ($field->getId() !== $id) {
                throw new RecordNotFoundException();
            }
        } else {
            throw new BadRequestException();
        }

        return $this->getContentResponse($field);
    }
}
