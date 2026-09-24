<?php
/**
 * @var Vips_SheetsController $controller
 * @var int $assignment_id
 * @var array<class-string<Exercise>, array> $exercise_types
 */
?>
<form class="default" action="<?= $controller->edit_exercise() ?>" method="POST">
    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">

    <fieldset>
        <legend>
            <?= _('Aufgabentyp auswählen') ?>
        </legend>

        <ul class="exercise_types">
            <? foreach ($exercise_types as $type => $entry): ?>
            <li>
                <button class="exercise_type" name="exercise_type" value="<?= htmlReady($type) ?>">
                    <?= $type::getTypeIcon()->asImg(40) ?>
                    <div class="exercise_type_description">
                        <span class="exercise_type_name"><?= htmlReady($entry['name']) ?></span>
                        <span><?= htmlReady($type::getTypeDescription()) ?></span>
                    </div>
                </button>
            </li>
            <? endforeach ?>
        </ul>
    </fieldset>
</form>
