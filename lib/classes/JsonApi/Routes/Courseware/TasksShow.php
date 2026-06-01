<?php

namespace JsonApi\Routes\Courseware;

use Courseware\Task;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\Schemas\Courseware\PeerReview as PeerReviewSchema;
use JsonApi\Schemas\Courseware\Task as TaskSchema;
use JsonApi\Schemas\Courseware\TaskGroup as TaskGroupSchema;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays one Task.
 */
class TasksShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array $args
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        /** @var ?\Courseware\Task $resource */
        $resource = Task::find($args['id']);
        if (!$resource) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowTask($this->getUser($request), $resource)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse($resource);
    }
}
