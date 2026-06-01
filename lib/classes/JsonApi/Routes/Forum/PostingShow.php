<?php
namespace JsonApi\Routes\Forum;

use Forum\Posting;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;

class PostingShow extends JsonApiController
{
    public function __invoke(Request $request, Response $response, $args)
    {
        $posting = Posting::find($args['posting_id']);
        if (!$posting) {
            throw new RecordNotFoundException();
        }

        $range = get_object_by_range_id($posting->range_id);
        if (!$range) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);
        if (!Authority::canShowForum($user, $range)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse($posting);
    }
}
