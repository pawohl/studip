<?
use Studip\Button, Studip\LinkButton;

$cal_views = [
    'day'   => _('Tagesansicht'),
    'week'  => _('Wochenansicht'),
    'month' => _('Monatsansicht')
];
$cal_deletes = [
    12 => _('12 Monate nach Ablauf'),
     6 => _('6 Monate nach Ablauf'),
     3 => _('3 Monate nach Ablauf'),
     0 => _('nie'),
];
$cal_step_days = [
     600 => _('10 Minuten'),
     900 => _('15 Minuten'),
    1800 => _('30 Minuten'),
    3600 => _('1 Stunde'),
    7200 => _('2 Stunden'),
];
$cal_step_weeks = [
    1800 => _('30 Minuten'),
    3600 => _('1 Stunde'),
    7200 => _('2 Stunden'),
];
?>

<form method="post" action="<?= $controller->link_for('settings/calendar/store') ?>" class="default"
    <?= Request::isDialog() ? 'data-dialog="reload-on-close"' : '' ?>>
    <input type="hidden" name="studip_ticket" value="<?= get_ticket() ?>">
    <?= CSRFProtection::tokenTag() ?>

    <fieldset>
        <legend>
            <?= _('Einstellungen des Kalenders') ?>
        </legend>

        <label>
            <?= _('Startansicht') ?>
            <select name="cal_view" id="cal_view">
                <? foreach ($cal_views as $index => $label): ?>
                    <option value="<?= $index ?>" <? if ($view == $index) echo 'selected'; ?>>
                        <?= $label ?>
                    </option>
                <? endforeach; ?>
            </select>
        </label>

        <label>
            <input type="radio" name="cal_type_week" value="LONG"
                <?= $type_week == 'LONG' ? 'checked' : "" ?>>
            <?= _('Alle Wochentage in der Wochenansicht anzeigen.') ?>
        </label>
        <label>
            <input type="radio" name="cal_type_week" value="SHORT"
                <?= $type_week == 'SHORT' ? 'checked' : "" ?>>
            <?= _('Nur Montag bis Freitag in der Wochenansicht anzeigen.') ?>
        </label>
    </fieldset>

    <fieldset>
        <legend>
            <?= _('Einzelterminkalender') ?>
        </legend>

        <div class="hgroup">
            <label>
                <?= _('Beginn') ?>
                <select name="cal_start" aria-label="<?= _('Startzeit der Tages- und Wochenansicht') ?>" class="size-s">
                    <? for ($i = 0; $i < 24; $i += 1): ?>
                        <option value="<?= $i ?>" <? if ($start == $i) echo 'selected'; ?>>
                            <?= sprintf('%02u:00', $i) ?>
                        </option>
                    <? endfor; ?>
                </select>
            </label>

            <label>
                <?= _('Ende') ?>
                <select name="cal_end" aria-label="<?= _('Endzeit der Tages- und Wochenansicht') ?>" class="size-s">
                    <? for ($i = 1; $i < 25; $i += 1): ?>
                        <option value="<?= $i ?>" <? if ($end == $i) echo 'selected'; ?>>
                            <?= sprintf('%02u:00', $i) ?>
                        </option>
                    <? endfor; ?>
                </select>
            </label>
        </div>

        <label>
            <?= _('Zeitintervall der Tagesansicht') ?>
            <select name="cal_step_day" for="cal_step_day">
                <? foreach ($cal_step_days as $index => $label): ?>
                    <option value="<?= $index ?>" <? if ($step_day == $index) echo 'selected'; ?>>
                        <?= $label ?>
                    </option>
                <? endforeach; ?>
            </select>
        </label>

        <label>
            <?= _('Zeitintervall der Wochenansicht') ?>
            <select name="cal_step_week" id="cal_step_week">
                <? foreach ($cal_step_weeks as $index => $label): ?>
                    <option value="<?= $index ?>" <? if ($step_week == $index) echo 'selected'; ?>>
                        <?= $label ?>
                    </option>
                <? endforeach; ?>
            </select>
        </label>

    </fieldset>

<? if (Config::get()->CALENDAR_GROUP_ENABLE): ?>
    <fieldset>
        <legend>
            <?= _('Gruppenterminkalender') ?>
        </legend>

        <label>
            <?= _("Zeitintervall der Tagesansicht") ?>
            <select name="cal_step_day_group" id="cal_step_day_group">
            <? foreach ($cal_step_days as $index => $label): ?>
                <option value="<?= $index ?>"
                         <? if (isset($step_day_group) && $step_day_group == $index) echo 'selected'; ?>
                >
                    <?= $label ?>
                </option>
            <? endforeach; ?>
            </select>
        </label>

        <label>
            <?= _('Zeitintervall der Wochenansicht') ?>
            <select name="cal_step_week_group" id="cal_step_week_group">
            <? foreach ($cal_step_weeks as $index => $label): ?>
                <option value="<?= $index ?>"
                        <? if (isset($step_week_group) && $step_week_group == $index) echo 'selected'; ?>
                >
                    <?= $label ?>
                </option>
            <? endforeach; ?>
            </select>
        </label>

    </fieldset>
<? endif ?>

    <footer data-dialog-button>
        <input type="hidden" name="view" value="calendar">
        <?= Button::createAccept(_('Übernehmen'), ['title' => _('Änderungen übernehmen')]) ?>
        <?= Button::createCancel(_('Abbrechen')) ?>
    </footer>
</form>
