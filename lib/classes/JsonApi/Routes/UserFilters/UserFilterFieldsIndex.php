<?php

namespace JsonApi\Routes\UserFilters;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\BadRequestException;
use JsonApi\JsonApiController;

class UserFilterFieldsIndex extends JsonApiController
{
    protected $allowedFilteringParameters = ['context', 'target'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $error = $this->validateFilters();
        if ($error) {
            throw new BadRequestException($error);
        }

        $filters = $this->getContextFilters();

        $fields = [];
        foreach (\UserFilterField::getAvailableFilterFields($filters['context'], $filters['target']) as $class => $name) {
            // Generic datafield conditions must be handled differently.
            if (str_contains($class, '_')) {
                [$classname, $typeparam] = explode('_', $class);
                $fields[] = new $classname($typeparam);
            } else {
                $fields[] = new $class();
            }
        }

        return $this->getContentResponse($fields);
    }

    private function validateFilters()
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        // context aka namespace filter
        if (
            isset($filtering['context'])
            && !in_array($filtering['context'], \UserFilterField::$validContexts)
        ) {
            return 'Requested context does not exist.';
        }
    }

    private function getContextFilters()
    {
        $defaults = [
            'context' => '',
            'target' => ''
        ];

        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        return array_merge($defaults, $filtering);
    }
}
