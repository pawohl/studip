<?php
/**
 * @var Admin_LtiController $controller
 * @var LtiTool[] $tools
 */
?>
<? if ($tools) : ?>
    <form action="" method="post">
        <?= CSRFProtection::tokenTag() ?>
        <table class="default">
            <caption><?= _('Aktuell konfigurierte LTI-Tools') ?></caption>

            <colgroup>
                <col style="width: 30%;">
                <col style="width: 40%;">
                <col style="width: 20%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
            </colgroup>

            <thead>
                <tr>
                    <th><?= _('Name der Anwendung') ?></th>
                    <th><?= _('URL der Anwendung') ?></th>
                    <th><?= _('Consumer-Key') ?></th>
                    <th><?= _('LTI-Version') ?></th>
                    <th><?= _('Client-ID') ?></th>
                    <th><?= _('Deployment-ID') ?></th>
                    <th><?= _('Deep Links') ?></th>
                    <th><?= _('Links') ?></th>
                    <th class="actions"><?= _('Aktionen') ?></th>
                </tr>
            </thead>

            <tbody>
                <? foreach ($tools as $tool): ?>
                    <tr>
                        <td>
                            <a href="<?= $controller->link_for('lti/tool/edit/global/' . $tool->id) ?>" data-dialog>
                                <?= htmlReady($tool->name) ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?= htmlReady($tool->launch_url) ?>" target="_blank" class="link-extern">
                                <?= htmlReady($tool->launch_url) ?>
                            </a>
                        </td>
                        <td><?= htmlReady($tool->consumer_key) ?></td>
                        <td><?= htmlReady($tool->getLtiVersionString()) ?></td>
                        <td><?= htmlReady($tool->id) ?></td>
                        <td>
                            <?
                            //Each tool should only have one deployment-ID:
                            $deployments = LtiDeployment::findBySQL(
                                "`tool_id` = :tool_id AND `purpose` = 'general'",
                                ['tool_id' => $tool->id]
                            );
                            $deployment_ids = [];
                            foreach ($deployments as $deployment)  {
                                $deployment_ids[] = $deployment->id;
                            }
                            ?>
                            <?= htmlReady(implode(', ', $deployment_ids)) ?>
                            <? if (count($deployment_ids) > 1) : ?>
                                <?= tooltipIcon(_('Dieses Tool hat mehrere Deployment-IDs zur generellen Nutzung!')) ?>
                            <? endif ?>
                        </td>
                        <td>
                            <?= htmlReady(LtiDeployment::countBySQL(
                                "`tool_id` = :tool_id AND `purpose` = 'deep_linking'",
                                ['tool_id' => $tool->id]
                            )) ?>
                        </td>
                        <td>
                            <?= \LtiResourceLink::countBySql(
                                "JOIN `lti_deployments`
                                ON `lti_deployments`.`id` = `lti_resource_links`.`deployment_id`
                                WHERE `lti_deployments`.`tool_id` = :tool_id",
                                ['tool_id' => $tool->id]
                            ) ?>
                        </td>
                        <td class="actions">
                            <a href="<?= $controller->link_for('lti/tool/edit/global/' . $tool->id) ?>" title="<?= _('LTI-Tool konfigurieren') ?>"
                               aria-label="<?= _('LTI-Tool konfigurieren') ?>" data-dialog>
                                <?= Icon::create('edit') ?>
                            </a>
                            <?= Icon::create('trash')->asInput([
                                'formaction'   => $controller->url_for('lti/tool/delete/global/' . $tool->id),
                                'title'        => _('LTI-Tool löschen'),
                                'data-confirm' => sprintf(_('Wollen Sie das LTI-Tool „%s“ wirklich löschen?'), htmlReady($tool->name)),
                                'aria-label'   => _('LTI-Tool löschen'),
                            ]) ?>
                        </td>
                    </tr>
                <? endforeach ?>
            </tbody>
        </table>
    </form>
<? else : ?>
    <?= MessageBox::info(_('Es sind keine globalen LTI-Tools konfiguriert.')) ?>
<? endif ?>
