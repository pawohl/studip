<?php
namespace JsonApi\Routes\Forum;

use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;
use Studip\Markup;
use Forum\Posting;

class PostingUpdate extends JsonApiController
{
    use ValidationTrait;

    public function __invoke(Request $request, Response $response, $args)
    {
        $posting = Posting::find($args['posting_id']);
        if (!$posting) {
            throw new RecordNotFoundException();
        }

        if (
            !Authority::canEditPost($this->getUser($request), $posting, (bool) $posting->discussion->closed_at)
        ) {
            throw new AuthorizationFailedException();
        }

        $json = $this->validate($request);
        $posting->content = Markup::purifyHtml(Markup::markAsHtml(self::arrayGet($json, 'data.attributes.content')));
        $posting->anonymous = (self::arrayGet($json, 'data.attributes.anonymous') && \Config::get()->FORUM_ANONYMOUS_POSTINGS);
        $posting->store();

        return $this->getCreatedResponse($posting);
    }

    protected function validateResourceDocument($json, $data)
    {
        $required_keys = [
            'data.attributes.content' => 'Missing `data.attributes.content`',
            'data.attributes.anonymous' => 'Missing `data.attributes.anonymous`',
        ];

        foreach ($required_keys as $key => $error_message) {
            if (!self::arrayHas($json, $key)) {
                return $error_message;
            }
        }

        return null;
    }
}
