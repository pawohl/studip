<?php
/**
 * WikiPage.php
 * model class for table wiki
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author    mlunzena
 * @copyright (c) Authors
 *
 * @property int $id alias column for page_id
 * @property int $page_id database column
 * @property string $range_id database column
 * @property string $name database column
 * @property string|null $content database column
 * @property int|null $parent_id database column
 * @property string $read_permission database column
 * @property string $write_permission database column
 * @property string $user_id database column
 * @property int|null $locked_since database column
 * @property string|null $locked_by_user_id database column
 * @property int $chdate database column
 * @property int $mkdate database column
 * @property SimpleORMapCollection<WikiVersion> $versions
 * @property SimpleORMapCollection<WikiOnlineEditingUser> $onlineeditingusers
 * @property User $user belongs_to User
 * @property Course $course belongs_to Course
 * @property-read (WikiPage | null) $parent additional field
 * @property-read WikiPage[] $children additional field
 * @property-read (WikiVersion | null) $predecessor additional field
 * @property-read int $versionnumber additional field
 */
class WikiPage extends SimpleORMap implements PrivacyObject
{
    /**
     * Configures the model
     * @param  array  $config Configuration
     */
    protected static function configure($config = [])
    {
        $config['db_table'] = 'wiki_pages';

        $config['belongs_to']['user'] = [
            'class_name'  => User::class,
            'foreign_key' => 'user_id'
        ];
        $config['belongs_to']['course'] = [
            'class_name'  => Course::class,
            'foreign_key' => 'range_id',
        ];
        $config['has_many']['versions'] = [
            'class_name'  => WikiVersion::class,
            'foreign_key' => 'page_id',
            'order_by'    => 'ORDER BY mkdate DESC',
            'on_delete'   => 'delete',
        ];
        $config['has_many']['onlineeditingusers'] = [
            'class_name' => WikiOnlineEditingUser::class,
            'foreign_key' => 'page_id',
            'on_delete'   => 'delete',
        ];

        $config['additional_fields']['parent'] = [
            'get' => function (WikiPage $page): ?WikiPage {
                return self::find($page->parent_id);
            }
        ];

        $config['additional_fields']['children'] = [
            'get' => function (WikiPage $page): array {
                return self::findBySQL('parent_id = ?', [
                    $page->id
                ]);
            }
        ];
        $config['additional_fields']['predecessor'] = [
            'get' => function (WikiPage $page): ?WikiVersion {
                return $page->versions ? $page->versions[0] : null;
            }
        ];
        $config['additional_fields']['versionnumber'] = [
            'get' => function (WikiPage $page): int {
                return count($page->versions) + 1;
            }
        ];

        $config['registered_callbacks']['before_store'][] = 'createVersion';
        $config['registered_callbacks']['after_delete'][] = function (WikiPage $page) {
            $query = "UPDATE `wiki_pages` SET `parent_id` = NULL WHERE `parent_id` = ?";
            DBManager::get()->execute($query, [$page->id]);
        };

        $config['default_values']['last_author'] = 'nobody';

        parent::configure($config);
    }

    protected function createVersion()
    {
        $last_version = $this->versions[0];
        if (
            !$this->isNew()
            && $this->content['content'] !== $this->content_db['content']
            && (
                $this->content_db['user_id'] !== $this->content['user_id']
                || $this->content_db['chdate'] < time() - 60 * 30
            )
            && (!$last_version || $last_version['content'] !== $this['content'])
        ) {
            //Neue Version anlegen:
            WikiVersion::create([
                'page_id' => $this->id,
                'name'    => $this->content_db['name'],
                'content' => $this->content_db['content'],
                'user_id' => $this->content_db['user_id'],
                'mkdate'  => $this->content_db['chdate'],
            ]);
        }
        return true;
    }

    public static function findByName($range_id, $name)
    {
        return self::findOneBySQL(
            'BINARY TRIM(name) = :name AND range_id = :range_id',
            [
             ':range_id' => $range_id,
             ':name'     => trim($name),
            ]
        );
    }


    /**
     * Returns whether this page is visible to the given user.
     * @param  string|null $user_id User id
     * @return boolean indicating whether the page is visible
     */
    public function isReadable(?string $user_id = null): bool
    {
        if ($this->isNew()) {
            return true;
        }
        // anyone can see this page if it belongs to a free course
        if (
            $this->read_permission === 'all'
            && Config::get()->ENABLE_FREE_ACCESS
            && $this->course
            && !$this->course->lesezugriff
        ) {
            return true;
        }
        if ($user_id === null && User::findCurrent()) {
            $user_id = User::findCurrent()->id;
        }

        if ($this->read_permission === 'all') {
            if ($GLOBALS['perm']->have_studip_perm('user', $this->range_id, $user_id)) {
                return true;
            }
            $inst = Institute::find($this->range_id);
            if ($inst) {
                //in institutes anyone can read the wiki
                return true;
            }
        }

        if ($GLOBALS['perm']->have_studip_perm(
            'dozent',
            $this->range_id,
            $user_id
        )) {
            return true;
        }

        if (in_array($this->read_permission, ['tutor', 'dozent'])) {
            return $GLOBALS['perm']->have_studip_perm($this->read_permission, $this->range_id, $user_id);
        } else {
            return StatusgruppeUser::exists([$this->read_permission, $user_id]);
        }
    }

