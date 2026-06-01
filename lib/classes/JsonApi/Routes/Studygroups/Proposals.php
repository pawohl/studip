<?php

namespace JsonApi\Routes\Studygroups;

use Course;
use DBManager;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use User;

final class Proposals extends JsonApiController
{
    protected $allowedPagingParameters = ['limit'];

    public function __invoke(Request $request, Response $response, $args): Response
    {
        $user = $this->getUser($request);

        [, $limit] = $this->getOffsetAndLimit(0, 4);

        $proposedStudygroupIds = $this->getProposedStudygroupIds($user, $limit);
        $proposedStudygroups = Course::findMany($proposedStudygroupIds);

        return $this->getContentResponse($proposedStudygroups);
    }

    private function getProposedStudygroupIds(User $user, int $amount = 4): array
    {
        $query = "SELECT DISTINCT `Seminar_id`
                  FROM (
                    SELECT `Seminar_id` FROM (
                      -- Andere Personen aus meinen Veranstaltungen
                      SELECT `seminare`.`Seminar_id`, COUNT(`seminar_user`.`user_id`) AS `count_colleagues`
                      FROM (
                        SELECT colleagues.`user_id`
                        FROM `seminar_user` AS colleagues
                        JOIN `seminar_user` AS mine USING (`Seminar_id`)
                        WHERE mine.`user_id` = :me
                          AND colleagues.`user_id` != mine.`user_id`
                      ) AS my_colleagues
                      JOIN `seminar_user`
                        ON (`my_colleagues`.`user_id` = `seminar_user`.`user_id`)
                      JOIN `seminare`
                        ON (`seminare`.`Seminar_id` = `seminar_user`.`Seminar_id`)
                      WHERE `seminare`.`status` IN (:studygroup_types)
                        AND NOT EXISTS(
                          SELECT 1
                          FROM `seminar_user`
                          WHERE `seminar_user`.`Seminar_id` = `seminare`.`Seminar_id`
                            AND `seminar_user`.`user_id` = :me
                        )
                      GROUP BY `seminare`.`seminar_id`
                      ORDER BY `count_colleagues` DESC
                      LIMIT 12
                    ) AS `colleagues_groups`

                    UNION ALL

                    SELECT `Seminar_id` FROM (
                      -- Andere Personen aus meinen Studiengängen
                      SELECT DISTINCT `seminare`.`Seminar_id`
                      FROM `user_studiengang`
                      STRAIGHT_JOIN `mvv_stgteil`
                        ON (`mvv_stgteil`.`fach_id` = `user_studiengang`.`fach_id`)
                      STRAIGHT_JOIN `mvv_stg_stgteil`
                        ON (`mvv_stg_stgteil`.`stgteil_id` = `mvv_stgteil`.`stgteil_id`)
                      STRAIGHT_JOIN `mvv_studiengang`
                        ON (
                          `mvv_studiengang`.`studiengang_id` = `mvv_stg_stgteil`.`studiengang_id`
                          AND `mvv_studiengang`.`abschluss_id` = `user_studiengang`.`abschluss_id`
                        )
                      STRAIGHT_JOIN `studygroup_stgteil`
                        ON (`studygroup_stgteil`.`stgteil_id` = `mvv_stgteil`.`stgteil_id`)
                      STRAIGHT_JOIN `seminare`
                        ON (`seminare`.`Seminar_id` = `studygroup_stgteil`.`studygroup_id`)
                      WHERE `seminare`.`status` IN (:studygroup_types)
                        AND `user_studiengang`.`user_id` = :me
                        AND NOT EXISTS(
                          SELECT 1
                          FROM `seminar_user`
                          WHERE `seminar_user`.`Seminar_id` = `seminare`.`Seminar_id`
                            AND `seminar_user`.`user_id` = :me
                        )
                      LIMIT 12
                    ) AS `same_studyarea_groups`

                    UNION ALL

                    SELECT `Seminar_id` FROM (
                      -- Neue Studiengruppen
                      SELECT `seminare`.`Seminar_id`
                      FROM `seminare`
                      WHERE `seminare`.`status` IN (:studygroup_types)
                        AND NOT EXISTS(
                          SELECT 1
                          FROM `seminar_user`
                          WHERE `seminar_user`.`Seminar_id` = `seminare`.`Seminar_id`
                            AND `seminar_user`.`user_id` = :me
                        )
                      ORDER BY `seminare`.`mkdate` DESC
                      LIMIT 12
                    ) AS `new_groups`
                ) AS `all_groups`";
        $groupIds = DBManager::get()->fetchFirst($query, [
            ':studygroup_types' => $this->getStudygroupSemTypeIds(),
            ':me'               => $user->id,
        ]);

        // Zufällig sortieren ist in PHP schneller als in SQL
        shuffle($groupIds);

        return array_slice($groupIds, 0, $amount);
    }

    private function getStudygroupSemTypeIds(): array
    {
        return array_filter(
            array_keys($GLOBALS['SEM_TYPE']),
            fn($sem_type_id) => (bool) $GLOBALS['SEM_CLASS'][$GLOBALS['SEM_TYPE'][$sem_type_id]['class']]['studygroup_mode']
        );
    }
}
