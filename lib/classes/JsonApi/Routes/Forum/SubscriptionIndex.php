<?php
namespace JsonApi\Routes\Forum;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;
use Forum\Subscription;

class SubscriptionIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];
    public function __invoke(Request $request, Response $response, $args)
    {
        $range = get_object_by_range_id($args['range_id']);
        if (!$range) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);
        if (!Authority::canShowForum($user, $range)) {
            throw new AuthorizationFailedException();
        }

        $subscriptions = Subscription::getUserSubscriptions($range->id, $user->user_id);

        return $this->getPaginatedContentResponse(
            array_slice($subscriptions, ...$this->getOffsetAndLimit()),
            count($subscriptions)
        );
    }
}
