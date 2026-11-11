<?php
/**
 * @var Vips_SolutionsController $controller
 * @var VipsAssignment $assignment
 * @var int $overall_uncorrected_solutions
 * @var int $assignment_id
 * @var array $first_uncorrected_solution
 * @var string $expand
 * @var string $view
 * @var array $solvers
 * @var int $overall_max_points
 * @var array $exercises
 *
 */
?>
<form action="" method="POST" id="post_form">
    <?= CSRFProtection::tokenTag() ?>
</form>

<form action="<?= $controller->link_for('vips/solutions/assignment_solutions') ?>">
    <input type="hidden" name="cid" value="<?= htmlReady($assignment->range_id) ?>">
    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">

    <table class="default dynamic_list">
        <caption>
            <?= sprintf(_('Aufgabenblatt „%s“'), htmlReady($assignment->test->title)) ?>
            <?= tooltipIcon($this->render_partial('vips/solutions/solution_color_tooltip'), false, true) ?>

            <span class="actions">
                <label>
                    <?= _('Anzeigefilter') ?>

                    <select name="view" class="submit-upon-select">
                        <? if ($assignment->type !== 'exam') : ?>
                            <option value="">
                                <?= _('Studierende mit abgegebenen Lösungen') ?>
                            </option>
                            <option value="todo" <?= $view == 'todo' ? 'selected' : '' ?>>
                                <?= _('Studierende mit unkorrigierten Lösungen') ?>
                            </option>
                            <option value="all" <?= $view == 'all' ? 'selected' : '' ?>>
                                <?= _('Alle Studierende') ?>
                            </option>
                        <? else : ?>
                            <option value="">
                                <?= _('Beendete Klausuren') ?>
                            </option>
                            <option value="working" <?= $view == 'working' ? 'selected' : '' ?>>
                                <?= _('Laufende Klausuren') ?>
                            </option>
                            <option value="pending" <?= $view == 'pending' ? 'selected' : '' ?>>
                                <?= _('Noch nicht begonnene Klausuren') ?>
                            </option>
                        <? endif ?>
                    </select>
                </label>
            </span>
        </caption>

        <thead>
            <tr>
                <th style="width: 20px;">
                    <input type="checkbox" data-proxyfor=".batch_select" data-activates=".batch_action" aria-label="<?= _('Alle Teilnehmenden auswählen') ?>">
                </th>
                <th style="width: 1em;"></th>
                <th>
                    <a href="#" class="solution-toggle">
                        <?= Icon::create('arr_1right')->asImg(['class' => 'arrow_all', 'title' => _('Aufgaben aller Teilnehmenden anzeigen')]) ?>
                        <?= Icon::create('arr_1down')->asImg(['class' => 'arrow_all', 'title' => _('Aufgaben aller Teilnehmenden verstecken'), 'style' => 'display: none;']) ?>
                        <?= _('Teilnehmende') ?>
                    </a>
                </th>
                <th style="text-align: center;">
                    <?= _('Punkte') ?>
                </th>
                <th style="text-align: center;">
                    <?= _('Prozent') ?>
                </th>
                <th style="text-align: center;">
                    <?= _('Fortschritt') ?>
                </th>
                <th style="text-align: center;">
                    <?= _('Unkorrigierte Lösungen') ?>
                </th>
                <th style="text-align: center;">
                    <?= _('Unbearbeitete Aufgaben') ?>
                </th>
                <th class="actions">
                    <?= _('Aktionen') ?>
                </th>
            </tr>
        </thead>

        <tbody>
            <? foreach ($solvers as $solver) : ?>
                <? /* extra info */ ?>
                <? $reached_points = $solver['extra_info']['points']; ?>
                <? $progress = $solver['extra_info']['progress']; ?>
                <? $uncorrected_solutions = $solver['extra_info']['uncorrected']; ?>
                <? $unanswered_exercises = $solver['extra_info']['unanswered']; ?>
                <? $uploaded_files = $solver['extra_info']['files']; ?>
                <tr id="row_<?= $solver['id'] ?>" class="solution <?= $expand == $solver['id'] ? '' : 'solution-closed' ?>">
                    <td>
                        <input class="batch_select" type="checkbox" name="user_ids[]" value="<?= $solver['user_id'] ?>" aria-label="<?= _('Zeile auswählen') ?>">
                    </td>
                    <td class="dynamic_counter" style="text-align: right;">
                    </td>

                    <td>
                        <a href="#" class="solution-toggle">
                            <?= Icon::create('arr_1right')->asImg(['class' => 'solution-open', 'title' => _('Aufgaben anzeigen')]) ?>
                            <?= Icon::create('arr_1down')->asImg(['class' => 'solution-close', 'title' => _('Aufgaben verstecken')]) ?>
                            <?= htmlReady($solver['name']) ?>
                        </a>

                        <? if ($solver['type'] == 'single') : ?>
                            <? /* running info */ ?>
                            <? if ($assignment->type == 'exam' && $view === 'working') : ?>
                                <? $ip        = $solver['running_info']['ip'] ?>
                                <? $start     = $solver['running_info']['start'] ?>
                                <? $remaining = $solver['running_info']['remaining'] ?>
                                <div class="smaller">
                                    <?= _('IP-Adresse') ?>: <?= htmlReady($ip) ?> (<?= htmlReady(gethostbyaddr($ip)) ?>)<br>
                                    <?= _('Start') ?>: <span title="<?= strftime('%A, %d.%m.%Y', $start) ?>"><?= sprintf(_('%s Uhr'), date('H:i', $start)) ?></span>
                                    <? if ($remaining > 0): ?>
                                        (<?= sprintf(ngettext('noch %d Minute', 'noch %d Minuten', $remaining), $remaining) ?>)
                                    <? endif ?>
                                </div>
                            <? endif ?>
                        <? elseif ($solver['type'] == 'group') : ?>
                            <? /* list members in group */ ?>
                            <? foreach ($solver['members'] as $member) : ?>
                                <div class="smaller" style="padding-left: 20px;">
                                    <?= htmlReady($member['name']) ?>
                                </div>
                            <? endforeach ?>
                        <? endif ?>
                    </td>

                    <? /* reached points */ ?>
                    <td style="text-align: center;">
                        <?= sprintf('%g / %g', $reached_points, $overall_max_points) ?>
                    </td>

                    <? /* percent */ ?>
                    <td style="text-align: center;">
                        <? if ($overall_max_points != 0) : ?>
                            <?= sprintf('%.1f %%', $reached_points / $overall_max_points * 100) ?>
                        <? else : ?>
                            &ndash;
                        <? endif ?>
                    </td>

                    <? /* progress */ ?>
                    <td style="text-align: center;">
                        <? if ($overall_max_points != 0) : ?>
                            <? $value = round($progress / $overall_max_points * 100) ?>
                            <progress class="assignment" value="<?= $value ?>" max="100" title="<?= $value ?> %"><?= $value ?> %</progress>
                        <? else : ?>
                            &ndash;
                        <? endif ?>
                    </td>

                    <? /* uncorrected solutions */ ?>
                    <td style="text-align: center;">
                        <? if ($uncorrected_solutions > 0) : ?>
                            <?= $uncorrected_solutions ?>
                        <? else : ?>
                            &ndash;
                        <? endif ?>
                    </td>

                    <? /* unanswered exercises */ ?>
                    <td style="text-align: center;">
                        <? if ($unanswered_exercises > 0) : ?>
                            <?= $unanswered_exercises ?>
                        <? else : ?>
                            &ndash;
                        <? endif ?>
                    </td>

                    <td class="actions">
                        <? $menu = ActionMenu::get() ?>
                        <? if ($assignment->type === 'exam' && $view !== 'pending') : ?>
                            <? if ($assignment->isRunning()) : ?>
                                <? $menu->addLink($controller->url_for('vips/solutions/edit_assignment_attempt', ['assignment_id' => $assignment_id, 'solver_id' => $solver['user_id'], 'view' => $view]),
                                       _('Abgabezeitpunkt bearbeiten'), Icon::create('edit'), ['data-dialog' => 'size=auto']
                                   ) ?>
                                <? $menu->addButton('reset', _('Teilnahme und Lösungen zurücksetzen'), Icon::create('refresh'), [
                                       'form'         => 'post_form',
                                       'formaction'   => $controller->url_for('vips/solutions/delete_solutions', ['assignment_id' => $assignment_id, 'solver_id' => $solver['user_id'], 'view' => $view]),
                                       'data-confirm' => _('Achtung: Wenn Sie die Teilnahme zurücksetzen, werden alle Lösungen der teilnehmenden Person archiviert!')
                                   ]) ?>
                            <? endif ?>
                            <? $menu->addLink($controller->url_for('vips/solutions/show_assignment_log', ['assignment_id' => $assignment_id, 'solver_id' => $solver['user_id']]),
                                   _('Abgabeprotokoll anzeigen'), Icon::create('log'), ['data-dialog' => 'size=auto']
                               ) ?>
                        <? endif ?>
                        <? if ($uploaded_files > 0): ?>
                            <? $menu->addLink($controller->url_for('vips/solutions/download_uploads', ['assignment_id' => $assignment_id, 'solver_id' => $solver['user_id']]),
                                   _('Abgegebene Dateien herunterladen'), Icon::create('download')
                               ) ?>
                        <? endif ?>
                        <? $menu->addLink($controller->url_for('vips/sheets/print_assignments', ['assignment_id' => $assignment_id, 'user_ids[]' => $solver['user_id'], 'print_files' => 1, 'print_correction' => !$view]),
                               _('Aufgabenblatt drucken'), Icon::create('print'), ['target' => '_blank']
                           ) ?>
                        <? if ($solver['type'] == 'single') : ?>
                            <? $menu->addLink(URLHelper::getURL('dispatch.php/messages/write', ['rec_uname' => $solver['username']]),
                                   sprintf(_('Nachricht an „%s“ schreiben'), $solver['name']), Icon::create('mail'), ['data-dialog' => '']
                               ) ?>
                        <? elseif ($solver['type'] == 'group') : ?>
                            <? $receivers = array_column($solver['members'], 'username') ?>
                            <? $menu->addLink(URLHelper::getURL('dispatch.php/messages/write', ['rec_uname' => $receivers]),
                                   _('Nachricht an die Gruppe schreiben'), Icon::create('mail'), ['data-dialog' => '']
                               ) ?>
                            <? if ($assignment->isFinished()) : ?>
                                <? $menu->addLink($controller->url_for('vips/solutions/edit_group_dialog', ['assignment_id' => $assignment_id, 'solver_id' => $solver['user_id'], 'view' => $view]),
                                       _('Personen aus der Gruppe entfernen'), Icon::create('community'), ['data-dialog' => 'size=auto']
                                   ) ?>
                            <? endif ?>
                        <? endif ?>
                        <?= $menu->render() ?>
                    </td>
                </tr>

                <tr class="nohover">
                    <td colspan="2"></td>
                    <td colspan="7">
                        <table class="smaller" style="width: 100%;">
                            <tr>
                                <? $col_count = 0; ?>
                                <? foreach ($exercises as $exercise) : ?>
                                    <td class="solution-col-5" style="padding: 2px;">
                                        <a href="<?= $controller->edit_solution(['assignment_id' => $assignment_id, 'exercise_id' => $exercise['id'], 'solver_id' => $solver['user_id'], 'view' => $view]) ?>">
                                            <? if (!isset($solutions[$solver['id']][$exercise['id']])) : ?>
                                                <? $class = 'solution-none'; ?>
                                            <? elseif (!$solutions[$solver['id']][$exercise['id']]['corrected']) : ?>
                                                <? $class = 'solution-uncorrected'; ?>
                                            <? elseif (!isset($solutions[$solver['id']][$exercise['id']]['grader_id'])) : ?>
                                                <? $class = 'solution-autocorrected'; ?>
                                            <? else : ?>
                                                <? $class = 'solution-corrected'; ?>
                                            <? endif ?>
                                            <span class="<?= $class ?>">
                                                <?= $exercise['position'] ?>.
                                                <?= htmlReady($exercise['title']) ?>
                                            </span>
                                        </a>
                                        <br>

                                        <? /* reached / max points */ ?>
                                        <? $max_points = $exercises[$exercise['id']]['points'] ?>
                                        <? if (isset($solutions[$solver['id']][$exercise['id']])) : ?>
                                            <? $points = $solutions[$solver['id']][$exercise['id']]['points'] ?>
                                            <? $title  = sprintf('Punkte: %g von %g', $points, $max_points) ?>
                                            <? if ($points > $max_points || $points < 0) : ?>
                                                <span style="color: red;" title="<?= htmlReady($title) ?>">
                                                    (<?= sprintf('%g/%g', $points, $max_points) ?>)
                                                </span>
                                            <? else : ?>
                                                <span title="<?= htmlReady($title) ?>">
                                                    (<?= sprintf('%g/%g', $points, $max_points) ?>)
                                                </span>
                                            <? endif ?>
                                        <? else : ?>
                                            <span class="solution-none">
                                                (<?= sprintf('%g/%g', 0, $max_points) ?>)
                                            </span>
                                        <? endif ?>
                                    </td>
                                    <? if (++$col_count % 5 == 0): ?>
                                        </tr>
                                        <tr>
                                    <? endif ?>
                                <? endforeach ?>
                            </tr>
                        </table>
                    </td>
                </tr>
            <? endforeach ?>
        </tbody>

        <? if (count($solvers)): ?>
            <tfoot>
                <tr>
                    <td colspan="9">
                        <?= Studip\Button::create(_('Drucken'), 'print', [
                                'class' => 'batch_action',
                                'formaction' => $controller->url_for('vips/sheets/print_assignments', ['print_files' => 1, 'print_correction' => !$view]),
                                'formmethod' => 'post',
                                'formtarget' => '_blank'
                            ]) ?>
                        <?= Studip\Button::create(_('Nachricht schreiben'), 'message', [
                                'class' => 'batch_action',
                                'formaction' => $controller->url_for('vips/solutions/write_message'),
                                'formmethod' => 'post',
                                'data-dialog' => ''
                            ]) ?>
                    </td>
                </tr>
            </tfoot>
        <? endif ?>
    </table>
</form>
