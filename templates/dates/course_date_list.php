<?php
/**
 * @var CourseDateList $collection
 * @var bool $with_room_names
 * @var bool $with_cancelled_dates
 *
 * @var SeminarCycleDate $regular_date
 * @var CourseDate $single_date
 * @var CourseExDate $cancelled_date
 */
?>
<? if (!$collection->isEmpty()) : ?>
    <ul class="list-unstyled collapsible-list" data-label="<?= _('Termine') ?>">
        <? foreach ($collection->getRegularDates() as $regular_date) : ?>
            <li>
                <?
                $output = $regular_date->toString($with_room_names ? 'long-start' : 'long-start-without-room', true);
                if ($regular_date->description) {
                    $output .= sprintf(', <i>%s</i>', htmlReady($regular_date->description));
                }
                ?>
                <?= $output ?>
            </li>
        <? endforeach ?>
        <? foreach ($collection->getSingleDates() as $single_date) : ?>
            <li>
                <?= htmlReady($single_date->getFullName('long')) ?>
                <? if ($with_room_names): ?>
                    <? $rooms = $single_date->getRooms() ?>
                    <? if ($rooms): ?>
                        <? foreach ($rooms as $room): ?>
                            <a href="<?= $room->getActionLink() ?>" data-dialog>
                                <?= htmlReady($room->name) ?>
                            </a>
                        <? endforeach ?>
                    <? else: ?>
                        <?= htmlReady($single_date->raum) ?>
                    <? endif ?>
                <? endif ?>
            </li>
        <? endforeach ?>
        <? if ($with_cancelled_dates) : ?>
            <? foreach ($collection->getCancelledDates() as $cancelled_date) : ?>
                <li>
                    <?= htmlReady($cancelled_date->getFullName()) ?>
                </li>
            <? endforeach ?>
        <? endif ?>
    </ul>
<? endif ?>
