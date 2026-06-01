<?php

namespace JsonApi\Routes\Schedule;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use \ScheduleEntry;
use JsonApi\Routes\Users\Authority;
use Neomerx\JsonApi\Schema\Link;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Zeige den Stundenplan eines Nutzers.
 */
class UserScheduleShow extends JsonApiController
{
    protected $allowedFilteringParameters = ['timestamp'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!$otherUser = \User::find($args['id'])) {
            throw new RecordNotFoundException('Could not find user.');
        }

        if (!Authority::canEditUser($this->getUser($request), $otherUser)) {
            throw new AuthorizationFailedException();
        }

        $filtering = $this->getQueryParameters()->getFilteringParameters();
        $semester = isset($filtering['timestamp'])
                  ? \Semester::findByTimestamp((int) $filtering['timestamp'])
                  : \Semester::findCurrent();

        if (!$semester) {
            throw new RecordNotFoundException('Could not find semester.');
        }

        return $this->getContentResponse(
            array_merge(
                ScheduleEntry::findByUser_id($otherUser->id),
                $this->getCycles($otherUser, $semester)
            ),
            200,
            [Link::SELF => $this->getSelfLink($otherUser, $semester)],
            $this->getMeta($semester)
        );
    }

    private function getCycles(\User $user, \Semester $semester): array
    {
        // get all virtually added seminars
        $stmt = \DBManager::get()->prepare(
            'SELECT c.`course_id` FROM `schedule_courses` as c
             LEFT JOIN `seminare` ON `seminare`.`seminar_id` = c.`course_id`
             JOIN `semester_courses` ON `seminare`.`seminar_id` = `semester_courses`.`course_id`
             WHERE user_id = :user_id AND semester_id = :semester_id'
        );
        $stmt->execute(['user_id' => $user->id, 'semester_id' => $semester->id]);
        $ids = $stmt->fetchFirst();

        // fetch seminar-entries
        $stmt = \DBManager::get()->prepare('
            SELECT s.seminar_id
            FROM seminar_user as su
                LEFT JOIN seminare as s USING (seminar_id)
                LEFT JOIN semester_courses ON (s.Seminar_id = semester_courses.course_id)
            WHERE su.user_id = :userid
                AND (semester_courses.semester_id IS NULL OR semester_courses.semester_id = :semester_id)
        ');
        $stmt->execute([
            'userid' => $user->id,
            'semester_id' => $semester->id,
        ]);

        return array_reduce(
            array_unique(array_merge($ids, $stmt->fetchFirst())),
            function ($cycles, $seminarId) {
                return array_merge(
                    $cycles,
                    array_filter(
                        \Course::find($seminarId)->cycles->getArrayCopy(),
                        function ($cycle) {
                            return $cycle['is_visible'];
                        }
                    )
                );
            },
            []
        );
    }

    private function getSelfLink(\User $user, \Semester $semester): Link
    {
        $routeParser = $this->app->getRouteCollector()->getRouteParser();
        $url = $routeParser->urlFor('get-schedule', ['id' => $user->id], ['filter[timestamp]' => $semester->beginn]);

        return new Link(false, $url, false);
    }

    private function getMeta(\Semester $semester): array
    {
        $routeParser = $this->app->getRouteCollector()->getRouteParser();
        $url = $routeParser->urlFor('get-semester', ['id' => $semester->id]);

        return [ 'semester' => $url ];
    }
}
