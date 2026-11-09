<?php
/**
 * @var Course_RoomRequestsController $controller
 * @var string $request_id
 * @var int $step
 * @var ResourceCategory[] $available_room_categories
 * @var string $room_category_id
 * @var ResourceCategory|null $category
 * @var ResourcePropertyDefinition[] $available_properties
 * @var string $room_name
 * @var Icon[] $available_room_icons
 */
?>
<form method="post" name="room_request" class="default"
      action="<?= $controller->link_for('course/room_requests/request_check_properties/' . $request_id . '/' . $step) ?>"
    <?= Request::isXhr() ? 'data-dialog="size=big"' : ''?>>
    <input type="hidden" name="request_id" value="<?= htmlReady($request_id) ?>">
    <?= CSRFProtection::tokenTag() ?>

    <?= $this->render_partial('course/room_requests/_new_request_header') ?>

    <section class="resources-grid">
        <div>
            <fieldset class="request-fieldset">
                <legend><?= _('Wünschbare Eigenschaften') ?></legend>

                <? if ($step === 1 || $step === 2) : ?>
                    <?= $this->render_partial('course/room_requests/_room_with_properties') ?>
                <? endif ?>

                <? if ($available_room_categories): ?>
                    <label>
                        <?= _('Raumkategorie') ?>
                        <span class="flex-row">
                            <select name="category_id" >
                            <option value="0">-- <?= _('Bitte auswählen') ?> --</option>
                            <? foreach ($available_room_categories as $rc): ?>
                                <option value="<?= htmlReady($rc->id) ?>"
                                        <?= $room_category_id === $rc->id
                                            ? 'selected'
                                            : '' ?>>
                                <?= htmlReady($rc->name) ?>
                                </option>
                            <? endforeach ?>
                            </select>
                            <?= Icon::create('accept')->asInput(
                                [
                                    'title' => _('Raumtyp auswählen'),
                                    'class' => 'text-bottom',
                                    'name'  => 'search_by_category',
                                    'value' => _('Raumtyp auswählen'),
                                    'style' => 'margin-left: 0.2em; margin-top: 0.6em;'
                                ]
                            ) ?>
                            <? if ($category) : ?>
                            <?= Icon::create('decline')->asInput(
                                [
                                    'title' => _('alle Angaben zurücksetzen'),
                                    'class' => 'text-bottom',
                                    'name'  => 'reset_category',
                                    'style' => 'margin-left: 0.2em; margin-top: 0.6em;'
                                ]
                            ) ?>
                            <? endif ?>
                        </span>
                    </label>
                <? endif ?>

                <!-- ROOM CATEGORY PROPERTIES -->
                <? if ($available_properties) : ?>
                    <? foreach ($available_properties as $property) : ?>
                        <?= $property->toHtmlInput(
                            $selected_properties[$property->name] ?? '',
                            'selected_properties[' . htmlReady($property->name) . ']',
                            true,
                            false
                        ) ?>
                    <? endforeach ?>

                <div>
                    <?= \Studip\Button::create(_('Räume suchen'), 'search_rooms') ?>
                </div>
                <? endif ?>

            </fieldset>
        </div>

        <div>
            <fieldset class="request-fieldset">
                <legend><?= _('Raumsuche') ?></legend>
                <label>
                    <?= _('Raumname') ?>
                    <span class="flex-row">
                        <input type="text" name="room_name" value="<?= htmlReady($room_name) ?>" >
                        <?= Icon::create('search')->asInput([
                            'title' => _('Räume suchen'),
                            'name'  => 'search_by_name',
                            'class' => 'text-bottom',
                            'style' => 'margin-left: 0.2em; margin-top: 0.6em;',
                        ]) ?>
                    </span>
                </label>
            <? if (!empty($available_rooms)) : ?>
                <label>
                    <strong><?= _('Passende Räume') ?></strong>
                </label>
                <section class="selectbox" id="room_select">
                <? foreach ($available_rooms as $room): ?>
                    <div class="flex-row">
                        <label class="horizontal">
                            <?= $available_room_icons[$room->id] ?>
                            <input type="radio" name="selected_room_id"
                                   data-activates="button[type='submit'][name='select_room']"
                                   value="<?= htmlReady($room->id) ?>"
                                <? if (isset($_SESSION[$request_id]['room_id']) && $_SESSION[$request_id]['room_id'] === $room->id) echo 'checked' ?>>
                            <?= htmlReady(mila($room->name, 50)) . ' (' . $room['category']->name . ')'?>
                            <? if ($room->properties): ?>
                                <? $property_names = $room->getInfolabelProperties()
                                    ->pluck('fullname') ?>
                                <?= tooltipIcon(implode("\n", $property_names)) ?>
                            <? endif ?>
                        </label>
                    </div>
                <? endforeach ?>
                </section>
                <?= \Studip\Button::create(_('Raum auswählen'), 'select_room') ?>
            <? endif ?>
            </fieldset>

        </div>
    </section>

<?= $this->render_partial('course/room_requests/_new_request_form_footer', ['step' => $step, 'search_by' => 'category']) ?>
