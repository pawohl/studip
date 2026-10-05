<?php
/**
 * @var Course_AdmissionController $controller
 * @var array $request
 * @var string $button_yes
 * @var string $button_no
 */
?>

<? foreach(PageLayout::getMessages() as $pm) : ?>
    <?= $pm ?>
<? endforeach; ?>
<form class="default" action="<?= $controller->link_for() ?>" method="post">
<?= CSRFProtection::tokenTag()?>
<? foreach(array_filter($request, function ($r) {return $r !== false;}) as $k => $v) : ?>
    <?= addHiddenFields($k, $v) ?>
<? endforeach ?>
<footer data-dialog-button>
    <?= Studip\Button::create(_('Ja'), $button_yes, ['data-dialog' => ''])?>
    <?= Studip\Button::createCancel(_('Abbrechen'), 'cancel') ?>
</footer>
</form>
