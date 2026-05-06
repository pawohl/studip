<form class="default" name="write_message" action="<?= URLHelper::getLink("dispatch.php/messages/send") ?>" method="post" style="margin-left: auto; margin-right: auto;" data-dialog data-secure="true">
    <?= CSRFProtection::tokenTag() ?>

    <input type="hidden" name="message_id" id="message_id" value="<?= htmlReady($default_message->id) ?>">
    <input type="hidden" name="answer_to" value="<?= htmlReady($answer_to) ?>">

    <article aria-live="assertive"
             class="validation_notes studip">
        <header>
            <h1>
                <?= Icon::create('info-circle', Icon::ROLE_INFO)->asImg(['class' => 'text-bottom validation_notes_icon']) ?>
                <?= _('Hinweise zum Ausfüllen des Formulars') ?>
            </h1>
        </header>
        <div class="required_note">
            <div aria-hidden="true">
                <?= _('Pflichtfelder sind mit Sternchen gekennzeichnet.') ?>
            </div>
            <div class="sr-only">
                <?= _('Dieses Formular enthält Pflichtfelder.') ?>
            </div>
        </div>
    </article>

    <fieldset>
        <legend><?= _('Neue Nachricht') ?></legend>
    <div class="message-user-list">
        <?
        $quick_search = QuickSearch::get('user_id', new StandardSearch('user_id'));
        ?>
        <label class="studiprequired" for="<?= htmlReady($quick_search->getId()) ?>">
            <?= _("An") ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        </label>
        <ul class="list-csv" id="adressees">
            <li id="template_adressee" style="display: none;" class="adressee">
                <input type="hidden" name="message_to[]" value="">
                <span class="visual"></span>
                <a class="remove_adressee"><?= Icon::create('trash', 'clickable')->asImg(['class' => 'text-bottom']) ?></a>
            </li>
            <? foreach ($default_message->getRecipients() as $user) : ?>
            <li class="adressee">
                <input type="hidden" name="message_to[]" value="<?= htmlReady($user['user_id']) ?>">
                <span class="visual">
                    <?= htmlReady($user['fullname']) ?>
                </span>
                <a class="remove_adressee"><?= Icon::create('trash', 'clickable')->asImg(['class' => 'text-bottom']) ?></a>
            </li>
            <? endforeach ?>
        </ul>
        <div class="message-search-wrapper">
        <?= $quick_search->fireJSFunctionOnSelect('STUDIP.Messages.add_adressee')
            ->setAttributes(['data-context' => ''])
            ->withButton()
            ->render();

        $mps = MultiPersonSearch::get('add_adressees')
            ->setLinkText(_('Mehrere Adressaten hinzufügen'))
            ->setExecuteURL($controller->url_for('messages/write'))
            ->setJSFunctionOnSubmit('STUDIP.Messages.add_adressees')
            ->setSearchObject($this->mp_search_object);
        foreach (Statusgruppen::findContactGroups() as $group) {
            $mps->addQuickfilter(
                $group['name'],
                $group->members->pluck('user_id')
            );
        }
        echo $mps->render();
        ?>
        </div>
        <script>
            STUDIP.MultiPersonSearch.init();
        </script>
    </div>
    <div>
        <label class="studiprequired">
            <?= _("Betreff") ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
            <input type="text" name="message_subject" required value="<?= htmlReady($default_message['subject']) ?>">
        </label>
    </div>
    <div>
        <label>
            <?= _("Nachricht") ?>
            <textarea name="message_body" class="wysiwyg"><?= wysiwygReady($default_message['message'],false) ?></textarea>
        </label>
    </div>
    <div>
        <ul class="message-options">
        <? if ($GLOBALS['ENABLE_EMAIL_ATTACHMENTS']): ?>
            <li>
                <a href="" onClick="STUDIP.Messages.toggleSetting('attachments'); return false;" aria-expanded="false"
                   role="button" title="<?= _('Anhänge verwalten') ?>" aria-controls="attachments"
                   id="toggle-attachments">
                    <?= Icon::create('staple')->asImg(40) ?>
                    <br>
                    <strong><?= _('Anhänge') ?></strong>
                </a>
            </li>
        <? endif; ?>
            <li>
                <a href="" onClick="STUDIP.Messages.toggleSetting('tags'); return false;" aria-expanded="false"
                   role="button" title="<?= _('Schlagworte verwalten') ?>" aria-controls="tags" id="toggle-tags">
                    <?= Icon::create('star')->asImg(40) ?>
                    <br>
                    <strong><?= _('Schlagworte') ?></strong>
                </a>
            </li>
            <li>
                <a href="" onClick="STUDIP.Messages.toggleSetting('settings'); return false;" aria-expanded="false"
                   role="button" title="<?= _('Optionen') ?>" aria-controls="settings" id="toggle-settings">
                    <?= Icon::create('admin', 'clickable')->asImg(40) ?>
                    <br>
                    <strong><?= _('Optionen') ?></strong>
                </a>
            </li>
        </ul>
    </div>

