<?php
namespace JsonApi\Routes\Forum;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;
use Forum\Posting;
use Forum\PostingReaction;
use Studip\Forum\Enum\ReactionEmoji;

class PostingReactionStore extends JsonApiController
{
    use ValidationTrait;

    public function __invoke(Request $request, Response $response, $args)
    {
        $json = $this->validate($request);
        $user = $this->getUser($request);

        $posting = Posting::find(self::arrayGet($json, 'data.relationships.posting.data.id'));
        $emoji = ReactionEmoji::tryFrom(self::arrayGet($json, 'data.attributes.emoji'))?->value;
        if ($posting === null || $emoji === null) {
            throw new BadRequestException();
        }

        $range = get_object_by_range_id($posting->range_id);
        if ($range === null) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowForum($user, $range)) {
            throw new AuthorizationFailedException();
        }

        $data = [
            'posting_id' => $posting->posting_id,
            'user_id' => $user->user_id,
            'emoji' => $emoji
        ];

        $reaction = PostingReaction::findOneBySQL(
            'posting_id = :posting_id AND user_id = :user_id AND emoji = :emoji',
            $data
        );

        if ($reaction === null) {
            $reaction = PostingReaction::create($data);

            if ($user->user_id !== $posting->user_id) {
                \PersonalNotifications::add(
                    $posting->user_id,
                    \URLHelper::getURL(
                        "dispatch.php/course/forum/discussions/show/{$posting->discussion_id}#post_{$posting->posting_id}",
                        ['cid' => $posting->range_id],
                        true
                    ),
                    studip_interpolate(
                        _('%{name} hat auf deinen Beitrag reagiert.'),
                        ['name' => $user->getFullName()]
                    ),
                    null,
                    $emoji
                );
            }
        }

        return $this->getCreatedResponse($reaction);
    }

    protected function validateResourceDocument($json, $data)
    {
        $required_keys = [
            'data.attributes.emoji' => 'Missing `data.attributes.emoji`',
            'data.relationships.posting.data.id' => 'Missing `data.relationships.posting.data.id`',
        ];

        foreach ($required_keys as $key => $error_message) {
            if (!self::arrayHas($json, $key)) {
                return $error_message;
            }
        }

        return null;
    }
}
