<?php
namespace JsonApi\Routes\Forum;

use Forum\Posting;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;
use SimpleORMapCollection;

class PostingReactions extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];
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

        $reactions = $posting->reactions ?? SimpleORMapCollection::createFromArray([]);

        return $this->getPaginatedContentResponse(
            $reactions->limit(...$this->getOffsetAndLimit()),
            count($reactions)
        );
    }
}
