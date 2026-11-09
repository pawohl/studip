<?php
/**
 * @var string $display
 * @var Vips_SolutionsController $controller
 * @var string $course_id
 * @var string $view
 * @var array $items
 * @var bool $has_grades
 * @var string $sort
 * @var bool $desc
 * @var array $overall
 * @var array $participants
 */
?>
<table class="default">
    <caption>
        <? if ($display === 'points') : ?>
            <?= _('Punkteübersicht') ?>

            <span class="actions">
                <form action="<?= $controller->participants_overview() ?>">
                    <input type="hidden" name="cid" value="<?= htmlReady($course_id) ?>">
                    <input type="hidden" name="display" value="points">

                    <label>
                        <?= _('Anzeigefilter') ?>

                        <select name="view" class="submit-upon-select">
                            <option value="">
                                <?= _('Übungen und Klausuren') ?>
                            </option>
                            <option value="selftest" <?= $view === 'selftest' ? 'selected' : '' ?>>
                                <?= _('Selbsttests') ?>
                            </option>
                        </select>
                    </label>
                </form>
            </span>
        <? else : ?>
            <?= _('Notenübersicht') ?>
        <? endif ?>
    </caption>

    <colgroup>
        <col>

        <? if (count($items['tests']) > 0) : ?>
            <col style="border-left: 1px dotted gray;">
        <? endif ?>
        <? if (count($items['tests']) > 1) : ?>
            <col span="<?= count($items['tests']) - 1 ?>">
        <? endif ?>

        <? if (count($items['blocks']) > 0) : ?>
            <col style="border-left: 1px dotted gray;">
        <? endif ?>
        <? if (count($items['blocks']) > 1) : ?>
            <col span="<?= count($items['blocks']) - 1 ?>">
        <? endif ?>

        <? if (count($items['exams']) > 0) : ?>
            <col style="border-left: 1px dotted gray;">
        <? endif ?>
        <? if (count($items['exams']) > 1) : ?>
            <col span="<?= count($items['exams']) - 1 ?>">
        <? endif ?>

        <col style="border-left: 1px dotted gray;">
        <? if ($display == 'weighting' && $has_grades) : ?>
            <col>
        <? endif ?>
    </colgroup>

    <thead>
        <tr>
            <th><? /* participant */ ?></th>

            <? if (count($items['tests']) > 0) : ?>
                <th colspan="<?= count($items['tests']) ?>" style="text-align: center;">
                    <?= $view === 'selftest' ? _('Selbsttests') : _('Übungen') ?>
                </th>
            <? endif ?>

            <? if (count($items['blocks']) > 0) : ?>
                <th colspan="<?= count($items['blocks']) ?>" style="text-align: center;">
                    <?= _('Blöcke') ?>
                </th>
            <? endif ?>

            <? if (count($items['exams']) > 0) : ?>
                <th colspan="<?= count($items['exams']) ?>" style="text-align: center;">
                    <?= _('Klausuren') ?>
                </th>
            <? endif ?>

            <th><? /* sum */ ?></th>
            <? if ($display == 'weighting' && $has_grades) : ?>
                <th><? /* grade */ ?></th>
            <? endif ?>
        </tr>

        <tr class="sortable">
            <th class="nowrap <?= $controller->sort_class($sort === 'name', $desc) ?>">
                <a href="<?= $controller->participants_overview(['display' => $display, 'view' => $view, 'sort' => 'name', 'desc' => $sort === 'name' && !$desc]) ?>">
                    <?= _('Nachname, Vorname') ?>
                </a>
            </th>

            <? foreach ($items as $category => $list) : ?>
                <? foreach ($list as $item) : ?>
                    <th class="gradebook_header" title="<?= htmlReady($item['tooltip']) ?>">
                        <?= htmlReady($item['name']) ?>
                    </th>
                <? endforeach ?>
            <? endforeach ?>

            <th class="nowrap <?= $controller->sort_class($sort === 'sum', $desc) ?>">
                <a href="<?= $controller->participants_overview(['display' => $display, 'view' => $view, 'sort' => 'sum', 'desc' => $sort !== 'sum' || !$desc]) ?>">
                    <?= _('Summe') ?>
                </a>
            </th>

            <? if ($display == 'weighting' && $has_grades) : ?>
                <th class="nowrap <?= $controller->sort_class($sort === 'grade', $desc) ?>">
                    <a href="<?= $controller->participants_overview(['display' => $display, 'sort' => 'grade', 'desc' => $sort !== 'grade' || !$desc]) ?>">
                        <?= _('Note') ?>
                    </a>
                </th>
            <? endif ?>
        </tr>

        <? if ($display == 'points' || $overall['weighting']): ?>
            <tr class="smaller" style="background-color: #D1D1D1;">
                <td>
                    <? if ($display == 'points') : ?>
                        <?= _('Maximalpunktzahl') ?>
                    <? else : ?>
                        <?= _('Gewichtung') ?>
                    <? endif ?>
                </td>

                <? foreach ($items as $category => $list) : ?>
                    <? foreach ($list as $item) : ?>
                        <td style="text-align: right; white-space: nowrap;">
                            <? if ($display == 'points') : ?>
                                <?= sprintf('%g', $item['points']) ?>
                            <? else : ?>
                                <?= sprintf('%d %%', round($item['weighting'], 1)) ?>
                            <? endif ?>
                        </td>
                    <? endforeach ?>
                <? endforeach ?>

                <td style="text-align: right; white-space: nowrap;">
                    <? if ($display == 'points') : ?>
                        <?= sprintf('%g', $overall['points']) ?>
                    <? else : ?>
                        100 %
                    <? endif ?>
                </td>

                <? if ($display == 'weighting' && $has_grades) : ?>
                    <td></td>
                <? endif ?>
            </tr>
        <? endif ?>
    </thead>

    <tbody>
        <? /* each participant */ ?>
        <? foreach ($participants as $p) : ?>
            <tr>
                <td>
                    <?= htmlReady($p['name']) ?>
                </td>

                <? foreach ($items as $category => $list) : ?>
                    <? foreach ($list as $item) : ?>
                        <td style="text-align: right; white-space: nowrap;">
                            <? if ($display == 'points') : ?>
                                <? if (isset($p['items'][$category][$item['id']]['points'])) : ?>
                                    <?= sprintf('%.1f', $p['items'][$category][$item['id']]['points']) ?>
                                <? else : ?>
                                    &ndash;
                                <? endif ?>
                            <? else : ?>
                                <? if (isset($p['items'][$category][$item['id']]['percent'])) : ?>
                                    <?= sprintf('%.1f %%', $p['items'][$category][$item['id']]['percent']) ?>
                                <? else : ?>
                                    &ndash;
                                <? endif ?>
                            <? endif ?>
                        </td>
                    <? endforeach ?>
                <? endforeach ?>

                <td style="text-align: right; white-space: nowrap;">
                    <? if ($display == 'points') : ?>
                        <? if (isset($p['overall']['points'])): ?>
                            <?= sprintf('%.1f', $p['overall']['points']) ?>
                        <? else: ?>
                            &ndash;
                        <? endif ?>
                    <? else : ?>
                        <? if (isset($p['overall']['weighting'])): ?>
                            <?= sprintf('%.1f %%', $p['overall']['weighting']) ?>
                        <? else: ?>
                            &ndash;
                        <? endif ?>
                    <? endif ?>
                </td>

                <? if ($display == 'weighting' && $has_grades) : ?>
                    <td style="text-align: right;">
                        <?= htmlReady($p['grade']) ?>
                    </td>
                <? endif ?>
            </tr>
        <? endforeach ?>
    </tbody>
</table>
