<?php
$start_pages = [
    '' => _('keine'),
     1 => _('Meine Veranstaltungen'),
     3 => _('Mein Stundenplan'),
     5 => _('Mein Kalender'),
     4 => _('Mein Adressbuch'),
     6 => _('Mein globaler Blubberstream'),
     7 => _('Mein Arbeitsplatz'),
];
?>

<form method="post" action="<?= $controller->url_for('settings/general/store') ?>" class="default">
    <?= CSRFProtection::tokenTag() ?>
    <input type="hidden" name="studip_ticket" value="<?= get_ticket() ?>">

    <fieldset>
        <legend><?= _('Allgemeine Einstellungen') ?></legend>

        <label>
            <?= _('Sprache') ?>
            <select name="forced_language" class="size-s">
                <? foreach ($GLOBALS['INSTALLED_LANGUAGES'] as $key => $language): ?>
                    <option value="<?= $key ?>"
                        <? if ($user_language == $key) echo 'selected'; ?>>
                        <?= $language['name'] ?>
                    </option>
                <? endforeach; ?>
            </select>
        </label>

    <? if (!$GLOBALS['perm']->have_perm('root')): ?>
        <label>
            <?= _('Persönliche Startseite') ?>
            <?= tooltipHtmlIcon(_('Sie können hier einstellen, welche Seite standardmäßig nach dem Einloggen '
                .'angezeigt wird. Wenn Sie zum Beispiel regelmäßig die Seite &raquo;Meine '
                .'Veranstaltungen&laquo; nach dem Login aufrufen, so können Sie dies hier '
                .'direkt einstellen.')) ?>
            <select name="personal_startpage">
            <? foreach ($start_pages as $index => $label): ?>
                <option value="<?= $index ?>" <? if ($config->PERSONAL_STARTPAGE == $index) echo 'selected'; ?>>
                    <?= htmlReady($label) ?>
                </option>
            <? endforeach; ?>
            </select>
        </label>
    <? endif ?>

        <label>
            <input type="checkbox"
                   name="showsem_enable"
                   value="1"
                <? if ($config->SHOWSEM_ENABLE) echo 'checked'; ?>>
            <?= _('Semesteranzeige in der Überschrift und auf &raquo;Meine Veranstaltungen&laquo;');?>
            <?= tooltipHtmlIcon(_('Mit dieser Einstellung können Sie in der Überschrift einer Veranstaltung und '
                .'auf der Seite &raquo;Meine Veranstaltungen&laquo; die Einblendung des Start- und Endsemesters '
                .'hinter jeder Veranstaltung aktivieren.')) ?>
        </label>

    <? if (Config::get()->TOURS_ENABLE) : ?>
        <label>
            <input type="checkbox" name="tour_autostart_disable"
                   aria-describedby="tour_autostart_disable_description" value="1"
                <? if ($config->TOUR_AUTOSTART_DISABLE) echo 'checked'; ?>>
            <?= _('Autostart von Touren verhindern');?>
            <?= tooltipIcon(_('Mit dieser Einstellung können Sie verhindern, dass Touren zu einzelnen '
                .'Stud.IP-Seiten automatisch starten, wenn Sie die Seite aufrufen. Die Touren '
                .'können weiterhin über die Hilfe gestartet werden.')) ?>
        </label>
    <? endif ?>
    </fieldset>

    <fieldset>
        <legend><?= _('Benachrichtigungen') ?></legend>

    <? if (Config::get()->getValue('PERSONAL_NOTIFICATIONS_ACTIVATED')): ?>
        <label>
            <input type="checkbox" name="personal_notifications_activated"
                   aria-describedby="personal_notifications_activated_description" value="1"
                <? if (PersonalNotifications::isActivated($user->user_id)) echo 'checked'; ?>>
            <?= _('Benachrichtigungen über Neuigkeiten') ?>
            <?= tooltipIcon(_('Hiermit wird in der Kopfzeile dargestellt, wenn es Benachrichtigungen für '
                .'Sie gibt. Die Benachrichtigungen werden auch angezeigt, wenn Sie nicht die '
                .'Seite neuladen.')) ?>
        </label>
    <? endif; ?>

        <label>
            <?= _('Platzierung von Systembenachrichtigungen im Browserfenster') ?>
            <?= tooltipIcon(_('Sie können entscheiden, an welcher Stelle Ihres Browserfensters ' .
                'Systembenachrichtigungen erscheinen sollen: mittig am oberen Rand oder rechts unten.')) ?>
            <select name="system_notifications_placement"
                   aria-describedby="system_notifications_notifications_placement_description">
                <option value="topcenter"<?= $notifications_placement === 'topcenter' ? ' selected' : '' ?>>
                    <?= _('zentriert am oberen Rand') ?>
                </option>
                <option value="bottomright"<?= $notifications_placement === 'bottomright' ? ' selected' : '' ?>>
                    <?= _('am rechten unteren Rand') ?>
                </option>
            </select>
        </label>
    </fieldset>

    <? if ($show_room_management_autor_config) : ?>
        <fieldset>
            <legend><?= _('Raumverwaltung') ?></legend>
            <label>
                <input type="checkbox" name="resources_confirm_plan_drag_and_drop"
                       value="1"
                       <?= $config->RESOURCES_CONFIRM_PLAN_DRAG_AND_DROP ? 'checked' : '' ?>>
                <?= _('Nach dem Verschieben einer Buchung per Drag & Drop im Belegungsplan eine Sicherheitsabfrage anzeigen') ?>
                <?= tooltipIcon(
                    _('Wenn diese Einstellung aktiviert ist, wird die Buchung erst dann verschoben, wenn die Sicherheitsabfrage mit „Ja“ beantwortet wurde.')
                ) ?>
            </label>
        </fieldset>
    <? endif ?>

    <footer>
        <?= \Studip\Button::create(_("Speichern")) ?>
    </footer>
</form>
