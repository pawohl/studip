<?php

return new class extends Migration {

   const EMOJI_MAPPING = [
       '&#128077;' => '👍',
       '&#128078;' => '👎',
       '&#128640;' => '🚀',
       '&#128512;' => '😀',
       '&#128526;' => '😎',
       '&#128533;' => '😕',
       '&#x2665;'  => '♥',
       '&#127881;' => '🎉',
   ];

    public function description()
    {
        return 'Updates forum_posting_reactions emoji values from HTML entity codes to UTF-8 characters.';
    }

    protected function up()
    {
        DBManager::get()->exec('
            ALTER TABLE `forum_posting_reactions`
                MODIFY COLUMN `emoji` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
        ');

        foreach (self::EMOJI_MAPPING as $entity => $utf8) {
            DBManager::get()->exec("UPDATE `forum_posting_reactions` SET `emoji` = '$utf8' WHERE `emoji` = '$entity'");
            DBManager::get()->exec("UPDATE `personal_notifications` SET `avatar` = '$utf8' WHERE `avatar` = '$entity'");
        }
    }

    protected function down()
    {
        DBManager::get()->exec('
            ALTER TABLE `forum_posting_reactions`
                MODIFY COLUMN `emoji` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        ');

        foreach (self::EMOJI_MAPPING as $entity => $utf8) {
            DBManager::get()->exec("UPDATE `forum_posting_reactions` SET `emoji` = '$entity' WHERE `emoji` = '$utf8'");
            DBManager::get()->exec("UPDATE `personal_notifications` SET `avatar` = '$entity' WHERE `avatar` = '$utf8'");
        }
    }
};
