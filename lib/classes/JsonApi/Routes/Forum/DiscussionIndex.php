<?php
namespace JsonApi\Routes\Forum;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;
use Forum\Discussion;

class DiscussionIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];
    protected $allowedFilteringParameters = [
        'last-visit',
        'keyword',
        'begin',
        'end',
        'topic-ids',
        'type-ids',
        'tag-ids',
        'user-ids',
        'status'
    ];

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

        $filters = $this->getFilter();
        if ($filters) {
            $_SESSION['forum'][$range->id]['search_filter'] = $filters;
        }

        $discussions = Discussion::getCourseDiscussions($range->id, $filters);

        return $this->getPaginatedContentResponse(
            array_slice($discussions, ...$this->getOffsetAndLimit()),
            count($discussions)
        );
    }

    private function getFilter(): array
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        $discussion_filter = [];

        if (isset($filtering['last-visit'])) {
            $discussion_filter['last_visit'] = (int) $filtering['last-visit'];
        }

        if (isset($filtering['keyword'])) {
            $discussion_filter['keyword'] = $filtering['keyword'];
        }

        if (isset($filtering['status'])) {
            $discussion_filter['status'] = (int) $filtering['status'];
        }

        if (isset($filtering['begin'])) {
            $discussion_filter['begin'] = (int) $filtering['begin'];
        }

        if (isset($filtering['end'])) {
            $discussion_filter['end'] = (int) $filtering['end'];
        }

        if (isset($filtering['topic-ids'])) {
            $discussion_filter['topic_ids'] = explode(',', $filtering['topic-ids']);
        }

        if (isset($filtering['type-ids'])) {
            $discussion_filter['type_ids'] = explode(',', $filtering['type-ids']);
        }

        if (isset($filtering['tag-ids'])) {
            $discussion_filter['tag_ids'] = explode(',', $filtering['tag-ids']);
        }

        if (isset($filtering['user-ids'])) {
            $discussion_filter['user_ids'] = explode(',', $filtering['user-ids']);
        }

        return $discussion_filter;
    }
}
