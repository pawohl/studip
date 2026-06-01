<?php
namespace JsonApi\Routes\Forum;

use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;
use Forum\Enum\SubscriptionNotificationType;
use Studip\Markup;
use Forum\Discussion;
use Forum\Posting;
use Forum\PostingRead;
use Forum\Subscription;

class PostingStore extends JsonApiController
{
    use ValidationTrait;

    public function __invoke(Request $request, Response $response, $args)
    {
        $json = $this->validate($request);
        $user = $this->getUser($request);

        $discussion = Discussion::find(self::arrayGet($json, 'data.relationships.discussion.data.id'));
        $range = get_object_by_range_id($discussion->range_id);

        if (!$discussion || !$range) {
            throw new RecordNotFoundException();
        }

        if (
            !Authority::canShowForum($user, $range) ||
            $discussion->closed_at
        ) {
            throw new AuthorizationFailedException();
        }

        $parent_id = self::arrayGet($json, 'data.relationships.posting.data.id');

        $psoting = Posting::create([
            'range_id' => $discussion->range_id,
            'parent_id' => $parent_id ?? null,
            'discussion_id' => $discussion->discussion_id,
            'content' => Markup::purifyHtml(Markup::markAsHtml(self::arrayGet($json, 'data.attributes.content'))),
            'anonymous' => (self::arrayGet($json, 'data.attributes.anonymous') && \Config::get()->FORUM_ANONYMOUS_POSTINGS),
            'user_id' => $user->user_id
        ]);

        $subscription = Subscription::findOneBySQL(
            "user_id = :user_id AND subject_id IN (:subject_ids)",
            [
                'user_id' => $user->user_id,
                'subject_ids' => [$discussion->discussion_id, $discussion->topic_id]
            ]
        );

        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->user_id = $user->user_id;
            $subscription->range_id = $discussion->range_id;
            $subscription->subject_id = $discussion->discussion_id;
            $subscription->subject = 'discussion';
            $subscription->notification_type = SubscriptionNotificationType::All->value;
            $subscription->store();
        }

        PostingRead::updateUserReadPoint($user->user_id, $discussion->discussion_id);

        return $this->getCreatedResponse($psoting);
    }

    protected function validateResourceDocument($json, $data)
    {
        $required_keys = [
            'data.attributes.content' => 'Missing `data.attributes.content`',
            'data.attributes.anonymous' => 'Missing `data.attributes.anonymous`',
            'data.relationships.discussion.data.id' => 'Missing `data.relationships.discussion.data.id`',
        ];

        foreach ($required_keys as $key => $error_message) {
            if (!self::arrayHas($json, $key)) {
                return $error_message;
            }
        }

        return null;
    }
}
