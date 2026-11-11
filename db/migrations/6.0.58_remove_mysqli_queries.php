<?php
final class RemoveMysqliQueries extends Migration
{
    public function description()
    {
        return 'Removes the configuration for mysqli async queries';
    }

    protected function up()
    {
        $query = "DELETE `config`, `config_values`
                  FROM `config`
                  LEFT JOIN `config_values` USING (`field`)
                  WHERE `field` = 'GLOBALSEARCH_ASYNC_QUERIES'";
        DBManager::get()->exec($query);
    }

    protected function down()
    {
        DBManager::get()->execute(
            "INSERT INTO `config` (`field`, `value`, `type`, `range`, `section`, `mkdate`, `chdate`, `description`)
             VALUES (:name, :value, :type, 'global', 'globalsearch', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), :desc)",
            [
                'name' => 'GLOBALSEARCH_ASYNC_QUERIES',
                'value' => true,
                'type' => 'boolean',
                'desc' => 'Sollen die Suchanfragen asynchron über mysqli gestellt werden? Andernfalls wird PDO verwendet.'
            ]
        );
    }
}
