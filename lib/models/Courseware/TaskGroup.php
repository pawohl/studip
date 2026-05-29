<?php

namespace Courseware;

use DBManager;
use Statusgruppen;
use User;

/**
 * Courseware's tasks.
 *
 * @author  Ron Lucke <lucke@elan-ev.de>
 * @license GPL2 or any later version
 *
 * @since   Stud.IP 5.1
 *
 * @property int $id database column
 * @property string $seminar_id database column
 * @property string $lecturer_id database column
 * @property int $target_id database column
 * @property int $task_template_id database column
 * @property int $solver_may_add_blocks database column
 * @property string $title database column
 * @property int $start_date database column
 * @property int $end_date database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property \SimpleORMapCollection<Task> $tasks has_many Task
 * @property \SimpleORMapCollection<PeerReviewProcess> $peer_review_processes has_many PeerReviewProcess
 * @property \User $lecturer belongs_to \User
 * @property \Course $course belongs_to \Course
 * @property StructuralElement $target belongs_to StructuralElement
 */
class TaskGroup extends \SimpleORMap implements \PrivacyObject
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'cw_task_groups';

        $config['belongs_to']['lecturer'] = [
            'class_name' => User::class,
            'foreign_key' => 'lecturer_id',
        ];

        $config['belongs_to']['course'] = [
            'class_name' => \Course::class,
            'foreign_key' => 'seminar_id',
        ];

        $config['belongs_to']['target'] = [
            'class_name' => StructuralElement::class,
            'foreign_key' => 'target_id',
        ];

        $config['has_many']['tasks'] = [
            'class_name' => Task::class,
            'assoc_foreign_key' => 'task_group_id',
            'on_delete' => 'delete',
            'on_store' => 'store',
            'order_by' => 'ORDER BY mkdate',
        ];

        $config['has_many']['peer_review_processes'] = [
            'class_name' => PeerReviewProcess::class,
            'assoc_foreign_key' => 'task_group_id',
            'on_delete' => 'delete',
            'on_store' => 'store',
            'order_by' => 'ORDER BY mkdate',
        ];

        $config['registered_callbacks']['after_store'][] = 'cbAfterStore';

        parent::configure($config);
    }

    /**
     * Export available data of a given user into a storage object
     * (an instance of the StoredUserData class) for that user.
     *
     * @param StoredUserData $storage object to store data into
     */
    public static function exportUserData(\StoredUserData $storage)
    {
        $task_groups = DBManager::get()->fetchAll('SELECT * FROM cw_task_groups WHERE lecturer_id = ?', [
            $storage->user_id,
        ]);
        if ($task_groups) {
            $storage->addTabularData(_('Courseware Aufgaben'), 'cw_task_groups', $task_groups);
        }
    }

    public function getSolvers(): iterable
    {
        $solvers = $this->tasks->pluck('solver');

        return array_filter($solvers);
    }

    /**
     * Returns all submitters of this TaskGroup.
     *
     * @returns iterable all the submitters of this TaskGroup.
     */
    public function getSubmitters(): iterable
    {
        return DBManager::get()->fetchAll(
            'SELECT solver_id, solver_type FROM cw_tasks WHERE task_group_id = ? AND submitted = 1',
            [$this->getId()],
            function ($row) {
                switch ($row['solver_type']) {
                    case 'autor':
                        return \User::find($row['solver_id']);
                    case 'group':
                        return \Statusgruppen::find($row['solver_id']);
                }
            }
        );
    }

    public function hasPeerReviewProcesses(): bool
    {
        return PeerReviewProcess::countBySql('task_group_id = ?', [$this->getId()]) > 0;
    }

    /**
     * Returns the task of this TaskGroup given to $solver.
     *
     * @param User|Statusgruppen $solver
     *
     * @return Task|null
     */
    public function findTaskBySolver($solver)
    {
        $row = DBManager::get()->fetchOne(
            'SELECT id FROM cw_tasks WHERE task_group_id = ? AND solver_id = ? AND solver_type = ?',
            [
                $this->getId(),
                $solver->getId(),
                $solver instanceof User ? 'autor' : 'group',
            ]
        );

        return empty($row) ? null : Task::find($row['id']);
    }

    public function cbAfterStore(): void
    {
        if ($this->isFieldDirty('end_date')) {
            $this->reschedulePeerReviewProcesses();
        }
    }

    private function reschedulePeerReviewProcesses(): void
    {
        if ($this->hasPeerReviewProcesses()) {
            foreach ($this->peer_review_processes as $process) {
                $process->rescheduleTo($this->end_date);
            }
        }
    }
}
