<?php
namespace JsonApi\Routes\Forum;

use Forum\PostingReaction;
use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;

class PostingReactionShow extends JsonApiController
{
    public function __invoke(Request $request, Response $response, $args)
    {
        $posting_reaction = PostingReaction::find($args['reaction_id']);
        if (!$posting_reaction) {
            throw new RecordNotFoundException();
        }

        return $this->getContentResponse($posting_reaction);
    }
}
