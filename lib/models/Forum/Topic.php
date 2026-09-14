<?php
namespace Forum;

use DBManager;
use Range;
use SimpleORMap;
use User;

/**
 * @property string $topic_id
 * @property string $category_id
 * @property string $range_id
 * @property string $name
 * @property string $description
 * @property int $position
 * @property int $mkdate
 * @property int $chdate
 *
 * @property Range $range
 * @property Category $category
 * @property Discussion[] $discussions
 * @property User[] $users
 * @property array $metadata
 */

class Topic extends SimpleORMap
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'forum_topics';

        $config['belongs_to']['category'] = [
            'class_name' => Category::class,
            'foreign_key' => 'category_id',
            'assoc_foreign_key' => 'category_id'
        ];

        $config['has_many']['discussions'] = [
            'class_name' => Discussion::class,
            'foreign_key' => 'topic_id',
            'assoc_func' => 'getDiscussions',
            'assoc_foreign_key' => 'topic_id',
        ];

        $config['additional_fields']['range'] = [
            'set' => function (Topic $topic, string $field, Range $range) {
                $topic->range_id = $range->getRangeId();
            },
            'get' => function (Topic $topic): Range {
                return get_object_by_range_id($topic->range_id);
            },
        ];

        $config['additional_fields']['users']['get'] = 'getUsers';

        $config['registered_callbacks']['after_delete'][] = 'onDelete';

        parent::configure($config);
    }

    /**
     * @return self[]
     */
    public static function getCourseTopics(string $range_id): array
    {
        return self::findBySQL(
            "range_id = :range_id
                GROUP BY CASE WHEN category_id IS NULL THEN topic_id ELSE category_id END
                ORDER BY position ASC, mkdate DESC",
            ["range_id" => $range_id]
        );
    }

    public static function getCourseTopic(string $range_id, string $topic_id): self
    {
        return self::findOneBySQL("range_id = ? AND topic_id = ?", [$range_id, $topic_id]);
    }

    public function getUsers($last_visit = null): array
    {
        $query = [
            "JOIN forum_postings USING(user_id)
            JOIN forum_discussions USING(discussion_id)
            WHERE forum_discussions.topic_id = :topic_id ",
            ['topic_id' => $this->topic_id]
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
     * @return Discussion[]
     */
    public function getDiscussions(): array
    {
        return DBManager::get()->fetchAll(
            "SELECT
                    discussions.*,
                    MAX(postings.mkdate) AS latest_post_date
                FROM forum_discussions AS discussions
                LEFT JOIN forum_postings as postings USING (discussion_id)
                WHERE discussions.topic_id = :topic_id
                GROUP BY discussions.discussion_id
                ORDER BY discussions.sticky DESC, latest_post_date DESC",
            ['topic_id' => $this->topic_id],
            Discussion::buildExisting(...)
        );
    }

    public function getMetadata(): array
    {
        $metadata = DBManager::get()->fetchOne(
            "SELECT
                        COUNT(DISTINCT `forum_discussions`.`discussion_id`) AS 'discussions_count',
                        COUNT(DISTINCT `forum_postings`.`posting_id`) AS 'postings_count',
                        COUNT(DISTINCT `forum_postings`.`user_id`) AS 'users_count',
                        MAX(`forum_postings`.`mkdate`) AS 'recent_activity',
                        (
                            SELECT
                                SUM(fpr.read_index)
                            FROM forum_discussions fd2
                            JOIN forum_posting_reads fpr
                                ON fpr.discussion_id = fd2.discussion_id
                               AND fpr.user_id = :user_id
                            WHERE fd2.topic_id = :topic_id
                        ) AS 'user_read_index',
                        COALESCE(SUM(`forum_postings`.`user_id` != :user_id), 0) AS 'others_postings_count'
                    FROM `forum_discussions`
                    LEFT JOIN `forum_postings` USING (`discussion_id`)
                    WHERE `forum_discussions`.`topic_id` = :topic_id",
            [
                'topic_id' => $this->topic_id,
                'user_id' => User::findCurrent()?->user_id
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

    public function transformData(): array
    {
        return [
            'topic_id' => $this->topic_id,
            'category_id' => $this->category_id,
            'range_id' => $this->range_id,
            'name' => $this->name,
            'description' => $this->description,
            'position' => $this->position,
            'chdate' => date('c', $this->chdate),
            'mkdate' => date('c', $this->mkdate)
        ];
    }

    public function onDelete(): void
    {
        Subscription::deleteBySQL("subject_id = ?", [$this->topic_id]);
        Discussion::deleteBySQL("topic_id = ?", [$this->topic_id]);
    }
}
