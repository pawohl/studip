<? use Studip\Button; ?>

<? if ($locked_info): ?>
    <?= MessageBox::info(formatLinks($locked_info)) ?>
<? endif; ?>

<form id="edit_private" action="<?= $controller->link_for('settings/details/store') ?>" method="post" class="default">
    <?= CSRFProtection::tokenTag() ?>
    <input type="hidden" name="studip_ticket" value="<?= get_ticket() ?>">
    <fieldset>
        <legend><?= _('Lebenslauf bearbeiten') ?></legend>

        <label>
            <?= _('Telefon Festnetz (privat)') ?>
            <input type="tel" name="telefon" class="size-m"
                   value="<?= htmlReady($user->privatnr) ?>"
                    <? if (!$controller->shallChange('user_info.privatnr')) echo 'disabled'; ?>>
        </label>

        <label>
            <?= _('Mobiltelefon (privat)') ?>
            <input type="tel" name="cell" class="size-m"
                   value="<?= htmlReady($user->privatcell) ?>"
                    <? if (!$controller->shallChange('user_info.privatcell')) echo 'disabled'; ?>>
        </label>

        <label for="private_address">
            <?= _('Adresse (privat)') ?>
            <input type="text" name="anschrift" id="private_address"
                   value="<?= htmlReady($user->privadr) ?>"
                    <? if (!$controller->shallChange('user_info.privadr')) echo 'disabled'; ?>>
        </label>

        <label>
            <?= _('Motto') ?>
            <input type="text" name="motto" id="motto"
                   value="<?= htmlReady($user->motto) ?>"
                    <? if (!$controller->shallChange('user_info.motto')) echo 'disabled'; ?>>
        </label>

        <label>
            <?= _('Homepage') ?>
            <input type="url" name="home" id="homepage"
                   value="<?= htmlReady($user->Home) ?>"
                    <? if (!$controller->shallChange('user_info.Home')) echo 'disabled'; ?>>
        </label>

        <label>
            <?= _('Hobbys') ?>
            <?= I18N::textarea('hobby', $user->hobby, ['class' => 'wysiwyg'] + (!$controller->shallChange('user_info.hobby') ? ['disabled' => true] : [])) ?>
        </label>

        <label id="lebenslauf">
            <?= _('Lebenslauf') ?>
            <?= I18N::textarea('lebenslauf', $user->lebenslauf, ['class' => 'wysiwyg'] + (!$controller->shallChange('user_info.lebenslauf') ? ['disabled' => true] : [])) ?>
        </label>

    <? if ($is_dozent): ?>
        <label id="schwerpunkte">
            <?= _('Schwerpunkte') ?>
            <?= I18N::textarea('schwerp', $user->schwerp, ['class' => 'wysiwyg'] + (!$controller->shallChange('user_info.schwerp') ? ['disabled' => true] : [])) ?>
        </label>

        <label id="publikationen">
            <?= _('Publikationen') ?>
            <?= I18N::textarea('publi', $user->publi, ['class' => 'wysiwyg'] + (!$controller->shallChange('user_info.publi') ? ['disabled' => true] : [])) ?>
        </label>
    <? endif; ?>

    <? if (Config::get()->getValue('OERCAMPUS_ENABLED')) : ?>
        <label>
            <?= _('Beschreibung auf dem OER Campus') ?>
            <textarea name="oercampus_description"><?= htmlReady($user->oercampus_description) ?></textarea>
        </label>
    <? endif ?>
    </fieldset>

    <? if (count($user_entries) > 0): ?>
        <fieldset>
            <legend> <?= _('Zusätzliche Datenfelder') ?></legend>
            <? foreach ($user_entries as $id => $entry): ?>
                    <? if ($entry->isEditable() && !LockRules::check($user->user_id, $entry->getId())): ?>
                        <?= $entry->getHTML('datafields', [
                            'tooltip'   => $entry->isVisible($user->perms) ? '' : _('Systemfeld (für die Person selbst nicht sichtbar)'),
                            'important' => true
                        ]) ?>
                    <? else: ?>
                        <?= htmlReady($entry->getName()) ?>

                        <div>
                            <?= formatReady($entry->getDisplayValue(false)) ?>

                            <small> <?= _('(Das Feld ist für die Bearbeitung gesperrt und kann '
                                       . 'nur durch einen Administrator verändert werden.)') ?></small>
                        </div>
                    <? endif; ?>
            <? endforeach; ?>
        </fieldset>
    <? endif; ?>
    <footer>
        <?= Button::create(_('Übernehmen'), 'store', ['title' => _('Änderungen übernehmen')]) ?>
    </footer>
</form>
