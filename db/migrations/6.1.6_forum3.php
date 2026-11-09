<?php
class Forum3 extends Migration
{
    public function description()
    {
        return "A new  version of the forum for Stud.IP.";
    }

    public function up()
    {
        \DBManager::get()->exec("
            ALTER TABLE `forum_categories`
                CHANGE `seminar_id` `range_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                CHANGE `pos` `position` int(11) NOT NULL DEFAULT 0,
                CHANGE `entry_name` `name` varchar(255) NOT NULL,
                ADD COLUMN `description` text DEFAULT NULL AFTER `name`,
                ADD COLUMN `color` VARCHAR(7) AFTER `description`,
                ADD COLUMN `chdate` INT(11) DEFAULT NULL AFTER `position`,
                ADD COLUMN `mkdate` INT(11) DEFAULT NULL AFTER `chdate`
        ");

        \DBManager::get()->exec("
            ALTER TABLE `forum_entries`
            ADD KEY `lft` (`lft`),
            ADD KEY `rgt` (`rgt`),
            ADD KEY idx_migr (seminar_id, depth, lft, rgt)
        ");

        \DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `forum_topics` (
                `topic_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `category_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
                `range_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `name` text NOT NULL,
                `description` text DEFAULT NULL,
                `position` INT(11) NOT NULL DEFAULT 0,
                `chdate` INT(11) NOT NULL,
                `mkdate` INT(11) NOT NULL,
                PRIMARY KEY (`topic_id`)
            )
        ");

        \DBManager::get()->exec("
            INSERT INTO `forum_topics` (`topic_id`, `category_id`, `range_id`, `name`, `description`, `position`, `chdate`, `mkdate`)
            SELECT `forum_entries`.`topic_id`,
                   `forum_categories_entries`.`category_id`,
                   `forum_entries`.`seminar_id`,
                   `forum_entries`.`name`,
                   `forum_entries`.`content`,
                   IFNULL(`forum_categories_entries`.`pos`, 0),
                   `forum_entries`.`latest_chdate`,
                   `forum_entries`.`mkdate`
            FROM `forum_entries`
                LEFT JOIN `forum_categories_entries` ON (`forum_categories_entries`.`topic_id` = `forum_entries`.`topic_id`)
            WHERE `forum_entries`.`depth` = 1
            GROUP BY `forum_entries`.`topic_id`
        ");

        \DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `forum_discussions` (
                `discussion_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `topic_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `type_id` INT(11) DEFAULT NULL,
                `title` text NOT NULL,
                `sticky` tinyINT(1) NOT NULL DEFAULT 0,
                `closed_at` INT(11),
                `view_count` INT NOT NULL DEFAULT 0,
                `chdate` INT(11) NOT NULL,
                `mkdate` INT(11) NOT NULL,
                PRIMARY KEY (`discussion_id`),
                KEY `topic_id` (`topic_id`)
            )
        ");
        \DBManager::get()->exec("
            INSERT INTO `forum_discussions` (`discussion_id`, `topic_id`, `sticky`, `title`, `chdate`, `mkdate`)
            SELECT `forum_entries`.`topic_id`,
                   `parent_fe`.`topic_id`,
                   `forum_entries`.`sticky`,
                   `forum_entries`.`name`,
                   `forum_entries`.`latest_chdate`,
                   `forum_entries`.`mkdate`
            FROM `forum_entries`
                LEFT JOIN `forum_entries` AS `parent_fe` ON (`parent_fe`.depth = 1 AND `parent_fe`.`lft` < `forum_entries`.`lft` AND `parent_fe`.`rgt` > `forum_entries`.`rgt` AND `parent_fe`.`seminar_id` = `forum_entries`.`seminar_id`)
            WHERE `forum_entries`.`depth` = 2
            GROUP BY `forum_entries`.`topic_id`
        ");

        \DBManager::get()->exec("
            CREATE TABLE `forum_postings` (
                `posting_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `discussion_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `range_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `parent_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin,
                `content` TEXT NOT NULL,
                `quote` TEXT,
                `user_id` CHAR(32) NOT NULL,
                `anonymous` tinyINT(1) NOT NULL DEFAULT 0,
                `chdate` INT(11) NOT NULL,
                `mkdate` INT(11) NOT NULL,
                PRIMARY KEY (`posting_id`),
                KEY `discussion_id` (`discussion_id`)
            )
        ");

        \DBManager::get()->exec("
            INSERT INTO `forum_postings` (`posting_id`, `discussion_id`, `range_id`, `content`, `user_id`, `anonymous`, `chdate`, `mkdate`)
            SELECT `forum_entries`.`topic_id`,
                   `forum_entries`.`topic_id`,
                   `forum_entries`.`seminar_id`,
                   `forum_entries`.`content`,
                   `forum_entries`.`user_id`,
                   `forum_entries`.`anonymous`,
                   `forum_entries`.`latest_chdate`,
                   `forum_entries`.`mkdate`
            FROM `forum_entries`
                LEFT JOIN `forum_entries` AS `parent_fe` ON (`parent_fe`.depth = 1 AND `parent_fe`.`lft` < `forum_entries`.`lft` AND `parent_fe`.`rgt` > `forum_entries`.`rgt` AND `parent_fe`.`seminar_id` = `forum_entries`.`seminar_id`)
            WHERE `forum_entries`.`depth` = 2
            GROUP BY `forum_entries`.`topic_id`
        ");

        \DBManager::get()->exec("
            INSERT INTO `forum_postings` (`posting_id`, `discussion_id`, `range_id`, `content`, `user_id`, `anonymous`, `chdate`, `mkdate`)
            SELECT `forum_entries`.`topic_id`,
                   `parent_fe`.`topic_id`,
                   `forum_entries`.`seminar_id`,
                   `forum_entries`.`content`,
                   `forum_entries`.`user_id`,
                   `forum_entries`.`anonymous`,
                   `forum_entries`.`latest_chdate`,
                   `forum_entries`.`mkdate`
            FROM `forum_entries`
                LEFT JOIN `forum_entries` AS `parent_fe` ON (`parent_fe`.depth = 2 AND `parent_fe`.`lft` < `forum_entries`.`lft` AND `parent_fe`.`rgt` > `forum_entries`.`rgt` AND `parent_fe`.`seminar_id` = `forum_entries`.`seminar_id`)
            WHERE `forum_entries`.`depth` = 3
            GROUP BY `forum_entries`.`topic_id`
        ");

        \DBManager::get()->exec("
            CREATE TABLE `forum_discussion_types` (
                `type_id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` varCHAR(255) NOT NULL,
                `icon` varCHAR(50) DEFAULT NULL,
                `chdate` INT(11) NOT NULL,
                `mkdate` INT(11) NOT NULL,
                PRIMARY KEY (`type_id`)
            )
        ");

        \DBManager::get()->exec("
            CREATE TABLE `forum_posting_reactions` (
                `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
                `posting_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `user_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `emoji` varCHAR(50) DEFAULT NULL,
                `chdate` INT(11) NOT NULL,
                `mkdate` INT(11) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `posting_id` (`posting_id`),
                KEY `user_id` (`user_id`),
                KEY `emoji` (`emoji`)
            )
        ");
        \DBManager::get()->exec("
            INSERT INTO `forum_posting_reactions` (`posting_id`, `user_id`, `emoji`, `chdate`, `mkdate`)
            SELECT `topic_id`, `user_id`, 'THUMBS UP SIGN', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
            FROM `forum_likes`
        "); // THUMBS UP SIGN, THUMBS DOWN SIGN, ROCKET, GRINNING FACE, SMILING FACE WITH SUNGLASSES, CONFUSED FACE, BLACK HEART SUIT, PARTY POPPER

        \DBManager::get()->exec("
            CREATE TABLE `forum_posting_reads` (
                `discussion_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `user_id` CHAR(32) NOT NULL,
                `read_index` INT(11) NOT NULL DEFAULT 0,
                `chdate` INT(11) NOT NULL,
                PRIMARY KEY (`discussion_id`, `user_id`)
            )
        ");

        \DBManager::get()->exec("
            CREATE TABLE `forum_subscriptions` (
                `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` CHAR(32) NOT NULL,
                `range_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `subject_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `subject` ENUM('discussion', 'topic') NOT NULL DEFAULT 'discussion',
                `notification_type` ENUM('all', 'replies_only', 'none') NOT NULL DEFAULT 'all',
                `chdate` INT(11) NOT NULL,
                `mkdate` INT(11) NOT NULL,
                PRIMARY KEY (`id`)
            )
        ");

        $insertConfigSql = "INSERT IGNORE INTO `config` VALUES (:field, :value, :type, :range, :section, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), :description)";

        \DBManager::get()->execute(
            $insertConfigSql,
            [
                'field' => 'FORUM_MODERATION_PERMISSION',
                'value' => 'dozent',
                'type' => 'string',
                'range' => 'course',
                'section' => 'Forum',
                'description' => 'Status, den es braucht, um das Forum zu moderieren.'
            ]
        );

        \DBManager::get()->execute(
            $insertConfigSql,
            [
                'field' => 'FORUM_HIDE_CATEGORIES_NAVIGATION',
                'value' => 0,
                'type' => 'boolean',
                'range' => 'course',
                'section' => 'Forum',
                'description' => 'Bestimmt, ob die Kategorien-Navigation im Forum ausgeblendet wird.'
            ]
        );

        \DBManager::get()->execute(
            $insertConfigSql,
            [
                'field' => 'FORUM_TILE_LAYOUT',
                'value' => 1,
                'type' => 'boolean',
                'range' => 'user',
                'section' => 'Forum',
                'description' => 'Konfiguration der Ansicht des Forum.'
            ]
        );

        \DBManager::get()->exec("
            RENAME TABLE `forum_entries_issues` TO `forum_topics_issues`;
        ");

        $insertDiscussionTypeSql = "INSERT IGNORE INTO `forum_discussion_types` VALUES (MD5(:name), :name, :icon, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())";

        $discussionTypes = [
            [
                'name' => 'Fragen',
                'icon' => 'question'
            ],
            [
                'name' => 'Aufgaben',
                'icon' => 'guestbook'
            ],
            [
                'name' => 'Ideen',
                'icon' => 'lightbulb'
            ],
            [
                'name' => 'Regeln',
                'icon' => 'info-circle'
            ],
            [
                'name' => 'Vorstellung',
                'icon' => 'vcard'
            ],
            [
                'name' => 'Organisation',
                'icon' => 'network2'
            ]
        ];

        foreach ($discussionTypes as $discussionType) {
            \DBManager::get()->execute($insertDiscussionTypeSql, $discussionType);
        }

        \DBManager::get()->exec("
            DROP TABLE IF EXISTS
                `forum_likes`,
                `forum_visits`,
                `forum_abo_users`,
                `forum_favorites`,
                `forum_user_roles`,
                `forum_categories_entries`,
                `forum_entries`
        ");
    }

    public function down()
    {
        $removeConfigSql = "DELETE `config`, `config_values` FROM `config`
                    LEFT JOIN `config_values` USING (`field`)
                    WHERE `field` = :field";

        \DBManager::get()->execute(
            $removeConfigSql,
            ['field' => 'FORUM_TILE_LAYOUT']
        );
        \DBManager::get()->execute(
            $removeConfigSql,
            ['field' => 'FORUM_HIDE_CATEGORIES_NAVIGATION']
        );
        \DBManager::get()->execute(
            $removeConfigSql,
            ['field' => 'FORUM_MODERATION_PERMISSION']
        );
        \DBManager::get()->execute(
            $removeConfigSql,
            ['field' => 'FORUM_TILE_LAYOUT']
        );

        \DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `forum_entries` (
              `topic_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `seminar_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `area` tinyint NOT NULL DEFAULT '0',
              `mkdate` int unsigned NOT NULL,
              `latest_chdate` int unsigned DEFAULT NULL,
              `chdate` int unsigned NOT NULL,
              `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `author_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `lft` int NOT NULL,
              `rgt` int NOT NULL,
              `depth` int NOT NULL,
              `anonymous` tinyint NOT NULL DEFAULT '0',
              `closed` tinyint unsigned NOT NULL DEFAULT '0',
              `sticky` tinyint unsigned NOT NULL DEFAULT '0',
              PRIMARY KEY (`topic_id`),
              KEY `seminar_id` (`seminar_id`,`lft`),
              KEY `seminar_id_2` (`seminar_id`,`rgt`),
              KEY `user_id` (`user_id`)
            )
        ");

        \DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `forum_likes` (
              `topic_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              PRIMARY KEY (`topic_id`,`user_id`)
            )
        ");

        \DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `forum_visits` (
              `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `seminar_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `visitdate` int unsigned NOT NULL,
              `last_visitdate` int unsigned NOT NULL,
              PRIMARY KEY (`user_id`,`seminar_id`)
            )
        ");

        \DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `forum_abo_users` (
              `topic_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              PRIMARY KEY (`topic_id`,`user_id`)
            )
        ");

        \DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `forum_favorites` (
              `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `topic_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              PRIMARY KEY (`user_id`,`topic_id`)
            )
        ");

        \DBManager::get()->exec("
            DROP TABLE IF EXISTS `forum_categories`
        ");

        \DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `forum_categories` (
              `category_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `seminar_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `entry_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `pos` int NOT NULL DEFAULT '0',
              PRIMARY KEY (`category_id`),
              KEY `seminar_id` (`seminar_id`)
            )
        ");

        \DBManager::get()->exec("
            CREATE TABLE IF NOT EXISTS `forum_categories_entries` (
              `category_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `topic_id` char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
              `pos` int NOT NULL DEFAULT '0',
              PRIMARY KEY (`category_id`,`topic_id`)
            )
        ");

        \DBManager::get()->exec("
            RENAME TABLE `forum_topics_issues` TO `forum_entries_issues`;
        ");

        \DBManager::get()->exec("
            DROP TABLE IF EXISTS
                `forum_subscriptions`,
                `forum_posting_reads`,
                `forum_posting_reactions`,
                `forum_discussion_types`,
                `forum_postings`,
                `forum_discussions`,
                `forum_topics`
        ");
    }
}
