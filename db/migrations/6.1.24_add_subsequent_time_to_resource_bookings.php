<?php


class AddSubsequentTimeToResourceBookings extends Migration
{
    use DatabaseMigrationTrait;

    public function description()
    {
        return 'Adds the subsequent_time column to the resource_bookings table.';
    }

    protected function up()
    {
        if ($this->columnExists('resource_bookings', 'subsequent_time')) {
            // This migration has propably already been executed as 6.0.41.1
            return;
        }

        $db = DBManager::get();
        $db->exec(
            "ALTER TABLE `resource_bookings`
            ADD COLUMN subsequent_time INT(4) UNSIGNED NOT NULL DEFAULT 0,
            CHANGE COLUMN preparation_time preparation_time INT(4) UNSIGNED NOT NULL DEFAULT 0"
        );

        $db->exec(
            "ALTER TABLE `resource_requests`
            ADD COLUMN subsequent_time INT(11) UNSIGNED NOT NULL DEFAULT 0,
            CHANGE COLUMN preparation_time preparation_time INT(11) UNSIGNED NOT NULL DEFAULT 0"
        );
    }

    protected function down()
    {
        $db = DBManager::get();
        $db->exec(
            "ALTER TABLE `resource_requests`
            DROP COLUMN subsequent_time,
            CHANGE COLUMN preparation_time preparation_time INT(11) SIGNED NOT NULL DEFAULT 0"
        );
        $db->exec(
            "ALTER TABLE `resource_bookings`
            DROP COLUMN subsequent_time,
            CHANGE COLUMN preparation_time preparation_time INT(4) SIGNED NOT NULL DEFAULT 0"
        );
    }
}
