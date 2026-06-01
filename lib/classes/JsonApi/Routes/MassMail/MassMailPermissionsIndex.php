<?php

namespace JsonApi\Routes\MassMail;

use JsonApi\Errors\AuthorizationFailedException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JsonApi\JsonApiController;

class MassMailPermissionsIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!Authority::canIndexMassMailPermissions($this->getUser($request))) {
            throw new AuthorizationFailedException();
        }

        [$offset, $limit] = $this->getOffsetAndLimit();

        $total = \MassMail\MassMailPermission::countBySQL('1');
        $permissions = \MassMail\MassMailPermission::findBySQL(
            "JOIN `Institute` ON (`Institute`.`Institut_id` = `massmail_permissions`.`institute_id`)
            ORDER BY `Institute`.`Name` LIMIT ?, ?",
            [$offset, $limit]);

        return $this->getPaginatedContentResponse($permissions, $total);
    }
}
