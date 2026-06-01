<?php

namespace JsonApi\Routes\MassMail;

use JsonApi\Errors\AuthorizationFailedException;
use MassMail\MassMailMessage;
use MassMail\MassMailPermission;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JsonApi\JsonApiController;

class MassMailMessagesIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];
    protected $allowedFilteringParameters = ['templates', 'queued', 'protected', 'locked', 'sent'];
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!Authority::canIndexMassMailMessages($this->getUser($request))) {
            throw new AuthorizationFailedException();
        }

        $filters = $this->getContextFilters();

        [$offset, $limit] = $this->getOffsetAndLimit();

        $sql = "`is_template` = :template AND `locked` = :locked AND `sent` = :sent ".
            "ORDER BY `chdate` DESC";
        $parameters = [
            'template' => $filters['templates'] ? 1 : 0,
            'locked' => $filters['locked'] ? 1 : 0,
            'sent' => $filters['sent'] ? 1 : 0
        ];

        if ($filters['protected']) {
            $sql = "`protected` = :protected AND " . $sql;
            $parameters['protected'] = 1;
        }

        if (!MassMailPermission::has($this->getUser($request)->id, true) || $filters['templates']) {
            $sql = "`author_id` = :author AND " . $sql;
            $parameters['author'] = $this->getUser($request)->id;
        }

        $total = MassMailMessage::countBySQL($sql, $parameters);
        $messages = MassMailMessage::findBySQL(
            $sql . " LIMIT :limit OFFSET :offset",
            array_merge(
                $parameters,
                ['limit' => $limit,'offset' => $offset]
            ));

        return $this->getPaginatedContentResponse($messages, $total);
    }

    private function getContextFilters()
    {
        $defaults = [
            'templates' => false,
            'queued' => false,
            'protected' => false,
            'locked' => false,
            'sent' => false
        ];

        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        return array_merge($defaults, $filtering);
    }
}
