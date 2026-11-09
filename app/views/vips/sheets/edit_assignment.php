<?php
/**
 * @var Vips_SheetsController $controller
 * @var int $assignment_id
 * @var VipsAssignment $assignment
 * @var VipsTest $test
 * @var array $assignment_types
 * @var VipsBlock[] $blocks
 * @var array $exam_rooms
 * @var bool $locked
 */
?>

<?= $contentbar->render() ?>

<form class="default width-1200" action="<?= $controller->store_assignment() ?>" method="POST">
    <?= CSRFProtection::tokenTag() ?>
    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
    <button hidden name="store"></button>

    <fieldset id="assignment" class="<?= htmlReady($assignment->type) ?>">
        <legend>
            <?= _('Grunddaten') ?>
        </legend>

        <? if ($locked): ?>
            <?= MessageBox::info(_('Die Klausur kann nur eingeschränkt bearbeitet werden, da bereits Lösungen abgegeben wurden.')) ?>
        <? endif ?>

        <label>
            <span class="required"><?= _('Titel') ?></span>
            <input type="text" name="assignment_name" class="character_input size-l" value="<?= htmlReady($test->title) ?>" data-secure required>
        </label>

        <label>
            <?= _('Beschreibung') ?>
            <textarea name="assignment_description" class="character_input size-l wysiwyg" data-secure><?= wysiwygReady($test->description) ?></textarea>
        </label>

        <fieldset class="undecorated">
            <legend>
                <?= _('Bearbeitungsmodus') ?>
                <?= tooltipIcon($this->render_partial('vips/sheets/assignment_type_tooltip'), false, true) ?>
            </legend>

            <? foreach ($assignment_types as $type => $entry) : ?>
                <label class="undecorated">
                    <input type="radio" class="assignment_type" name="assignment_type" value="<?= $type ?>" <?= $assignment->type == $type ? 'checked' : '' ?> data-secure>
                    <?= htmlReady($entry['name']) ?>
                </label>
            <? endforeach ?>
        </fieldset>

        <label class="formpart undecorated" id="start_date">
            <div class="label-text">
                <span class="required"><?= _('Startzeitpunkt') ?></span>
            </div>

            <input type="text" name="start_date" class="has-date-picker size-s" value="<?= date('d.m.Y', $assignment->start) ?>" data-secure required>
            <input type="text" name="start_time" class="has-time-picker size-s" value="<?= date('H:i', $assignment->start) ?>" data-secure required>
        </label>

        <? $required = $assignment->type !== 'selftest' ? 'required' : '' ?>

        <label class="formpart undecorated" id="end_date">
            <div class="label-text">
                <span class="<?= $required ?>"><?= _('Endzeitpunkt') ?></span>
            </div>

            <input type="text" name="end_date" class="has-date-picker size-s" value="<?= $assignment->isUnlimited() ? '' : date('d.m.Y', $assignment->end) ?>" data-secure <?= $required ?>>
            <input type="text" name="end_time" class="has-time-picker size-s" value="<?= $assignment->isUnlimited() ? '' : date('H:i', $assignment->end) ?>" data-secure <?= $required ?>>
        </label>

        <? $disabled = $assignment->type !== 'exam' ? 'disabled' : '' ?>

        <label id="exam_length" class="practice-hidden selftest-hidden">
            <span class="required"><?= _('Dauer in Minuten') ?></span>
            <input type="number" name="exam_length" min="0" max="99999" value="<?= htmlReady($assignment->options['duration']) ?>" <?= $disabled ?> data-secure required>
        </label>

        <section>
            <input id="options-toggle" class="options-toggle" type="checkbox" value="on" <?= $assignment_id ? '' : 'checked' ?>>
            <a class="caption" href="#" role="button" data-toggles="#options-toggle" aria-controls="options-panel" aria-expanded="<?= $assignment_id ? 'false' : 'true' ?>">
                <?= Icon::create('arr_1down')->asImg(['class' => 'toggle-open']) ?>
                <?= Icon::create('arr_1right')->asImg(['class' => 'toggle-closed']) ?>
                <?= _('Weitere Einstellungen') ?>
            </a>

            <div class="toggle-box" id="options-panel">
                <? if ($assignment->range_type === 'course'): ?>
                    <label class="formpart undecorated">
                        <div class="label-text">
                            <?= _('Block') ?>
                        </div>

                        <select name="assignment_block" style="max-width: 22.7em;" data-secure>
                            <option value="0">
                                <?= _('Keinem Block zuweisen') ?>
                            </option>
                            <? foreach ($blocks as $block): ?>
                                <option value="<?= $block->id ?>" <?= $assignment->block_id == $block->id ? 'selected' : '' ?>>
                                    <?= htmlReady($block->name) ?>
                                </option>
                            <? endforeach ?>
                        </select>
                        <?= _('oder') ?>
                        <input type="text" name="assignment_block_name" style="max-width: 22.7em;" placeholder="<?= _('Neuen Block anlegen') ?>" data-secure>
                    </label>
                <? endif ?>

                <label class="exam-hidden selftest-hidden">
                    <input type="checkbox" name="use_groups" value="1" <?= $assignment->options['use_groups'] ? 'checked' : '' ?> data-secure>
                    <?= _('Aufgaben können in Gruppen bearbeitet werden') ?>
                </label>

                <label class="practice-hidden selftest-hidden">
                    <input type="checkbox" name="self_assessment" value="1" <?= $assignment->options['self_assessment'] ? 'checked' : '' ?> data-secure>
                    <?= _('Testklausur zur Selbsteinschätzung der Teilnehmenden') ?>
                    <?= tooltipIcon(_('Teilnehmende können beliebig oft neu starten, Ergebnisse können direkt nach Ablauf der Bearbeitungszeit zugänglich gemacht werden.')) ?>
                </label>

                <label class="practice-hidden selftest-hidden">
                    <input type="checkbox" name="shuffle_exercises" value="1" <?= $assignment->options['shuffle_exercises'] ? 'checked' : '' ?> data-secure>
                    <?= _('Zufällige Reihenfolge der Aufgaben bei Anzeige der Klausur') ?>
                </label>

                <label class="practice-hidden selftest-hidden">
                    <input type="checkbox" name="shuffle_answers" value="1" <?= $assignment->options['shuffle_answers'] !== 0 ? 'checked' : '' ?> data-secure>
                    <?= _('Zufällige Reihenfolge der Antworten in Multiple- und Single-Choice-Aufgaben') ?>
                </label>

                <label class="exam-hidden practice-hidden">
                    <input type="checkbox" name="resets" value="1" <?= $assignment->options['resets'] !== 0 ? 'checked' : '' ?> data-secure>
                    <?= _('Teilnehmende dürfen ihre Lösungen zurücksetzen und den Test neu starten') ?>
                </label>

                <label class="exam-hidden practice-hidden">
                    <input type="checkbox" value="1" <?= $assignment->options['max_tries'] !== 0 ? 'checked' : '' ?> data-activates=".max_tries" data-secure>
                    <?= _('Anzeige der Musterlösung nach eingesteller Anzahl von Fehlversuchen') ?>
                </label>

                <label class="exam-hidden practice-hidden">
                    <?= _('Anzahl der Lösungsversuche pro Aufgabe') ?>
                    <input type="number" name="max_tries" class="max_tries" min="1" value="<?= $assignment->options['max_tries'] ?: 3 ?>" data-secure>
                </label>

                <label>
                    <?= _('Falsche Antworten in Multiple- und Single-Choice-Aufgaben') ?>

                    <select name="evaluation_mode" data-secure>
                        <option value="0">
                            <?= _('&hellip; geben keinen Punktabzug') ?>
                        </option>
                        <option value="1" <?= $assignment->options['evaluation_mode'] == VipsAssignment::SCORING_NEGATIVE_POINTS ? 'selected' : '' ?>>
                            <?= _('… geben Punktabzug (Gesamtpunktzahl Aufgabe mind. 0)') ?>
                        </option>
                        <option value="2" <?= $assignment->options['evaluation_mode'] == VipsAssignment::SCORING_ALL_OR_NOTHING ? 'selected' : '' ?>>
                            <?= _('… führen zur Bewertung der Aufgabe mit 0 Punkten') ?>
                        </option>
                    </select>
                </label>

                <label>
                    <?= _('Notizen (für Teilnehmende unsichtbar)') ?>
                    <textarea name="assignment_notes" class="character_input" data-secure><?= htmlReady($assignment->options['notes']) ?></textarea>
                </label>

                <label class="practice-hidden selftest-hidden">
                    <?= _('Zugangscode zur Klausur (optional)') ?>
                    <input type="text" name="access_code" value="<?= htmlReady($assignment->options['access_code']) ?>" data-secure>
                </label>

                <label class="practice-hidden selftest-hidden">
                    <?= _('Zugriff auf Prüfungsräume oder IP-Bereiche beschränken (optional)') ?>
                    <?= tooltipIcon($this->render_partial('vips/sheets/ip_range_tooltip'), false, true) ?>
                    <input type="text" name="ip_range" class="validate_ip_range" value="<?= htmlReady($assignment->options['ip_range']) ?>" data-secure>
                </label>

                <? if ($exam_rooms): ?>
                    <div class="practice-hidden selftest-hidden smaller">
                        <?= _('Raum hinzufügen:') ?>
                        <? foreach (array_keys($exam_rooms) as $room_name): ?>
                            <a href="#" class="add_ip_range" data-value="#<?= htmlReady($room_name) ?>">
                                <?= htmlReady($room_name) ?>
                            </a>
                        <? endforeach ?>
                    </div>
                <? endif?>
            </div>

            <div class="practice-hidden exam-hidden">
                <input id="feedback-toggle" class="options-toggle" type="checkbox" value="on">
                <a class="caption" href="#" role="button" data-toggles="#feedback-toggle" aria-controls="feedback-panel" aria-expanded="false">
                    <?= Icon::create('arr_1down')->asImg(['class' => 'toggle-open']) ?>
                    <?= Icon::create('arr_1right')->asImg(['class' => 'toggle-closed']) ?>
                    <?= _('Automatisches Feedback') ?>
                </a>

                <div class="toggle-box" id="feedback-panel">
                    <table class="default description fixed">
                        <thead>
                            <tr>
                                <th style="width: 16%;">
                                    <?= _('Erforderliche Punkte') ?>
                                </th>
                                <th style="width: 76%;">
                                    <?= _('Kommentar zur Bewertung') ?>
                                </th>
                                <th class="actions" style="width: 8%;">
                                    <?= _('Löschen') ?>
                                </th>
                            </tr>
                        </thead>

                        <tbody class="dynamic_list" style="vertical-align: top;">
                            <? if (isset($assignment->options['feedback'])): ?>
                                <? foreach ($assignment->options['feedback'] as $threshold => $feedback): ?>
                                    <tr class="dynamic_row">
                                        <td>
                                            <input type="number" name="threshold[]" min="0" max="100" value="<?= htmlReady($threshold) ?>" data-secure> %
                                        </td>
                                        <td>
                                            <textarea name="feedback[]" class="character_input size-l wysiwyg" data-secure><?= wysiwygReady($feedback) ?></textarea>
                                        </td>
                                        <td class="actions">
                                            <?= Icon::create('trash')->asInput(['class' => 'delete_dynamic_row', 'title' => _('Eintrag löschen')]) ?>
                                        </td>
                                    </tr>
                                <? endforeach ?>
                            <? endif ?>

                            <tr class="dynamic_row template">
                                <td>
                                    <input type="number" name="threshold[]" min="0" max="100" data-secure> %
                                </td>
                                <td>
                                    <textarea name="feedback[]" class="character_input size-l wysiwyg-hidden" data-secure></textarea>
                                </td>
                                <td class="actions">
                                    <?= Icon::create('trash')->asInput(['class' => 'delete_dynamic_row', 'title' => _('Eintrag löschen')]) ?>
                                </td>
                            </tr>

                            <tr>
                                <th colspan="3">
                                    <?= Studip\Button::create(_('Eintrag hinzufügen'), 'add_feedback', ['class' => 'add_dynamic_row']) ?>
                                </th>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </fieldset>

    <table class="default" id="exercises">
        <? if (count($test->exercise_refs)): ?>
            <thead>
                <tr>
                    <th style="padding-left: 2ex;">
                        <input type="checkbox" data-proxyfor=".batch_select" data-activates=".batch_action" aria-label="<?= _('Alle Aufgaben auswählen') ?>">
                    </th>
                    <th></th>
                    <th style="width: 60%;">
                        <?= _('Aufgaben') ?>
                    </th>
                    <th style="width: 22%;">
                        <?= _('Aufgabentyp') ?>
                    </th>
                    <th style="width: 5em;">
                        <span class="required"><?= _('Punkte') ?></span>
                    </th>
                    <th class="actions">
                        <?= _('Aktionen') ?>
                    </th>
                </tr>
            </thead>

            <tbody id="list" class="dynamic_list" data-assignment="<?= $assignment_id ?>" role="list">
                <?= $this->render_partial('vips/sheets/list_exercises') ?>
            </tbody>
        <? endif ?>

        <tfoot>
            <tr>
                <td colspan="4">
                    <?= Studip\Button::createAccept(_('Speichern'), 'store') ?>
                    <? if ($assignment_id && !$locked): ?>
                        <?= Studip\LinkButton::create(_('Neue Aufgabe erstellen'),
                                $controller->url_for('vips/sheets/add_exercise_dialog', compact('assignment_id')),
                                ['data-dialog' => 'size=auto']) ?>
                    <? endif ?>
                    <? if (count($test->exercise_refs)): ?>
                        <?= Studip\Button::create(_('Kopieren'), 'copy_exercises', [
                                'class' => 'batch_action',
                                'formaction' => $controller->url_for('vips/sheets/copy_exercises_dialog'),
                                'data-dialog' => 'size=auto'
                            ]) ?>
                        <? if (!$locked): ?>
                            <?= Studip\Button::create(_('Verschieben'), 'move_exercises', [
                                    'class' => 'batch_action',
                                    'formaction' => $controller->url_for('vips/sheets/move_exercises_dialog'),
                                    'data-dialog' => 'size=auto'
                                ]) ?>
                            <?= Studip\Button::create(_('Löschen'), 'delete_exercises', [
                                    'class' => 'batch_action',
                                    'formaction' => $controller->url_for('vips/sheets/delete_exercises'),
                                    'data-confirm' => _('Wollen Sie wirklich die ausgewählten Aufgaben löschen?')
                                ]) ?>
                        <? endif ?>
                    <? endif ?>
                </td>
                <td colspan="2" style="padding-left: 0;">
                    <? if (count($test->exercise_refs) > 0): ?>
                        <div class="points">
                            <?= sprintf('%g', $test->getTotalPoints()) ?>
                        </div>
                    <? endif ?>
                </td>
            </tr>
        </tfoot>
    </table>
</form>
