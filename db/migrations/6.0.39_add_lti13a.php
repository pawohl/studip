<?php


class AddLti13a extends Migration
{
    public function description()
    {
        return 'Add tables and settings for the LTI 1.3A functionality.';
    }

    protected function up()
    {
        $db = DBManager::get();

        $db->exec(
            "CREATE TABLE IF NOT EXISTS keyrings (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                range_id CHAR(32) COLLATE latin1_bin NOT NULL,
                range_type VARCHAR(16) NOT NULL,
                public_key BLOB(16384) NOT NULL,
                private_key BLOB(16384) NOT NULL DEFAULT '',
                passphrase VARCHAR(512) NOT NULL DEFAULT '',
                mkdate INT(11) NOT NULL DEFAULT 0,
                chdate INT(11) NOT NULL DEFAULT 0
            )"
        );
        $db->exec("ALTER TABLE `keyrings` ADD INDEX(`range_id`, `range_type`)");

        $db->exec("RENAME TABLE `lti_tool` TO lti_tools");

        $db->exec(
            "ALTER TABLE `lti_tools`
            ADD COLUMN lti_version VARCHAR(8) NOT NULL DEFAULT '1.3a',
            ADD COLUMN range_id CHAR(32) COLLATE latin1_bin NOT NULL,
            ADD COLUMN oidc_init_url VARCHAR(255) NOT NULL DEFAULT '',
            ADD COLUMN oauth2_client_id INT NULL DEFAULT NULL,
            ADD COLUMN jwks_url VARCHAR(255) NOT NULL DEFAULT '',
            ADD COLUMN jwks_key_id VARCHAR(255) NOT NULL DEFAULT '',
            ADD COLUMN deep_linking_url VARCHAR(255) NOT NULL DEFAULT '',
            ADD COLUMN terms_of_use_url VARCHAR(255) NOT NULL DEFAULT '',
            ADD COLUMN privacy_policy_url VARCHAR(255) NOT NULL DEFAULT '',
            ADD COLUMN data_protection_notes TEXT DEFAULT NULL"
        );

        //All configured tools are globally configured LTI 1.1 tools at this point:
        $db->exec("UPDATE `lti_tools` SET `lti_version` = '1.1', `range_id` = 'global'");

        $this->migrateLtiDataTable();

        $this->addConfig();