<? if ($GLOBALS['ENABLE_EMAIL_ATTACHMENTS']): ?>
    <div id="attachments" style="<?= $default_attachments ? '' : 'display: none;'?>">
        <?= _("Anhänge") ?>
        <div>
            <ul class="files">
                <li style="display: none;" class="file">
                    <span class="icon"></span>
                    <span class="name"></span>
                    <span class="size"></span>
                    <a class="remove_attachment"><?= Icon::create('trash', 'clickable')->asImg(['class' => 'text-bottom']) ?></a>
                </li>
                <? if ($default_attachments) : ?>
                    <? foreach ($default_attachments as $a) : ?>
                    <li class="file" data-document_id="<?=$a['document_id']?>">
                    <span class="icon"><?=$a['icon']?></span>
                    <span class="name"><?=$a['name']?></span>
                    <span class="size"><?=$a['size']?></span>
                    <a class="remove_attachment"><?= Icon::create('trash', 'clickable')->asImg(['class' => 'text-bottom']) ?></a>
                    </li>
                    <? endforeach ?>
                <? endif ?>
            </ul>
            <div id="statusbar_container">
                <div class="statusbar" style="display: none;">
                    <div class="progress"></div>
                    <div class="progresstext">0%</div>
                </div>
            </div>
            <label >
                <input type="file" id="fileupload" multiple onChange="STUDIP.Messages.upload_from_input(this);" style="display: none;">
                <?= Icon::create('upload')->asImg(['title' => _('Datei hochladen'), 'class' => 'text-bottom']) ?>
                <?= _('Datei hochladen') ?>
            </label>

            <div id="upload_finished" style="display: none"><?= _("wird verarbeitet") ?></div>
            <div id="upload_received_data" style="display: none"><?= _("gespeichert") ?></div>
        </div>
    </div>
<? endif; ?>
    <div id="tags" style="<?= $default_tags ? '' : 'display: none' ?>">
        <label>
            <?= _("Schlagworte") ?>
            <input type="text"
                   name="message_tags"
                   style="width: 100%"
                   placeholder="<?= _("z.B. klausur termin statistik etc.") ?>"
                   value="<?= htmlReady($default_tags) ?>">
        </label>
    </div>
    <div id="settings" style="display: none;">
        <?= _("Optionen") ?>
        <label for="message_mail">
            <input type="checkbox" name="message_mail" id="message_mail" value="1"<?= $mailforwarding ? " checked" : "" ?>>
            <?= _("Immer per E-Mail weiterleiten") ?>
        </label>

        <? if (isset($show_adressees)) : ?>
            <label for="show_adressees">
                <input type="checkbox" name="show_adressees" id="show_adressees" value="1"<?= $show_adressees ? " checked" : "" ?>>
                <?= _("Sollen die Adressaten für die Empfänger sichtbar sein?") ?>
            </label>
        <? endif ?>
    </div>
    </fieldset>

    <footer data-dialog-button>
        <?= Studip\Button::createAccept(_('Abschicken'), null, ['onclick' => "STUDIP.Messages.checkAdressee();"]) ?>
        <?= Studip\LinkButton::createCancel(_('Abbrechen'), $controller->url_for('messages')) ?>
    </footer>

</form>
