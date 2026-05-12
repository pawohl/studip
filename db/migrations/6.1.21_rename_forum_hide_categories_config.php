<?php

final class RenameForumHideCategoriesConfig extends Migration
{
    public function description()
    {
        return 'Rename FORUM_HIDE_CATEGORIES_NAVIGATION config to FORUM_HIDE_CATEGORIES';
    }

    protected function up()
    {
        DBManager::get()->exec("UPDATE config SET `field` = 'FORUM_HIDE_CATEGORIES' WHERE `field` = 'FORUM_HIDE_CATEGORIES_NAVIGATION'");
        DBManager::get()->exec("UPDATE config_values SET `field` = 'FORUM_HIDE_CATEGORIES' WHERE `field` = 'FORUM_HIDE_CATEGORIES_NAVIGATION'");
    }

    protected function down()
    {
        DBManager::get()->exec("UPDATE config SET `field` = 'FORUM_HIDE_CATEGORIES_NAVIGATION' WHERE `field` = 'FORUM_HIDE_CATEGORIES'");
        DBManager::get()->exec("UPDATE config_values SET `field` = 'FORUM_HIDE_CATEGORIES_NAVIGATION' WHERE `field` = 'FORUM_HIDE_CATEGORIES'");
    }
}
