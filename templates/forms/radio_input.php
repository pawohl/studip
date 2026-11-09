<?php
/**
 * @var string $id
 * @var string $name
 * @var string $value
 * @var string|null $orientation
 * @var bool $required
 * @var array $options
 * @var string $attributes
 */
?>
<div class="formpart">
    <section <?= $orientation == 'horizontal' ? 'class="hgroup"' : '' ?> id="<?= htmlReady($id) ?>">
    <span class="textlabel<?= $required ? ' studiprequired' : '' ?> ">
        <?= htmlReady($title) ?>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
    </span>

    <? $count = 0; foreach ($options as $key => $option) : ?>
        <label class="" <?= $attributes ?>>
                <input type="radio"
                       name="<?= htmlReady($name) ?>"
                       v-model="<?= htmlReady($name) ?>"
                       value="<?= htmlReady($key) ?>" <?= $key == $value ? 'checked' : '' ?>
                       <?= $required && $count === 0 ? ' required' : ''?>>
                    <?= htmlReady($option) ?>
        </label>
    <? $count++; endforeach ?>
</section>
</div>
