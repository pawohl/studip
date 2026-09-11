<?php
// In den Controller
$is_exTermin = $termin instanceof CourseExDate;
?>
<tr>
<? if (!$locked) : ?>
    <td>
        <label for="<?= htmlReady($termin->termin_id) ?>">
            <input class="<?= $class_ids ?>" type="checkbox" id="<?= htmlReady($termin->termin_id) ?>"
                   value="<?= htmlReady($termin->termin_id) ?>"
                   <? if (is_array($checked_dates)): ?>
                       <? if (in_array($termin->termin_id, $checked_dates)) echo 'checked' ?>
                   <? else: ?>
                       <? if (!$is_exTermin && $termin->date > time() && ($termin->date <= $current_semester->ende || $semester_filter !== 'all')) echo 'checked' ?>
                   <? endif ?>
                   name="single_dates[]">
        </label>
    </td>
<? endif ?>

    <td class="<?= !empty($termin->getRooms()) ? 'green' : 'red' ?>">
    <? if ($is_exTermin) : ?>
        <span class="is_ex_termin">
            <?= htmlReady($termin->getFullName(CourseDate::FORMAT_VERBOSE)) ?>
        </span>
    <? elseif ($locked): ?>
        <?= htmlReady($termin->getFullName(CourseDate::FORMAT_VERBOSE)) ?>
    <? else: ?>
        <a data-dialog="size=600"
           href="<?= $controller->url_for('course/timesrooms/editDate/' . $termin->termin_id, $linkAttributes) ?>">
            <?= htmlReady($termin->getFullName(CourseDate::FORMAT_VERBOSE)) ?>
        </a>
    <? endif ?>
    </td>

    <td>
    <? if (count($termin->dozenten) > 0): ?>
        <ul class="list-unstyled list-csv <? if ($is_exTermin) echo 'is_ex_termin' ?>">
        <? foreach ($termin->dozenten as $dozent) : ?>
            <li><?= $dozent instanceof User ? htmlReady($dozent->getFullName()) : '' ?></li>
        <? endforeach ?>
        </ul>
    <? endif ?>
    </td>
    <td>
        <ul class="list-unstyled list-csv">
            <? if (count($termin->statusgruppen)) : ?>
                <? foreach ($termin->statusgruppen as $group) : ?>
                    <li><?= htmlReady($group->name)?></li>
                <? endforeach ?>
            <? endif ?>
        </ul>
    </td>
    <td>
    <? if ($room_holiday = SemesterHoliday::isHoliday($termin->date, false)): ?>
        <? $room_holiday = sprintf(
            '<span %s>(%s)</span>',
            ($is_exTermin ? ' class="is_ex_termin"' : ''),
            htmlReady($room_holiday['name'])
        )?>
    <? endif ?>

    <? if ($is_exTermin && ($comment = $termin->content)) : ?>
        <span class="is_ex_termin" style="font-style: italic"><?= _('(fällt aus)') ?></span>
        <?= tooltipIcon($termin->content) ?>
    <? elseif ($name = SemesterHoliday::isHoliday($termin->date, false) && $is_exTermin): ?>
        <?= $room_holiday ?>
    <? elseif ($rooms = $termin->getRooms()) : ?>
        <? foreach ($rooms as $room) : ?>
            <span class="no-break">
                <a href="<?= $room->getActionLink('booking_plan', ['defaultDate' => date('Y-m-d', $termin->date)]) ?>"
                   data-dialog="size=big">
                    <?= Icon::create('link-intern')->asImg(['class' => 'text-bottom']) ?>
                    <?= htmlReady($room->getFullName()) ?>
                </a>
            </span>
        <? endforeach ?>
        <?= $room_holiday ?: '' ?>
    <? elseif ($freeTextRoom = $termin->getRoomNames()) : ?>
        <?= sprintf('(%s)', formatLinks($freeTextRoom)) ?>
    <? else : ?>
        <?= _('Keine Raumangabe') ?>
        <?= $room_holiday ?: '' ?>
    <? endif ?>

    <? $room_request = RoomRequest::findByDate($termin->id) ?>
    <? if ($room_request && $room_request->closed == ResourceRequest::STATE_OPEN): ?>
        <? $msg_info = _('Für diesen Termin existiert eine Raumanfrage.') ?>
        <?= tooltipIcon($msg_info) ?>
    <? endif ?>
    </td>
    <td class="actions">
        <? $actionMenu = ActionMenu::get()->setContext($termin) ?>
    <? if ($is_exTermin && !$locked): ?>
        <? $actionMenu->addLink(
            $controller->url_for(
                'course/timesrooms/cancel/' . $termin->id
                . ($termin->metadate_id ? '/' . $termin->metadate_id : ''),
                $linkAttributes
            ),
            _('Kommentare bearbeiten'),
            Icon::create('edit', Icon::ROLE_CLICKABLE, ['title' => _('Kommentar für diesen Termin bearbeiten')]),
            ['data-dialog' => 'size=50%']
        ) ?>

        <? $params = [
            'name'         => 'delete_single_date',
            'data-confirm' => _('Diesen Termin wiederherstellen?'),
            'formaction'   => $controller->url_for('course/timesrooms/undeleteSingle/' . $termin->id),
        ] ?>
        <? if (Request::isXhr()) : ?>
            <? $params['data-dialog'] = 'size=auto' ?>
        <? endif ?>

        <? $actionMenu->addButton(
            'delete_part',
            _('Termin wiederherstellen'),
            Icon::create('refresh'),
            $params
        ) ?>

    <? elseif (!$locked) : ?>
        <? $actionMenu->addLink(
            $controller->url_for('course/timesrooms/editDate/' . $termin->id, $linkAttributes),
            _('Termin bearbeiten'),
            Icon::create('edit'),
            ['data-dialog' => 'width=430;height=720']
        ) ?>
        <? if (!$termin->metadate_id): ?>
            <? $actionMenu->addLink(
                $controller->url_for('course/timesrooms/cloneDate/' . $termin->id, $linkAttributes),
                _('Termin duplizieren'),
                Icon::create('copy')
            ) ?>
        <? endif ?>
        <? $actionMenu
                ->conditionAll(Config::get()->RESOURCES_ENABLE && Config::get()->RESOURCES_ALLOW_ROOM_REQUESTS)
                ->condition((bool) $room_request)
                ->addLink(
                    $controller->url_for(
                        'course/room_requests/request_show_summary',
                        $room_request
                    ),
                    _('Raumanfrage bearbeiten'),
                    Icon::create('room-occupied'),
                    ['data-dialog' => 'size=big']
                )
                ->condition(!$room_request)
                ->addLink(
                    $controller->url_for(
                        'course/room_requests/new_request',
                        ['range_str' => 'date', 'range_id' => $termin->id]
                    ),
                    _('Neue Raumanfrage'),
                    Icon::create('room-request'),
                    ['data-dialog' => 'size=big']
                )
                ->conditionAll(true)
        ?>

        <? $actionMenu->addLink(
            $controller->url_for(
                'course/timesrooms/stack',
                [
                    'single_dates[]' => $termin->termin_id,
                    'method' => 'preparecancel'
                ]
            ),
            _('Termin löschen'),
            Icon::create('trash'),
            ['data-dialog' => '1']
        ) ?>
    <? endif ?>
        <?= $actionMenu->render() ?>
    </td>
</tr>
