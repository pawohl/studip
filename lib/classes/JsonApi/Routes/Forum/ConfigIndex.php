<?php

namespace JsonApi\Routes\Forum;

use Config;
use CoreForum;
use Studip\Forum\Enum\ReactionEmoji;
use UserConfig;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;

class ConfigIndex extends JsonApiController
{
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

        return $this->getMetaResponse([
            'is-admin' => CoreForum::isAdmin($range->id),
            'is-moderator' => CoreForum::isModerator($range->id),
            'is-tutor' => $GLOBALS['perm']->have_studip_perm('tutor', $range->id, $user->id),
            'is-categories-hidden' => $range->getConfiguration()->getValue('FORUM_HIDE_CATEGORIES'),
            'anonymous-post' => (bool) Config::get()->FORUM_ANONYMOUS_POSTINGS,
            'tile-layout' => (bool) UserConfig::get($user->user_id)->FORUM_TILE_LAYOUT,
            'reaction-emojis' => ReactionEmoji::toArray()
        ]);
    }
}
