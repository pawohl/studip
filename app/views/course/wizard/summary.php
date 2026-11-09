<?php
/**
 * @var Course_WizardController $controller
 * @var int $stepnumber
 * @var string $temp_id
 * @var bool $dialog
 * @var Course|null $source_course
 */
?>
<form class="default" action="<?= $controller->link_for('course/wizard/process', $stepnumber, $temp_id) ?>" method="post">
<fieldset>
    <legend><?= _('Anlegen der Veranstaltung') ?></legend>

<? if ($dialog) : ?>
    <?= MessageBox::info(
        _('Sie haben alle benötigten Daten angegeben und können nun die Veranstaltung anlegen.')
    ) ?>
<? else : ?>
    <?= MessageBox::info(
        _('Sie haben alle benötigten Daten angegeben und können nun die Veranstaltung anlegen.')
        . ' ' .
        _('Der nächste Schritt führt Sie  gleich in den Verwaltungsbereich '
        . 'der neu angelegten Veranstaltung, wo Sie weitere Daten hinzufügen können.')
    ) ?>
<? endif ?>

<? if (isset($source_course)) : ?>
    <section>
        <p>
            <?= sprintf(
                _('Folgende Daten der Ursprungsveranstaltung (%s) kopieren'),
                sprintf(
                    '<a data-dialog href="%s">%s</a>',
                    URLHelper::getLink('dispatch.php/course/details', ['sem_id' => $source_course->id]),
                    htmlReady($source_course->getFullName())
                )
            ) ?>
        </p>

        <label>
            <input type="checkbox" checked name="copy_basic_data" value="1">
            <?= _('Grunddaten') ?>
        </label>

        <label>
            <input type="checkbox" name="copy_participants" value="1">
            <?= _('Reguläre Teilnehmende') ?>
        </label>

        <label>
            <input type="checkbox" name="copy_groups" value="1" data-activates="[name='copy_members']">
            <?= _('Statusgruppen') ?>
        </label>

        <label>
            <input type="checkbox" name="copy_members" value="1">
            <?= _('Zugeordnete Teilnehmende der Statusgruppen') ?>
        </label>
    </section>
<? endif ?>

    <section>
        <input type="hidden" name="step" value="<?= $stepnumber ?>">
    <? if ($dialog) : ?>
        <input type="hidden" name="dialog" value="1">
    <? endif ?>
    </section>
</fieldset>

    <footer data-dialog-button>
    <? if (isset($_SESSION['coursewizard'][$temp_id]['batchcreate'])) : ?>
        <?= addHiddenFields(
            'batchcreate',
            $_SESSION['coursewizard'][$temp_id]['batchcreate']
        ) ?>
    <? endif ?>
        <?= Studip\Button::create(_('Zurück'), 'back',
            $dialog ? ['data-dialog' => 'size=50%'] : []) ?>
        <?= Studip\Button::createAccept(_('Veranstaltung anlegen'), 'create') ?>
    </footer>
</form>
