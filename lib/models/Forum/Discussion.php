<?php
namespace Forum;

use User;
use DBManager;
use SimpleORMap;
use Forum\DTO\Member as MemberDTO;
use Forum\DTO\Tag as TagDTO;
use Forum\Service\DiscussionNotification;

/**
 * @property string $discussion_id
 * @property string $topic_id
 * @property int $type_id
 * @property string $title
 * @property bool $sticky
 * @property int $closed_at
 * @property int $view_count
 * @property int $mkdate
 * @property int $chdate
 *
 * @property Topic $topic
 * @property User $user
 * @property DiscussionType $discussion_type
 * @property Posting[] $postings
 * @property Subscription[] $subscribers
 * @property User[] $users
 * @property MemberDTO[] $members
 * @property TagDTO[] $tags
 * @property Category $category
 */
class Discussion extends SimpleORMap
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'forum_discussions';

        $config['belongs_to']['topic'] = [
            'class_name' => Topic::class,
            'foreign_key' => 'topic_id',
            'assoc_foreign_key' => 'topic_id'
        ];

        $config['belongs_to']['discussion_type'] = [
            'class_name' => DiscussionType::class,
            'foreign_key' => 'type_id',
            'assoc_foreign_key' => 'type_id'
        ];

        $config['has_many']['postings'] = [
            'class_name' => Posting::class,
            'foreign_key' => 'discussion_id',
            'assoc_foreign_key' => 'discussion_id',
            'order_by' => 'ORDER BY mkdate',
        ];

        $config['has_many']['subscribers'] = [
            'class_name' => Subscription::class,
            'foreign_key' => 'discussion_id',
            'assoc_foreign_key' => 'discussion_id'
        ];

        $config['belongs_to']['user'] = [
            'class_name' => User::class,
            'foreign_key' => 'user_id',
            'assoc_foreign_key' => 'user_id'
        ];

        $config['additional_fields']['range_id']['get'] = 'getRangeId';
        $config['additional_fields']['category']['get'] = 'getCategory';
        $config['additional_fields']['tags']['get'] = 'getTags';
        $config['additional_fields']['users']['get'] = 'getUsers';
        $config['additional_fields']['members']['get'] = 'getMembers';

        $config['registered_callbacks']['after_create'][] = 'onCreate';
        $config['registered_callbacks']['after_delete'][] = 'onDelete';

        parent::configure($config);
    }

    /**
     * @param string $range_id course_id or institute_id.
     * @param array $filter Optional: filters to apply.
     *
     * @return self[]
     */
    public static function getCourseDiscussions(string $range_id, array $filter = []): array
    {
        $query = [
            "SELECT
                    discussions.*,
                    MAX(postings.mkdate) AS latest_post_date
                FROM forum_discussions AS discussions
                JOIN forum_postings as postings USING (discussion_id)
                JOIN forum_topics AS topics USING (topic_id)
                LEFT JOIN tags_relations ON (tags_relations.range_id = discussions.discussion_id AND range_type = 'forum')
                WHERE topics.range_id = :range_id",
            ['range_id' => $range_id]
        ];

        if (isset($filter['last_visit'])) {
            $query[0] .= " AND postings.mkdate > :last_visit";
            $query[1]["last_visit"] = $filter['last_visit'];
        }

        if (isset($filter['keyword'])) {
            $keyword = $filter['keyword'];
            $query[0] .= " AND (discussions.title LIKE :keyword OR postings.content LIKE :keyword)";
            $query[1]["keyword"] = "%$keyword%";
        }

        if (isset($filter['begin'])) {
            $query[0] .= " AND postings.mkdate >= :begin";
            $query[1]['begin'] = $filter['begin'];
        }

        if (isset($filter['end'])) {
            $query[0] .= " AND postings.mkdate <= :end";
            $query[1]['end'] = $filter['end'];
        }

        if (isset($filter['topic_ids'])) {
            $query[0] .= " AND discussions.topic_id IN (:topic_ids)";
            $query[1]['topic_ids'] = $filter['topic_ids'];
        }

        if (isset($filter['type_ids'])) {
            $query[0] .= " AND discussions.type_id IN (:type_ids)";
            $query[1]['type_ids'] = $filter['type_ids'];
        }

        if (isset($filter['tag_ids'])) {
            $query[0] .= " AND tags_relations.tag_id IN (:tag_ids)";
            $query[1]['tag_ids'] = $filter['tag_ids'];
        }

        if (isset($filter['user_ids'])) {
            $query[0] .= " AND postings.user_id IN (:user_ids)";
            $query[1]['user_ids'] = $filter['user_ids'];
        }

        if (isset($filter['status'])) {
            $query[0] .= match ($filter['status']) {
                2 => " AND discussions.closed_at IS NULL", // opens
                3 => " AND discussions.closed_at IS NOT NULL", // closed
                default => ""
            };
        }

        return \DBManager::get()->fetchAll(
            $query[0]." GROUP BY discussions.discussion_id ORDER BY latest_post_date DESC",
            $query[1],
            self::buildExisting(...)
        );
    }

    /**
     * @return TagDTO[]
     */
    public function getTags(): array
    {
        return DBManager::get()->fetchAll(
            "SELECT DISTINCT `tags_relations`.`tag_id`, `tags`.`name` FROM `tags`
                        LEFT JOIN  `tags_relations` ON `tags`.`id` = `tags_relations`.`tag_id`
                        WHERE `tags_relations`.`range_id` = :discussion_id AND `tags`.`active` = TRUE
                        ORDER BY `tags`.`mkdate` DESC",
            ['discussion_id' => $this->discussion_id],
            function ($tag) {
                return TagDTO::fromArray([
                    'id' => $tag['tag_id'],
                    'name' => $tag['name']
                ]);
            }
        );
    }

    public function getCategory(): ?Category
    {
        return Category::findOneBySQL("JOIN forum_topics USING (category_id) WHERE forum_topics.topic_id = :topic_id", ['topic_id' => $this->topic_id]);
    }

    public function getRangeId(): string
    {
        return $this->topic->range_id;
    }

    public function getUsers($last_visit = null): array
    {
        $query = [
            "JOIN forum_postings USING(user_id)
            WHERE forum_postings.discussion_id = :discussion_id
            AND forum_postings.anonymous = FALSE",
            ['discussion_id' => $this->discussion_id]
        ];

        if ($last_visit) {
            $query[0] .= " AND forum_postings.mkdate > :last_visit";
            $query[1]["last_visit"] = $last_visit;
        }

        $users = User::findBySQL($query[0]." ORDER BY forum_postings.mkdate DESC", $query[1]);

        $unique_users = [];
        foreach ($users as $user) {
            $unique_users[$user->user_id] = $user;
        }

        return array_values($unique_users);
    }

    /**
     * @return MemberDTO[]
     */
    public function getMembers($last_visit = null): array
    {
        $users = $this->getUsers($last_visit);

        $members = [];
        foreach ($users as $user) {
            $members[] = MemberDTO::fromUser($user, $this->range_id);
        }

        return $members;
    }

    public function transformData(): array
    {
        return [
            'discussion_id' => $this->discussion_id,
            'topic_id' => $this->topic_id,
            'type_id' => $this->type_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'sticky' => (int) $this->sticky,
            'closed_at' => $this->closed_at ? date('c', $this->closed_at) : '',
            'view_count' => (int) $this->view_count,
            'chdate' => date('c', $this->chdate),
            'mkdate' => date('c', $this->mkdate)
        ];
    }

    public function getMetadata(int $last_visit = 0): array
    {
        $user_id = User::findCurrent()?->user_id;

        if (!$last_visit) {
            $plugin_id = \PluginEngine::getPlugin(\CoreForum::class)->getPluginId();
            $last_visit = object_get_visit($this->topic->range_id, $plugin_id, 'last', '', $user_id);
        }

        $metadata = DBManager::get()->fetchOne(
            "SELECT
                            COUNT(`posting_id`) 'postings_count',
                            MAX(`mkdate`) 'recent_activity',
                            (
                                SELECT `read_index`
                                FROM `forum_posting_reads`
                                WHERE `discussion_id` = :discussion_id AND `user_id` = :user_id
                            ) 'user_read_index',
                            SUM(`mkdate` > :last_visit AND `user_id` != :user_id) AS 'recent_postings_count',
                            SUM(`user_id` != :user_id) AS 'others_postings_count'
                        FROM `forum_postings`
                        WHERE `discussion_id` = :discussion_id",
            [
                'discussion_id' => $this->discussion_id,
                'user_id' => $user_id,
                'last_visit' => $last_visit
            ]
        );

        return [
            ...$metadata,
            'unread_postings_count' => max(
                0,
                $metadata['others_postings_count'] - (int) $metadata['user_read_index']
            )
        ];
    }

    public function onCreate(): void
    {
        $discussionNotification = new DiscussionNotification($this);
        $discussionNotification->notifySubscribers();
    }

    public function onDelete(): void
    {
        Subscription::deleteBySQL("subject_id = ?", [$this->discussion_id]);
        Posting::deleteBySQL("discussion_id = ?", [$this->discussion_id]);
        PostingRead::deleteBySQL("discussion_id = ?", [$this->discussion_id]);
    }
}