    /**
     * Returns whether this page is editable to the given user.
     * @param  string|null  $user_id the ID of the user
     * @return boolean indicating whether the page is editable
     */
    public function isEditable(?string $user_id = null): bool
    {
        if ($user_id === null && User::findCurrent()) {
            $user_id = User::findCurrent()->id;
        }

        if (
            !$user_id
            || !$GLOBALS['perm']->have_studip_perm('user', $this->range_id, $user_id)
        ) {
            return false;
        }

        // Check create permission if page is new
        if ($this->isNew()) {
            $range = RangeFactory::find($this->range_id, ['sem', 'inst']);
            $permission = $range->getConfiguration()->getValue('WIKI_CREATE_PERMISSION');
            return $permission === 'all'
                || $GLOBALS['perm']->have_studip_perm($permission, $this->range_id, $user_id);
        }

        // Otherwise check write permissions
        if ($this->write_permission === 'all') {
            return true;
        }

        if (in_array($this->write_permission, ['tutor', 'dozent'])) {
            return $GLOBALS['perm']->have_studip_perm(
                $this->write_permission,
                $this->range_id,
                $user_id
            );
        } else {
            return StatusgruppeUser::exists([$this->write_permission, $user_id]);
        }
    }

    public function isDeletable(?string $user_id = null): bool
    {
        if ($user_id === null && User::findCurrent()) {
            $user_id = User::findCurrent()->id;
        }

        if (!$user_id) {
            return false;
        }

        if ($this->id == RangeConfig::get($this->range_id)->WIKI_STARTPAGE_ID) {
            if (self::countBySQL('range_id = ?', [$this->range_id]) > 1) {
                return false;
            }
        }

        $permission  = $this->write_permission;
        if ($permission === 'all') {
            $permission = 'tutor';
        }

        if (
            in_array($permission, ['tutor', 'dozent'])
            && $GLOBALS['perm']->have_studip_perm(
                $this->write_permission,
                $this->range_id,
                $user_id
            )
        ) {
            return true;
        }

        return $this->user_id === $user_id
            && $this->versions->every(function (WikiVersion $version) use ($user_id): bool {
                return $version->user_id === $user_id;
            });
    }

    /**
     * Export available data of a given user into a storage object
     * (an instance of the StoredUserData class) for that user.
     *
     * @param StoredUserData $storage object to store data into
     */
    public static function exportUserData(StoredUserData $storage)
    {
        $sorm = self::findBySQL("user_id = ?", [$storage->user_id]);
        if ($sorm) {
            $field_data = [];
            foreach ($sorm as $row) {
                $field_data[] = $row->toRawArray();
            }
            if ($field_data) {
                $storage->addTabularData(_('Wiki Einträge'), 'wiki', $field_data);
            }
        }
    }


    /**
     * Tests if a given Wikipage name (keyword) is a valid ancestor for this page.
     *
     * @param   string   $ancestor Wikipage name to be tested to be an ancestor
     * @return  boolean  true if ok, false if not
     *
     */
    public function isValidAncestor($ancestor): bool
    {
        if ($this->name === 'WikiWikiWeb' || $this->name === $ancestor) {
            return false;
        }

        $keywords = array_map(
            function ($descendant) {
                return $descendant->name;
            },
            $this->getDescendants()
        );

        return !in_array($ancestor, $keywords);
    }

    /**
     * Retrieve an array of all descending WikiPages (recursive).
     *
     * @return WikiPage[] Array of all descendant WikiPages
     *
     */
    public function getDescendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            array_push($descendants, $child, ...$child->getDescendants());
        }

        return $descendants;
    }

    /**
     * @return array
     */
    public function getOnlineUsers(): array
    {
        WikiOnlineEditingUser::purge($this);
        $this->resetRelation('onlineeditingusers');

        return $this->onlineeditingusers->map(function (WikiOnlineEditingUser $editing_user) {
            return [
                'user_id' => $editing_user->user_id,
                'username' => $editing_user->user->username,
                'fullname' => $editing_user->user->getFullName(),
                'avatar' => Avatar::getAvatar($editing_user->user_id)->getURL(Avatar::SMALL),
                'editing' => (bool) $editing_user->editing,
                'editing_request' => (bool) $editing_user->editing_request,
            ];
        });
    }
}
