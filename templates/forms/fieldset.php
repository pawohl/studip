<?php
/**
 * @var bool $collapsable
 * @var bool $collapsed
 * @var string $legend
 * @var array<\Studip\Forms\Part> $part
 */
?>
<fieldset<?= $collapsable ? ' class="collapsable' . ($collapsed ? ' collapsed' : '') . '"' : '' ?>>
    <? if ($legend) : ?>
        <legend><?= htmlReady($legend) ?></legend>
    <? endif ?>
    <? foreach ($parts as $part) : ?>
        <?= $part->renderWithCondition() ?>
    <? endforeach ?>
</fieldset>
