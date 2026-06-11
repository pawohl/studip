<?php
return new class extends Migration {

    public function description()
    {
        return 'Adds column and configuration option for actual number of participants';
    }

    protected function up()
    {
        $db = DBManager::get();
        $db->exec("INSERT IGNORE INTO `config`
             (`field`, `value`, `type`, `range`, `section`, `mkdate`, `chdate`, `description`)
             VALUES
             (
              'ENABLE_NUMBER_OF_PARTICIPANTS',
              0,
              'boolean',
              'global',
              'global',
              UNIX_TIMESTAMP(),
              UNIX_TIMESTAMP(),
              'Schaltet die Möglichkeit zum Erfassen der tatsächlichen Teilnehmendenzahl pro Termin ein.'
             )"
        );
        $db->exec("ALTER TABLE `termine` ADD `number_of_participants` SMALLINT NULL DEFAULT NULL");
    }

    protected function down()
    {
        $db = DBManager::get();
        $db->exec("DELETE `config`, `config_values`
                   FROM `config` LEFT JOIN `config_values` USING (`field`)
                   WHERE `field` = 'ENABLE_NUMBER_OF_PARTICIPANTS'");
        $db->exec("ALTER TABLE `termine` DROP `number_of_participants`");

    }
};
