<?php

namespace JsonApi\Routes\Courseware;

use Courseware\Task;
use Courseware\TaskGroup;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\JsonApiController;
use JsonApi\Schemas\Courseware\Task as TaskSchema;
use JsonApi\Schemas\Courseware\TaskGroup as TaskGroupSchema;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays all Tasks.
 */
class TasksIndex extends JsonApiController
{
    protected $allowedFilteringParameters = ['cid'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param array $args
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if ($error = $this->validateFilters()) {
            throw new BadRequestException($error);
        }

        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];
        $resources = [];

        if (empty($filtering)) {
            if (!Authority::canIndexTasks($this->getUser($request))) {
                throw new AuthorizationFailedException('Only root users may index all tasks without a `filter[cid]`.');
            }

            $resources = Task::findBySQL('1 ORDER BY mkdate', []);
        } else {
            $user = $this->getUser($request);
            /** @var ?\Course $course */
            $course = \Course::find($filtering['cid']);

            if ($GLOBALS['perm']->have_studip_perm('tutor', $course->getId(), $user->getId())) {
                $resources = $this->findTasksByCourse($course);
            } else {
                $resources = $this->findTasksByCourseMember($user, $course);
            }
        }

        return $this->getContentResponse($resources);
    }

    private function validateFilters()
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        // course
        if (isset($filtering['cid'])) {
            $course = \Course::find($filtering['cid']);
            if (!$course) {
                return 'Could not find a course matching this `filter[cid]`.';
            }
        }
    }

    private function findTasksByCourse(\Course $course, bool $showNotYetActive = true): \SimpleCollection
    {
        $whereClause = $showNotYetActive ? 'seminar_id = ?' : 'start_date <= UNIX_TIMESTAMP() AND seminar_id = ?';
        $taskGroups = TaskGroup::findBySQL($whereClause, [$course->getId()]);

        $tasks = [];
        foreach ($taskGroups as $taskGroup) {
            $tasks[] = $taskGroup->tasks->getArrayCopy();
        }
        $tasks = \SimpleORMapCollection::createFromArray(array_flatten($tasks), false)->orderBy('id asc');

        return $tasks;
    }

    private function findTasksByCourseMember(\User $user, \Course $course): \SimpleCollection
    {
        $groupIds = $course['statusgruppen']
            ->filter(function (\Statusgruppen $group) use ($user) {
                return $group->isMember($user->getId());
            })
            ->pluck('id');

        return $this->findTasksByCourse($course, false)->filter(function ($task) use ($user, $groupIds) {
            return ('autor' === $task['solver_type'] && $task['solver_id'] === $user->getId()) ||
                ('group' === $task['solver_type'] && in_array($task['solver_id'], $groupIds));
        });
    }
}
