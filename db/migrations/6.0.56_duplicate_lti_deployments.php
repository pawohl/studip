
<?php

final class DuplicateLtiDeployments extends Migration {

    public function description(): string
    {
        return 'Remove duplicate general deployments per tool';
    }

    public function up(): void
    {
        DBManager::get()->exec("
            UPDATE lti_resource_links JOIN lti_deployments d ON deployment_id = d.id
                SET deployment_id = (SELECT MIN(id) FROM lti_deployments WHERE tool_id = d.tool_id AND purpose = d.purpose)
                WHERE purpose = 'general'
        ");

        DBManager::get()->exec("
            DELETE FROM lti_deployments WHERE id NOT IN (SELECT deployment_id FROM lti_resource_links)
        ");
    }
}