        $this->migrateLtiToolTitle();
    }

    protected function migrateLtiDataTable()
    {
        $db = DBManager::get();
        $db->exec("RENAME TABLE `lti_data` TO lti_deployments");

        //Create LTI tool instances for the old LTI 1.0/1.1 tools
        //that have been configured directly in a course:
        $rows = $db->fetchAll(
                "SELECT `id`, `course_id`, `title`, `launch_url`, `options`
                 FROM `lti_deployments`
                 WHERE `tool_id` = 0"
        );

        $create_tool_stmt = $db->prepare(
            "INSERT INTO `lti_tools`
            (`range_id`, `name`, `launch_url`, `consumer_key`, `consumer_secret`,
             `custom_parameters`, `send_lis_person`, `lti_version`, `mkdate`, `chdate`)
         VALUES
            (:range_id, :name, :launch_url, :consumer_key, :consumer_secret,
             :custom_parameters, :send_lis_person, '1.1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())"
        );
        $update_deployment_stmt = $db->prepare(
            "UPDATE `lti_deployments`
             SET `tool_id` = :new_tool_id, `options` = :new_options
             WHERE `id` = :deployment_id"
        );

        foreach ($rows as $row) {
            $options = json_decode($row['options'] ?? '', true);
            if (!is_array($options)) {
                $options = [];
            }

            $create_tool_stmt->execute([
                'range_id'          => $row['course_id'],
                'name'              => $row['title'],
                'launch_url'        => $row['launch_url'] ?? '',
                'consumer_key'      => $options['consumer_key'] ?? '',
                'consumer_secret'   => $options['consumer_secret'] ?? '',
                'custom_parameters' => $options['custom_parameters'] ?? '',
                'send_lis_person'   => (int) ($options['send_lis_person'] ?? 0),
            ]);
            $new_tool_id = (int) $db->lastInsertId();

            unset(
                $options['launch_url'],
                $options['consumer_key'],
                $options['consumer_secret'],
                $options['custom_parameters'],
                $options['send_lis_person']
            );

            $update_deployment_stmt->execute([
                'new_tool_id'   => $new_tool_id,
                'new_options'   => json_encode($options),
                'deployment_id' => $row['id'],
            ]);
        }

        $db->exec(
            "CREATE TABLE IF NOT EXISTS `lti_tool_privacy_settings` (
                `tool_id` INT(11) NOT NULL,
                `user_id` CHAR(32) COLLATE latin1_bin NOT NULL,
                `accepted` TINYINT(1) NOT NULL DEFAULT 0,
                `allowed_optional_fields` VARCHAR(256) NOT NULL DEFAULT '',
                `mkdate` INT(11) NOT NULL DEFAULT 0,
                `chdate` INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`tool_id`, `user_id`)
            )"
        );
    }

    protected function addConfig()
    {
        $db = DBManager::get();

        $configs = [
            [
                'LTI_DATA_PROTECTION_DEFAULT_WARNING',
                'Bitte beachten Sie die Datenschutzhinweise. Wenn Sie zugestimmt haben, werden Ihre Daten weitergegeben.',
                'string',
                'global',
                'Eine Warnung zur Weitergabe personenbezogener Daten, die standardmäßig angezeigt wird, wenn Personen aus einer Veranstaltung in ein LTI-Tool wechseln.'
            ],
            [
                'LTI_DATA_PROTECTION_COURSE_WARNING',
                '',
                'string',
                'course',
                'Eine in einer Veranstaltung angepasste Warnung zur Weitergabe personenbezogener Daten, die angezeigt wird, wenn Personen aus der Veranstaltung in ein LTI-Tool wechseln.'
            ],
            [
                'LTI_ALLOW_TOOL_CONFIG_IN_COURSE',
                '1',
                'boolean',
                'global',
                'Soll es Lehrenden möglich sein, eigene LTI-Tools zu konfigurieren? Wenn nicht, können nur global konfigurierte LTI-Tools in Veranstaltungen angebunden werden.'
            ]
        ];

        $stmt = $db->prepare(
            "INSERT INTO `config`
            (`field`, `value`, `type`, `range`, `description`, `section`, `mkdate`, `chdate`)
            VALUES
            (:field, :value, :type, :range, :description, 'LTI', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())"
        );

        foreach ($configs as $c) {
            $stmt->execute([
                'field'       => $c[0],
                'value'       => $c[1],
                'type'        => $c[2],
                'range'       => $c[3],
                'description' => $c[4],
            ]);
        }
    }

    protected function migrateLtiToolTitle()
    {
        $db = DBManager::get();
        $plugin_id = $db->query("SELECT `pluginid` FROM `plugins` WHERE `pluginclassname` = 'LtiToolModule'")->fetchColumn();
        if ($plugin_id === false) {
            //The LTI core module is not registered. We cannot continue.
            return;
        }

        $fetch_stmt = $db->prepare("SELECT `range_id`, `value` FROM `config_values` where `field` = 'LTI_TOOL_TITLE'");
        $get_tool_metadata_stmt = $db->prepare(
            "SELECT `metadata` FROM `tools_activated`
             WHERE `range_type` = 'course' AND `plugin_id` = :plugin_id AND `range_id` = :range_id"
        );
        $update_tool_stmt = $db->prepare(
            "UPDATE `tools_activated`
             SET `metadata` = :metadata,
            `chdate` = UNIX_TIMESTAMP()
             WHERE
             `range_type` = 'course'
             AND `range_id` = :range_id
             AND `plugin_id` = :plugin_id"
        );

        $fetch_stmt->execute();
        while ($row = $fetch_stmt->fetch()) {
            $get_tool_metadata_stmt->execute(['plugin_id' => $plugin_id, 'range_id' => $row['range_id']]);
            $metadata_json = $get_tool_metadata_stmt->fetchColumn();
            if ($metadata_json === false) {
                //Tool not activated, therefore, nothing needs to be done.
                continue;
            }
            $metadata = [];
            if ($metadata_json) {
                //Decode the JSON to get an array that can be modified:
                $metadata = json_decode($metadata_json, true);
            }
            if (!$metadata) {
                //In case the decoding did not work or there is nothing to decode, create a new array:
                $metadata = [];
            }
            $metadata['displayname'] = $row['value'];

            $update_tool_stmt->execute([
                'range_id'  => $row['range_id'],
                'plugin_id' => $plugin_id,
                'metadata'  => json_encode($metadata)
            ]);
        }

        //At this point, all entries from LTI_TOOL_TITLE have been migrated so that that configuration
        //can be removed:
        $db->exec("DELETE FROM `config_values` where `field` = 'LTI_TOOL_TITLE'");
        $db->exec("DELETE FROM `config` WHERE `field` = 'LTI_TOOL_TITLE'");
    }

    protected function down()
    {
        //Uhhh... no!
    }
}
