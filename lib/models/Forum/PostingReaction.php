<?php
namespace Forum;

use SimpleORMap;
use User;

/**
 * @property int $id
 * @property string $posting_id
 * @property string $user_id
 * @property string $emoji
 * @property int $mkdate
 * @property int $chdate
 *
 * @property Posting $posting
 * @property User $user
 */

class PostingReaction extends SimpleORMap
{

    protected static function configure($config = [])
    {
        $config['db_table'] = 'forum_posting_reactions';

        $config['belongs_to']['posting'] = [
            'class_name' => Posting::class,
            'foreign_key' => 'posting_id',
            'assoc_foreign_key' => 'posting_id'
        ];

        $config['belongs_to']['user'] = [
            'class_name' => User::class,
            'foreign_key' => 'user_id',
            'assoc_foreign_key' => 'user_id'
        ];

        parent::configure($config);
    }
}
