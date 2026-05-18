<?php

final class UpdateForumReactionEmojiValuesToHtmlCodes extends Migration
{

    public function description()
    {
        return 'Updates forum_posting_reactions emoji values from Unicode text names to HTML entity codes.';
    }

    protected function up()
    {
        $db = DBManager::get();

        $db->exec("UPDATE forum_posting_reactions SET `emoji` = '&#128078;' WHERE `emoji` = 'THUMBS DOWN SIGN'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = '&#128640;' WHERE `emoji` = 'ROCKET'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = '&#128512;' WHERE `emoji` = 'GRINNING FACE'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = '&#128526;' WHERE `emoji` = 'SMILING FACE WITH SUNGLASSES'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = '&#128533;' WHERE `emoji` = 'CONFUSED FACE'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = '&#x2665;' WHERE `emoji` = 'BLACK HEART SUIT'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = '&#127881;' WHERE `emoji` = 'PARTY POPPER'");
    }

    protected function down()
    {
        $db = DBManager::get();
        
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = 'THUMBS DOWN SIGN' WHERE `emoji` = '&#128078;'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = 'ROCKET' WHERE `emoji` = '&#128640;'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = 'GRINNING FACE' WHERE `emoji` = '&#128512;'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = 'SMILING FACE WITH SUNGLASSES' WHERE `emoji` = '&#128526;'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = 'CONFUSED FACE' WHERE `emoji` = '&#128533;'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = 'BLACK HEART SUIT' WHERE `emoji` = '&#x2665;'");
        $db->exec("UPDATE forum_posting_reactions SET `emoji` = 'PARTY POPPER' WHERE `emoji` = '&#127881;'");
    }
}
