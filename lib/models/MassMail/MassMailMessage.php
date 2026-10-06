<?php

namespace MassMail;

use \Semester, \DBManager, \UserFilter, \Folder, \User, \Config;

/**
 * @license GPL2 or any later version
 *
 * @property int $id alias column for message_id
 * @property int $message_id database column
 * @property string|null $sender_id database column
 * @property string $author_id database column
 * @property int|null $send_at_date database column
 * @property string|null $target database column
 * @property \JSONArrayObject|null $config database column
 * @property string|null $exclude_users database column
 * @property string|null $cc database column
 * @property string $subject database column
 * @property string $message database column
 * @property string|null $folder_id database column
 * @property int $is_template database column
 * @property int $locked database column
 * @property int $sent database column
 * @property int $protected database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property \SimpleORMapCollection<MassMailFilter> $filters has_many MassMailFilter
 * @property \SimpleORMapCollection<MassMailToken> $tokens has_many MassMailToken
 * @property \User $author has_one \User
 * @property \User|null $sender has_one \User
 * @property \Folder|null $folder has_one \Folder
 */
class MassMailMessage extends \SimpleORMap implements \UserFilterRange
{

    protected static function configure($config = [])
    {
        $config['db_table'] = 'massmail_messages';

        $config['serialized_fields']['config'] = \JSONArrayObject::class;

        $config['has_one']['author'] = [
            'class_name' => User::class,
            'foreign_key' => 'author_id',
            'assoc_foreign_key' => 'user_id'
        ];
        $config['has_one']['sender'] = [
            'class_name' => User::class,
            'foreign_key' => 'sender_id',
            'assoc_foreign_key' => 'user_id'
        ];
        $config['has_many']['filters'] = [
            'class_name' => MassMailFilter::class,
            'assoc_foreign_key' => 'message_id',
            'on_store' => 'store',
            'on_delete' => 'delete'
        ];
        $config['has_one']['folder'] = [
            'class_name' => Folder::class,
            'foreign_key' => 'folder_id',
            'assoc_foreign_key' => 'id',
            'on_store' => 'store',
            'on_delete' => 'delete'
        ];
        $config['has_many']['tokens'] = [
            'class_name' => MassMailToken::class,
            'assoc_foreign_key' => 'message_id',
            'on_store' => 'store',
            'on_delete' => 'delete'
        ];

        parent::configure($config);
    }

    /**
     * Finds all messages that are currently due to be sent.
     * @return MassMailMessage[]
     */
    public static function findUnsent(): array
    {
        return static::findBySQL(
            "`is_template` = 0
                AND `sent` = 0
                AND `locked` = 0
                AND (`send_at_date` IS NULL OR `send_at_date` <= UNIX_TIMESTAMP())
            ORDER BY `mkdate`"
        );
    }

    /**
     * Finds all messages that have been successfully sent and can be deleted now according to their age.
     * @return MassMailMessage[]
     */
    public static function findObsolete(): array
    {
        return static::findBySQL(
            "`sent` = 1 AND `is_template` = 0 AND `protected` = 0 AND `chdate` <= :threshold",
            ['threshold' => time() - (Config::get()->MASSMAIL_GC_DAYS * 24 * 60 * 60)]
        );
    }

    /**
     * Possible targets for mass mails.
     * @return array
     */
    public static function getTargets(): array
    {
        return [
            'all' => _('alle'),
            'students' => _('Studierende'),
            'employees' => _('Beschäftigte'),
            'lecturers' => _('Aktive Lehrende'),
            'courses' => _('Veranstaltungen'),
            'usernames' => _('Liste von Benutzernamen'),
        ];
    }

    /**
     * Fetches all semesters.
     * @return array
     */
    public static function getSemesters(): array
    {
        $semesters = [];

        foreach (array_reverse(Semester::getAll()) as $one) {
            $semesters[$one->id] = $one->name;
        }

        return $semesters;
    }

