<form method="post" action="<?= $controller->store_order_settings() ?>" class="default">
    <input type="hidden" name="from" value="<?= htmlReady(Request::get('from')) ?>">
    <?= CSRFProtection::tokenTag() ?>
    <label>
        <?= _('Letzte Veranstaltungsliste sortieren nach') ?>
        <select name="order_by">
            <option value="name" <? if ($order_by_field === 'name') echo 'selected'; ?>><?= _('Veranstaltungsname') ?></option>
            <option value="number" <? if ($order_by_field === 'number') echo 'selected'; ?>><?= _('Veranstaltungsnummer') ?></option>
        </select>
    </label>
    <footer data-dialog-button>
        <?= \Studip\Button::createAccept(_('Speichern')) ?>
    </footer>
</form>
