<?php

namespace JsonApi\Routes;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JsonApi\JsonApiController;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use Studip\Activity\SystemContext;
use Studip\Activity\CourseContext;
use Studip\Activity\Filter;
use Studip\Activity\InstituteContext;
use Studip\Activity\Stream;
use Studip\Activity\UserContext;

function canShowActivityStream(\User $observer, string $userId): bool
{
    if ($GLOBALS['perm']->have_perm('root', $observer->id)) {
        return true;
    }

    return $observer->id === $userId;
}

class ActivityStreamShow extends JsonApiController
{
    protected $allowedFilteringParameters = ['start', 'end', 'activity-type', 'context-type', 'context-id', 'object-type', 'object-id'];

    protected $allowedPagingParameters = ['offset', 'limit'];

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        if (!canShowActivityStream($this->getUser($request), $userId = $args['id'])) {
            throw new AuthorizationFailedException();
        }

        $user = \User::find($userId);
        if (!$user) {
            throw new RecordNotFoundException();
        }

        $urlFilter = $this->getUrlFilter();
        $contexts = $this->createContexts($user);
        $filter = $this->createFilter($urlFilter);

        try {
            $stream = $this->createStream($contexts, $filter);
            list($offset, $limit) = $this->getOffsetAndLimit();
            $total = count($stream);
            $data = array_slice($stream->getIterator()->getArrayCopy(), $offset, $limit);
        } catch (\Exception $exception) {
            throw new \JsonApi\Errors\InternalServerError($exception->getMessage());
        }

        $meta = ['filter' => $urlFilter];

        return $this->getPaginatedContentResponse($data, $total, 200, null, $meta);
    }

    private function getUrlFilter(): array
    {
        $params = $this->getQueryParameters();
        $filtering = $params->getFilteringParameters();

        $filter = [
            'start' => strtotime('-6 months'),
            'end' => time(),
            'activity-type' => null,
            'context-type' => null,
            'context-id' => null,
            'object-type' => null,
            'object-id' => null
        ];

        $filter = array_reduce(
            words('start end activity-type context-type context-id object-type object-id'),
            function ($filter, $key) use ($filtering) {
                if (isset($filtering[$key])) {
                    if ($key === 'start' || $key === 'end') {
                        $filter[$key] = (int) $filtering[$key];
                    } else {
                        $filter[$key] = $filtering[$key];
                    }
                }

                return $filter;
            },
            $filter
        );

        return $filter;
    }

    private function createContexts(\User $user): array
    {
        $contexts = [
            new SystemContext($user),
            new UserContext($user, $user),
        ];

        $user->contacts->each(function ($anotherUser) use (&$contexts, $user) {
            $contexts[] = new UserContext($anotherUser, $user);
        });

        if (!in_array($user->perms, ['admin', 'root'])) {
            // create courses and institutes context
            foreach (\Course::findMany($user->course_memberships->pluck('seminar_id')) as $course) {
                $contexts[] = new CourseContext($course, $user);
            }

            foreach (\Institute::findMany($user->institute_memberships->pluck('institut_id')) as $institute) {
                $contexts[] = new InstituteContext($institute, $user);
            }
        }

        return $contexts;
    }

    private function createFilter(array $urlFilter): Filter
    {
        $filter = new Filter();

        if (!empty($urlFilter['activity-type'])) {
            $types = array_filter(
                explode(',', $urlFilter['activity-type']),
                function ($word) {
                    return in_array(
                        $word,
                        [
                            'activity',
                            'documents',
                            'forum',
                            'message',
                            'news',
                            'participants',
                            'schedule',
                            'wiki',
                            'courseware'
                        ]
                    );
                }
            );

            if (count($types)) {
                $filter->setType((object) array_fill_keys(['course', 'institute', 'system', 'user'], $types));
            }
        }

        $filter->setStartDate($urlFilter['start']);
        $filter->setEndDate($urlFilter['end']);
        $filter->setContext($urlFilter['context-type']);
        $filter->setContextId($urlFilter['context-id']);
        $filter->setObjectType($urlFilter['object-type']);
        $filter->setObjectId($urlFilter['object-id']);

        return $filter;
    }

    private function createStream(array $contexts, Filter $filter): Stream
    {
        return new Stream($contexts, $filter);
    }
}
