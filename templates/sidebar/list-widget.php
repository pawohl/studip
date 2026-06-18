<form class="default" method="post">
    <?= CSRFProtection::tokenTag() ?>
    <ul class="<?= implode(' ', $css_classes) ?>" aria-label="<?= htmlReady($title) ?>">
    <? foreach ($elements as $index => $element): ?>
        <li id="<?= htmlReady($index) ?>"
            <?= !empty($element->active) ? 'class="active"' : '' ?>>
            <?= $element->render() ?>
        </li>
    <? endforeach; ?>
    </ul>
</form>