    /**
     * Get the folder belonging to this message. If none is found, it will be auto-created as a
     * personal folder of the current user..
     * @param string $id
     * @return \FolderType
     */
    public function findFolder(string $id): \FolderType
    {
        $messageFolder = Folder::findOneBySQL(
            "`range_id` = :id AND `range_type` = 'massmail'",
            ['id' => $id]
        );
        if (!$messageFolder) {
            $messageFolder = new \StandardFolder([
                'user_id' => User::findCurrent()->id,
                'range_id' => $id,
                'range_type' => 'massmail',
                'parent_id' => 'root',
                'name' => _('Nachricht an Zielgruppen')
            ]);
            $messageFolder->store();
        } else {
            $messageFolder = $messageFolder->getTypedFolder();
        }

        return $messageFolder;
    }

    /**
     * Gets the real recipient list for this message.
     * @return string[] the usernames that will get this message.
     */
    public function getRecipients(): array
    {
        $ids = [];

        switch ($this->target) {
            // Everyone studying something or working at an institute.
            case 'all':

                $sql = "SELECT DISTINCT `user_id` FROM `user_studiengang`";
                $parameters = [];
                if (!MassMailPermission::has($this->author_id, true)) {

                    $permission = MassMailPermission::getForUser($this->author);

                    $sql .= " WHERE `abschluss_id` IN (:degrees) OR `fach_id` IN (:subjects)";
                    $parameters = [
                        'degrees' => $permission['allowed_degrees'],
                        'subjects' => $permission['allowed_subjects']
                    ];
                }
                $students = DBManager::get()->fetchFirst($sql, $parameters);

                $sql = "SELECT DISTINCT `user_id` FROM `user_inst` WHERE `inst_perms` IN (:perms)";
                $parameters = ['perms' => ['autor', 'tutor', 'dozent']];
                if (!MassMailPermission::has($this->author_id, true)) {
                    $sql .= " AND `Institut_id` IN (:institutes)";
                    $parameters['institutes'] = $permission['allowed_institutes'];
                }
                $employees = DBManager::get()->fetchFirst($sql, $parameters);

                $ids = array_unique(array_merge($students, $employees));

                break;

            // Students are users with at least one studycourse assignment in user_studiengang.
            case 'students':

                $sql = "SELECT DISTINCT `user_id` FROM `user_studiengang`";
                $parameters = [];

                if (!MassMailPermission::has($this->author_id, true)) {
                    $permission = MassMailPermission::getForUser($this->author);

                    $sql .= " WHERE `abschluss_id` IN (:degrees) OR `fach_id` IN (:subjects)";
                    $parameters = [
                        'degrees' =>  $permission['allowed_degrees'],
                        'subjects' => $permission['allowed_subjects']
                    ];
                }
                $ids = DBManager::get()->fetchFirst($sql, $parameters);

                if (count($this->filters) > 0) {

                    $filtered = [];
                    foreach ($this->filters as $filter) {
                        $f = new UserFilter($filter->filter_id);
                        $filtered = array_merge($filtered, $f->getUsers());
                    }

                    $ids = array_unique(array_intersect($ids, $filtered));

                }

                break;

            // Employees are users with at least one institute assignment at "autor" level or more.
            case 'employees':

                $sql = "SELECT DISTINCT `user_id` FROM `user_inst` WHERE `inst_perms` IN (:perms)";
                $parameters = ['perms' => ['autor', 'tutor', 'dozent']];
                if (!MassMailPermission::has($this->author_id, true)) {
                    $permission = MassMailPermission::getForUser($this->author);

                    $sql .= " AND `Institut_id` IN (:institutes)";
                    $parameters['institutes'] = $permission->allowed_institutes ? $permission->allowed_institutes->pluck('id') : [];
                }
                $ids = DBManager::get()->fetchFirst($sql, $parameters);

                if (count($this->filters) > 0) {

                    $filtered = [];
                    foreach ($this->filters as $filter) {
                        $f = new UserFilter($filter->filter_id);
                        $filtered = array_merge($filtered, $f->getUsers());
                    }

                    $ids = array_unique(array_intersect($ids, $filtered));

                }

                break;

            // Course members having the specified permission level.
            case 'courses':

                $courses = array_map(
                    fn ($course) => $course['id'],
                    $this->config['courses']->getArrayCopy()
                );
                $permission = $this->config['perm']->getArrayCopy();

                $ids = DBManager::get()->fetchFirst(
                    "SELECT DISTINCT `user_id` FROM `seminar_user` WHERE `Seminar_id` IN (:courses) AND `status` IN (:perm)",
                    ['courses' => $courses, 'perm' => $permission]
                );

                break;

            // Lecturers of at least one course in the given semester
            case 'lecturers':

                $ids = DBManager::get()->fetchFirst(
                    "SELECT DISTINCT u.`user_id` FROM `seminar_user` u
                        LEFT JOIN `semester_courses` sc ON (sc.`course_id` = u.`Seminar_id`)
                        JOIN `seminare` s ON (s.`Seminar_id` = u.`Seminar_id`)
                        JOIN `sem_types` t ON (t.`id` = s.`status`)
                    WHERE (sc.`semester_id` = :semester OR sc.`semester_id` IS NULL)
                        AND t.`class` IN (:categories)
                        AND u.`status` = 'dozent'",
                    [
                        'semester' => $this->config['semester'],
                        'categories' => Config::get()->MASSMAIL_LECTURER_SEM_CATEGORIES
                    ]
                );

                break;

            case 'usernames':

                $ids = DBManager::get()->fetchFirst(
                    "SELECT DISTINCT `user_id` FROM `auth_user_md5` WHERE `Username` IN (:usernames)",
                    ['usernames' => explode("\n", $this->config['usernames'])]
                );
        }

        return DBManager::get()->fetchFirst(
            "SELECT DISTINCT `username`
            FROM `auth_user_md5`
            WHERE `visible` != :visible
                AND `locked` = :locked
                AND `user_id` IN (:ids)
                AND `username` NOT IN (:exclude)
            ORDER BY `username`",
            [
                'visible' => 'never',
                'locked' => 0,
                'ids' => $ids,
                'exclude' => $this->exclude_users ? explode("\n", $this->exclude_users) : ['']
            ]
        );
    }

