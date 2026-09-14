<?php
/**
 * @see https://gitlab.studip.de/studip/studip/-/issues/6817
 */
final class AddMoreForumIndices extends Migration
{
    public function description()
    {
        return 'Adds missing indices to forum tables';
    }

    protected function up()
    {
        $query = "ALTER TABLE `forum_topics`
                  ADD INDEX `category_id` (`category_id`)";
        DBManager::get()->exec($query);

        $query = "ALTER TABLE `forum_postings`
                  ADD INDEX `user_id` (`user_id`),
                  ADD INDEX `range_id` (`range_id`)";
        DBManager::get()->exec($query);
    }

    protected function down()
    {
        $query = "ALTER TABLE `forum_postings`
                  DROP INDEX `range_id`,
                  DROP INDEX `user_id`";
        DBManager::get()->exec($query);

        $query = "ALTER TABLE `forum_topics`
                  DROP INDEX `category_id`";
        DBManager::get()->exec($query);
    }
}
