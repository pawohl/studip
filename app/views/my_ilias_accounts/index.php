<form method="post">
<? foreach($ilias_list as $ilias_index => $ilias) : ?>
    <? if (!$GLOBALS['perm']->have_perm($ilias->ilias_config['author_perm'])) continue; ?>
    <table class="default">
        <caption>
            <?= sprintf(_('Meine Lernobjekte in %s'), $ilias->getName()) ?>
            <? if (!empty($ilias->ilias_interface_config['create_objects'] && !empty($ilias->ilias_interface_config['create_category']))) : ?>
                <span class="actions">
                    <a href="<?= $controller->url_for('my_ilias_accounts/add_object/'.$ilias_index) ?>" data-dialog="size=auto" title="<?=_('Neues Lernobjekt anlegen')?>">
                        <?= Icon::create('add', Icon::ROLE_CLICKABLE, tooltip2(_('Neues Lernobjekt anlegen'))) ?>
                    </a>
                </span>
            <? endif ?>
        </caption>
        <colgroup>
            <col style="width: 5%">
            <col style="width: 55%">
            <col style="width: 20%">
            <col style="width: 20%">
        </colgroup>
        <thead>
            <th></th>
            <th><?= _('Name') ?></th>
            <th><?= _('Typ') ?></th>
            <th class="actions"><?= _('Aktionen') ?></th>
        </thead>
   <? if (count($ilias->getUserModules())) : ?>
        <? foreach ($ilias->getUserModules() as $module_id => $module) : ?>
        <tr>
            <td><?=Icon::create('learnmodule', Icon::ROLE_INFO, [
                            'title'        => $module->getModuleTypeName()
                            ])
            ?></td>
            <td><a href="<?= $controller->url_for($module->getRoute('view_tools'))?>" data-dialog=""><?=$module->getTitle()?></a></td>
            <td><?=$module->getModuleTypeName()?></td>
                <td class="actions">
                    <? $actionMenu = ActionMenu::get()->setContext($module->getTitle()) ?>
                    <? $actionMenu->addButton(
                            'view',
                            _('Info'),
                            Icon::create('info-circle', Icon::ROLE_CLICKABLE),
                            [
                                'title'        => _('Info'),
                                'formaction'   => $controller->url_for($module->getRoute('view_tools')),
                                'data-dialog'  => ''
                            ]
                    ) ?>
                    <? if ($module->isAllowed('start')) $actionMenu->addLink(
                            $controller->url_for($module->getRoute('start')),
                            _('In ILIAS anzeigen'),
                            Icon::create('play', Icon::ROLE_CLICKABLE),
                            [
                                'title'        => _('In ILIAS anzeigen'),
                                'target'       => '_blank',
                            ]
                    ) ?>
                    <? if ($module->isAllowed('edit')) $actionMenu->addLink(
                            $controller->url_for($module->getRoute('edit')),
                            _('In ILIAS bearbeiten'),
                            Icon::create('edit', Icon::ROLE_CLICKABLE),
                            [
                                'title'        => _('In ILIAS bearbeiten'),
                                'target'       => '_blank',
                            ]
                    ) ?>
                    <?= $actionMenu->render() ?>
                </td>
        </tr>
        <? endforeach ?>
   <? else : ?>
        <tr>
            <td colspan="4">
                 <?= sprintf(
                     _("Sie haben im System %s noch keine eigenen Lernmodule."),
                     htmlReady($ilias->getName())
                 )?>
            </td>
        </tr>
   <? endif ?>
   </table>
   <br>
   <br>
<? endforeach ?>
    <table class="default">
        <caption>
            <?= count($ilias_list) == 1 ? _('Mein Account') : _('Meine Accounts') ?>
        </caption>
        <colgroup>
            <col style="width: 5%">
            <col style="width: 55%">
            <col style="width: 20%">
            <col style="width: 20%">
        </colgroup>
        <thead>
            <th></th>
            <th><?= _('Login') ?></th>
            <th><?= _('System') ?></th>
            <th class="actions"><?= _('Aktionen') ?></th>
        </thead>
        <tbody>
        <? foreach($ilias_list as $ilias_index => $ilias) : ?>
        <? if ($ilias->user->getUserName()) : ?>
            <tr id="ilias-account-<?= htmlReady($ilias_index)?>">
                <td><?=Icon::create('person', Icon::ROLE_INFO, [
                                'title'        => $ilias->user->getUserName()
                                ])
                ?></td>
                <td><?=$ilias->user->getUserName()?></td>
                <td><?=$ilias->getName()?></td>
                <td class="actions">
                    <? $actionMenu = ActionMenu::get()->setContext($ilias->getName()) ?>
                    <? if ($ilias->ilias_config['allow_change_account'] && ($ilias->user->getUserType() === IliasUser::USER_TYPE_CREATED)) $actionMenu->addButton(
                            'new_account',
                            _('Account neu zuordnen'),
                            Icon::create('person', Icon::ROLE_CLICKABLE),
                            [
                                'title'        => _('Account neu zuordnen'),
                                'formaction'   => $controller->url_for('my_ilias_accounts/new_account/'.$ilias_index),
                                'data-confirm' =>
                                    sprintf(_('Möchten Sie wirklich die bestehende Zuordnung aufheben? Sie verlieren dadurch alle mit dem bestehenden Account verbundenen Inhalte und Lernfortschritte im System "%s".'),
                                    $ilias->getName()
                                ),
                                'data-dialog'  => 'size=auto;reload-on-close'
                            ]
                    ) ?>
                    <? if ($ilias->ilias_config['allow_change_account'] && ($ilias->user->getUserType() === IliasUser::USER_TYPE_ORIGINAL)) $actionMenu->addButton(
                            'change_account',
                            _('Account-Zuordnung aufheben'),
                            Icon::create('trash', Icon::ROLE_CLICKABLE),
                            [
                                'title'        => _('Account-Zuordnung aufheben'),
                                'formaction'   => $controller->url_for('my_ilias_accounts/change_account/'.$ilias_index.'/remove'),
                                'data-confirm' =>
                                    sprintf(_('Möchten Sie wirklich die bestehende Zuordnung aufheben? Sie verlieren dadurch alle mit dem bestehenden Account verbundenen Inhalte und Lernfortschritte im System "%s".'),
                                    $ilias->getName()
                                )
                            ]
                    ) ?>
                    <? if ($ilias->user->getUserType() === IliasUser::USER_TYPE_CREATED) $actionMenu->addButton(
                            'update_account',
                            _('Account aktualisieren'),
                            Icon::create('refresh', Icon::ROLE_CLICKABLE),
                            [
                                'title'        => _('Account aktualisieren'),
                                'formaction'   => $controller->url_for('my_ilias_accounts/change_account/'.$ilias_index.'/update')
                            ]
                    ) ?>
                    <?= $actionMenu->render() ?>
                </td>
            </tr>
        <? else : ?>
        <tr>
            <td colspan="4">
                 <?= sprintf(
                     _("Sie haben im System %s noch keinen Account. Loggen Sie sich zuerst in %s ein, um ILIAS-Lernobjekte in Stud.IP nutzen zu können."),
                     htmlReady($ilias->getName()),
                     "<a href=\"".$ilias->getAbsolutePath()."\">".htmlReady($ilias->getName())."</a>"
                 )?>
            </td>
        </tr>
        <? endif ?>
        <? endforeach ?>
        </tbody>
    </table>
</form>
