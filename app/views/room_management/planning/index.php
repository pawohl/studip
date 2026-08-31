<? if ($no_clipboard): ?>
    <?= MessageBox::info(
        _('Es wurde keine Raumgruppe ausgewählt!')
    ) ?>
<? elseif ($no_rooms): ?>
    <?= MessageBox::info(
        _('Die gewählte Raumgruppe enthält keine Räume!')
    ) ?>
<? else: ?>
    <? if (Request::get("allday")) {
        $min_time = '00:00:00';
        $max_time = '24:00:00';
    } else {
        $min_time = Config::get()->RESOURCES_BOOKING_PLAN_START_HOUR . ':00';
        $max_time = Config::get()->RESOURCES_BOOKING_PLAN_END_HOUR . ':00';
    } ?>
    <section class="studip-fullcalendar-header booking-plan-header">
        <div>
            <?= _('Raumgruppe') ?>
            <span id="booking-plan-header-roomgroup"><?= htmlReady($clipboard->name) ?></span>
            <span id="booking-plan-header-calweek"></span>
            <span id="booking-plan-header-calbegin"></span>
            <span id="booking-plan-header-semrow">
                <strong><span id="booking-plan-header-semname"></span></strong>
                <span id="booking-plan-header-semweek-part">
                    <span id="booking-plan-header-semweek"></span>
                </span>
            </span>
        </div>
    </section>

    <?= \Studip\Fullcalendar::create(
        _('Belegungsplan'),
        [
            'resources' => $scheduler_resources,
            'resourceOrder' => 'order',
            'resourceLabelText' => _('Raum'),
            'editable' => true,
            'selectable' => $all_rooms_booking_rights,
            'studip_urls' => $fullcalendar_studip_urls,
            'slotMinTime' => $min_time,
            'slotMaxTime' => $max_time,
            'allDaySlot' => false,
            'headerToolbar' => [
                'start' => '',
                'center' => 'title',
                'end' => 'prev,next'
            ],
            'slotLabelFormat' => [
                ['hour'=> '2-digit',
                 'hour12' => false]
            ],
            'initialView' => \Studip\Fullcalendar::GROUP_DAY,
            'initialDate' => Request::get("defaultDate"),
            'eventSources' => [
                [
                    'url' => URLHelper::getLink(
                        'dispatch.php/resources/ajax/get_clipboard_booking_plan/' . $clipboard->id
                    ),
                    'method' => 'GET',
                    'extraParams' => [
                        'booking_types' => $booking_types,
                        'display_requests' => 1,
                        'display_all_requests' => $display_all_requests ? 1 : 0
                    ]
                ]
            ],
            'confirm' => (
                UserConfig::get($GLOBALS['user']->id)->RESOURCES_CONFIRM_PLAN_DRAG_AND_DROP
                ? [
                    'drop' => _('Wollen Sie die Buchung wirklich ändern?')
                ]
                : []
            )
        ],
        ['class' => 'resource-plan room-group-booking-plan'],
        'resources-fullcalendar'
    ) ?>
    <ul class="map-key-list">
        <? foreach ($table_keys as $key): ?>
            <li class="map-key">
                <span style="background-color:<?= $key['colour'] ?>">
                    &nbsp;
                </span>
            <?= htmlReady($key['text']) ?>
            </li>
        <? endforeach ?>
        <li class="map-key">
            <?= Icon::create('refresh', Icon::ROLE_INFO, ['class' => 'text-bottom']); ?>
            <?= _('Wiederholungstermin') ?>
        </li>
        <li class="map-key">
            <?= Icon::create('chat2', Icon::ROLE_INFO, ['class' => 'text-bottom']); ?>
            <?= _('Kommentar') ?>
        </li>
    </ul>
<? endif ?>
