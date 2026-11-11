<?php
/**
 * @var Vips_SolutionsController $controller
 * @var string $sort
 * @var bool $desc
 * @var VipsBlock[] $blocks
 * @var float $sum_reached_points
 * @var float $sum_max_points
 */
?>
<table class="default collapsable">
    <caption>
        <?= _('Freigegebene Ergebnisse') ?>
    </caption>

    <thead>
        <tr class="sortable">
            <th style="width: 40%;" class="<?= $controller->sort_class($sort === 'title', $desc) ?>">
                <a href="<?= $controller->link_for('vips/solutions', ['sort' => 'title', 'desc' => $sort === 'title' && !$desc]) ?>">
                    <?= _('Titel') ?>
                </a>
            </th>
            <th style="width: 20%;" class="<?= $controller->sort_class($sort === 'start', $desc) ?>">
                <a href="<?= $controller->link_for('vips/solutions', ['sort' => 'start', 'desc' => $sort === 'start' && !$desc]) ?>">
                    <?= _('Start') ?>
                </a>
            </th>
            <th style="width: 20%;" class="<?= $controller->sort_class($sort === 'end', $desc) ?>">
                <a href="<?= $controller->link_for('vips/solutions', ['sort' => 'end', 'desc' => $sort === 'end' && !$desc]) ?>">
                    <?= _('Ende') ?>
                </a>
            </th>
            <th colspan="3" style="width: 5%; text-align: right;">
                <?= _('Punkte') ?>
            </th>
            <th style="width: 10%; text-align: right;">
                <?= _('Prozent') ?>
            </th>
            <th class="actions">
                <?= _('Aktion') ?>
            </th>
        </tr>
    </thead>

    <? foreach ($blocks as $block) :?>
        <? if (isset($block_assignments[$block->id])): ?>
            <tbody>
                <? if (count($block_assignments) > 1): ?>
                    <tr class="header-row">
                        <th class="toggle-indicator" colspan="8">
                            <a class="toggler" href="#">
                                <?= htmlReady($block->name) ?>
                            </a>
                        </th>
                    </tr>
                <? endif ?>

                <? foreach ($block_assignments[$block->id] as $ass): ?>
                    <tr>
                        <td>
                            <a href="<?= $controller->student_assignment_solutions(['assignment_id' => $ass['assignment']->id]) ?>">
                                <?= $ass['assignment']->getTypeIcon() ?>
                                <?= htmlReady($ass['assignment']->test->title) ?>
                            </a>
                        </td>
                        <td>
                            <?= date('d.m.Y, H:i', $ass['assignment']->start) ?>
                        </td>
                        <td>
                            <? if (!$ass['assignment']->isUnlimited()) : ?>
                                <?= date('d.m.Y, H:i', $ass['assignment']->end) ?>
                            <? endif ?>
                        </td>
                        <td style="text-align: right;">
                            <?= sprintf('%g', $ass['reached_points']) ?>
                        </td>
                        <td style="text-align: center;">
                            /
                        </td>
                        <td style="text-align: right;">
                            <?= sprintf('%g', $ass['max_points']) ?>
                        </td>
                        <td style="text-align: right;">
                            <? if ($ass['max_points'] != 0) : ?>
                                <?= sprintf('%.1f %%', 100 * $ass['reached_points'] / $ass['max_points']) ?>
                            <? else : ?>
                                &ndash;
                            <? endif ?>
                        </td>
                        <td class="actions">
                            <? if ($ass['released'] >= VipsAssignment::RELEASE_STATUS_CORRECTIONS): ?>
                                <? $menu = ActionMenu::get() ?>
                                <? $menu->addLink(
                                    $controller->url_for('vips/sheets/print_assignments', ['assignment_id' => $ass['assignment']->id]),
                                    _('Aufgabenblatt drucken'),
                                    Icon::create('print'),
                                    ['target' => '_blank']
                                ) ?>
                                <?= $menu->render() ?>
                            <? endif ?>
                        </td>
                    </tr>
                <? endforeach ?>
            </tbody>
        <? endif ?>
    <? endforeach ?>

    <tfoot>
        <tr>
            <td colspan="3"></td>
            <td style="padding: 5px; text-align: right;">
                <?= sprintf('%g', $sum_reached_points) ?>
            </td>
            <td style="padding: 5px; text-align: center;">
                /
            </td>
            <td style="padding: 5px; text-align: right;">
                <?= sprintf('%g', $sum_max_points) ?>
            </td>
            <td style="padding: 5px; text-align: right;">
                <? if ($sum_max_points != 0) : ?>
                    <?= sprintf('%.1f %%', 100 * $sum_reached_points / $sum_max_points) ?>
                <? else : ?>
                    &ndash;
                <? endif ?>
            </td>
            <td>
            </td>
        </tr>
    </tfoot>
</table>
