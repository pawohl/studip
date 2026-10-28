<form action="<?= $controller->link_for("questionnaire/bulkdelete", compact('range_type', 'range_id')) ?>"
      method="post">
    <?= CSRFProtection::tokenTag() ?>
    <table class="default sortable-table" data-sortlist="[[6, 1]]" id="questionnaire_overview">
        <thead>
            <tr>
                <th width="20">
                    <input type="checkbox"
                           data-proxyfor="#questionnaire_overview > tbody input[type=checkbox]"
                           data-activates="#questionnaire_overview tfoot button"
                    >
                </th>    
                <th data-sort="text"><?= _('Fragebogen') ?></th>
                <th data-sort="digit"><?= _('Start') ?></th>
                <th data-sort="digit"><?= _('Ende') ?></th>
                <th data-sort="text"><?= _('Eingebunden') ?></th>
                <th data-sort="digit"><?= _('Teilnehmende') ?></th>
                <th data-sort="digit"><?= _('Datum') ?></th>
                <th class="actions"><?= _('Aktionen') ?></th>
            </tr>
        </thead>
        <tbody>
            <? if (count($questionnaires)) : ?>
            <? foreach ($questionnaires as $questionnaire) : ?>
                <?= $this->render_partial('questionnaire/_overview_questionnaire.php', compact('questionnaire', 'range_type', 'params')) ?>
            <? endforeach ?>
            <? else : ?>
                <tr class="noquestionnaires">
                    <td colspan="8" style="text-align: center">
                        <?= _('Sie haben noch keine Fragebögen erstellt.') ?>
                    </td>
                </tr>
            <? endif ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8">
                    <?= \Studip\Button::create(_("Löschen"), "bulkdelete", ['data-confirm' => _("Wirklich löschen?")]) ?>
                </td>
            </tr>
        </tfoot>
    </table>
</form>
<?php
$actions = new ActionsWidget();
if (!empty($statusgruppen)) {
    $actions->addLink(
        _('Fragebogen erstellen'),
        $controller->url_for('questionnaire/add_to_context'),
        Icon::create('add'),
        ['data-dialog' => 'size=auto']
    );
} else {
    $actions->addLink(
        _('Fragebogen erstellen'),
        $controller->url_for('questionnaire/edit', compact('range_type', 'range_id')),
        Icon::create('add'),
        ['data-dialog' => 'size=big']
    );
}
Sidebar::Get()->addWidget($actions);
