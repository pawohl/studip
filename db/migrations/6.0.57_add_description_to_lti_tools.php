
<?php

final class AddDescriptionToLtiTools extends Migration {

    public function description(): string
    {
        return 'Add description to LTI tools';
    }

    public function up(): void
    {
        DBManager::get()->exec("ALTER TABLE `lti_tools` ADD COLUMN `description` TEXT AFTER `name`");
    }

    public function down(): void
    {
        DBManager::get()->exec("ALTER TABLE `lti_tools` DROP COLUMN `description`");
    }
}
