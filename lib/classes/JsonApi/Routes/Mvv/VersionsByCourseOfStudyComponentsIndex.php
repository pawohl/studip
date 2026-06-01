<?php

namespace JsonApi\Routes\Mvv;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class VersionsByCourseOfStudyComponentsIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $user = $this->getUser($request);
        $component = \StudiengangTeil::find($args['id']);
        if (!$component) {
            throw new RecordNotFoundException();
        }
        [$offset, $limit] = $this->getOffsetAndLimit();
        $versions = $component->versionen->filter(
            fn($version) => Authority::canShowComponentVersion($user, $version)
        );

        return $this->getPaginatedContentResponse(
            $versions->limit($offset, $limit),
            count($versions)
        );
    }
}
