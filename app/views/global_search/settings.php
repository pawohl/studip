<form class="default" action="<?= $controller->url_for('globalsearch/saveconfig') ?>" method="post">
    <section>
        <label>
            <span class="required">
                <?= _('Wieviele Ergebnisse pro Kategorie sollen in der Schnellsuche angezeigt werden?') ?>
            </span>
            <input type="number" name="entries_per_type" size="5"
                   value="<?= intval(Config::get()->GLOBALSEARCH_MAX_RESULT_OF_TYPE) ?>" required>
        </label>
    </section>
    <br>
    <section>
        <table class="default sortable-table" id="globalsearch-modules">
            <caption>
                <?= _('In welcher Reihenfolge sollen Suchergebnisse erscheinen?') ?>
            </caption>
            <colgroup>
                <col width="10">
                <col>
                <col width="50">
                <col width="50">
            </colgroup>
            <thead>
                <tr>
                    <th></th>
                    <th><?= _('Kategorie') ?></th>
                    <th><?= _('Aktiv?') ?></th>
                    <th><?= _('Volltext?') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $className => $module) : ?>
                    <tr>
                        <td class="drag-handle"></td>
                        <td>
                            <label for="active[<?= htmlReady($className) ?>]">
                                <?= htmlReady($module->getName()) ?>
                            </label>
                            <input type="hidden" name="modules[<?= htmlReady($className) ?>][class]"
                                   value="<?= htmlReady($className) ?>">
                        </td>
                        <td>
                            <input type="checkbox" id="active[<?= htmlReady($className) ?>]"
                                   name="modules[<?= htmlReady($className) ?>][active]" value="1"
                                <?= !empty($config[$className]['active']) ? ' checked' : '' ?>>
                        </td>
                        <td>
                            <?php if (is_a($module, 'GlobalSearchFulltext')) : ?>
                                <input type="checkbox" name="modules[<?= htmlReady($className) ?>][fulltext]"
                                       value="1"<?= $config[$className]['fulltext'] ? ' checked' : ''?>>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </section>
    <?= CSRFProtection::tokenTag() ?>
    <footer data-dialog-button>
        <?= \Studip\Button::createAccept(_('Speichern'), 'submit')?>
    </footer>
</form>

<script language="JavaScript" type="text/javascript">
    //<!--
    jQuery('#globalsearch-modules tbody').sortable();
    //-->
</script>
