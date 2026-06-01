<?php

/**
 * @see https://gitlab.studip.de/studip/studip/-/work_items/6449
 */
final class AddDateToSemDeleteSingledateLogAction extends Migration
{
    public function description()
    {
        return 'Adjusts format of SEM_DELETE_SINGLEDATE log action';
    }

    public function up()
    {
        $query = "UPDATE `log_actions`
                  SET `info_template` = ?
                  WHERE `name` = 'SEM_DELETE_SINGLEDATE'";
        DBManager::get()->execute($query, [
            '%user löscht Einzeltermin %dbg_info in %sem(%coaffected).',
        ]);
    }

    public function down()
    {
        $query = "UPDATE `log_actions`
                  SET `info_template` = ?
                  WHERE `name` = 'SEM_DELETE_SINGLEDATE'";
        DBManager::get()->execute($query, [
            '%user löscht Einzeltermin %singledate(%affected) in %sem(%coaffected).',
        ]);
    }
}