    /**
     * Checks whether this message has replacement markers in its message text.
     * @param $with_tokens Check for tokens or just for "normal" markers?
     * @return bool
     */
    public function hasMarkers($type = 'all'): bool
    {
        $markers = MassMailMarker::findAndMapBySQL(
            fn($m) => '{{' . $m->marker . '}}',
            $type === 'all' ? "1" : "`type` = :type",
            $type === 'all' ? [] : ['type' => $type]
        );
        foreach ($markers as $marker) {
            if (str_contains($this->message, $marker)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Replaces serial message markers with the data of the given user.
     * @param User $user
     * @return string
     */
    public function replaceMarkers(User $user): string
    {
        $text = MassMailMarker::processText($this->message, $user, $this->getMarkers());

        if (count($this->tokens) > 0) {
            $text = MassMailMarker::processToken($this->message, $text, $user);
        }

        return $text;
    }

    /**
     * Get available serial message markers, optionally including person token markers
     * @param bool $with_tokens
     * @return array
     */
    private function getMarkers($with_tokens = true): array
    {
        $found = [];
        $markers = MassMailMarker::findBySQL($with_tokens ? "1" : "`type` != 'token'");
        foreach ($markers as $marker) {
            if (str_contains($this->message, $marker->marker)) {
                $found[] = $marker;
            }
        }
        return $found;
    }

    /**
     * Get message attachments (excluding files used fot token generation)
     * @return array|\FileRef[]
     */
    public function getAttachments()
    {
        $files = [];
        $folder = Folder::find($this->folder_id);

        return array_filter(
            $folder->getTypedFolder()->getFiles(),
            fn ($ref) => !isset($ref->file->metadata['is_token_file'])
        );
    }

    /**
     * @see UserFilterRange::canEdit()
     */
    public function canEditFilter(User $user, UserFilter $filter): bool
    {
        return MassMailPermission::has($user->id, true)
            || MassMailPermission::has($user->id, false) && $this->creator_id === $user->id;

    }

}
