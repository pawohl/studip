<?php

/**
 * @see https://gitlab.studip.de/studip/studip/-/work_items/6580
 */
final class ChangeBlubberCommentsIndices extends Migration
{
    public function description()
    {
        return 'Drops indices thread_id and mkdate on table blubber_comments and adds combined index';
    }

    public function up()
    {
        $query = "ALTER TABLE `blubber_comments`
                  DROP INDEX `thread_id`,
                  DROP INDEX `mkdate`,
                  ADD INDEX `thread_id_mkdate` (`thread_id`, `mkdate`)";
        DBManager::get()->exec($query);
    }

    public function down()
    {
        $query = "ALTER TABLE `blubber_comments`
                  DROP INDEX `thread_id_mkdate`,
                  ADD INDEX `thread_id` (`thread_id`),
                  ADD INDEX `mkdate` (`mkdate`)";
        DBManager::get()->exec($query);
    }
}
