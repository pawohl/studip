<?php
/**
 * @var LtiTool $tool
 * @var ?\LtiResourceLink $link
 */
?>
<fieldset>
    <legend><?= _('Grunddaten') ?></legend>
    <label class="studiprequired">
        <span class="textlabel"><?= _('Titel') ?></span>
        <span class="asterisk">*</span>
        <input type="text" name="name" required
               value="<?= htmlReady($link->title ?? $tool->name ?? '') ?>">
    </label>
    <label>
        <?= _('Beschreibung') ?>
        <textarea name="description" class="wysiwyg"><?= wysiwygReady($link->description ?? $tool->description ?? '') ?></textarea>
    </label>
    <label>
        <?= _('Datenschutzhinweise') ?>
        <textarea name="data_protection_notes" class="wysiwyg"
                  placeholder="<?= _('Bitte machen Sie Angaben zu dem angebundenen Werkzeug, soweit sie ihnen bekannt sind. Wie ist der Name, wer bietet es an, wozu wird es eingesetzt und welche Daten werden übertragen? (Beispiel: „Tool XY wird zur Durchführung von Sprachtests genutzt und Testergebnisse und ggf. Noten werden gespeichert. Zur Anmeldung werden Name und Nutzerkennung übertragen.“)') ?>"><?= wysiwygReady($tool->data_protection_notes) ?></textarea>
    </label>
    <label>
        <?= _('URL zu den Nutzungsbedingungen des LTI-Tools (falls verfügbar)') ?>
        <input type="url" name="terms_of_use_url" value="<?= htmlReady($tool->terms_of_use_url) ?>">
    </label>
    <label>
        <?= _('URL zur Datenschutzerklärung des LTI-Tools (falls verfügbar)') ?>
        <input type="url" name="privacy_policy_url" value="<?= htmlReady($tool->privacy_policy_url) ?>">
    </label>
</fieldset>
<fieldset>
    <legend><?= _('Konfiguration des LTI-Tools') ?></legend>
    <label class="studiprequired">
        <span class="textlabel"><?= _('LTI-Version') ?></span>
        <span class="asterisk">*</span>
        <select name="lti_version"
                data-shows=".lti11-field" data-hides=".lti13a-field"
                data-triggering-value="1.1">
            <option value="1.1" <?= !empty($tool->lti_version) && $tool->lti_version === '1.1' ? 'selected' : '' ?>>
                1.0/1.1
            </option>
            <option value="1.3a" <?= empty($tool->lti_version) || $tool->lti_version === '1.3a' ? 'selected' : '' ?>>
                1.3a
            </option>
        </select>
    </label>

    <label class="studiprequired">
        <span class="textlabel"><?= _('LTI Launch-URL') ?></span>
        <span class="asterisk">*</span>
        <input type="text" name="launch_url" required
               value="<?= htmlReady($tool->launch_url ?? '') ?>">
    </label>

    <div class="lti13a-field">
        <label>
            <?= _('OIDC Login-URL') ?>
            <?= tooltipIcon(_('Die URL, mit der der Login via OpenID Connect stattfindet.')) ?>
            <input type="text" name="oidc_init_url" value="<?= htmlReady($tool->oidc_init_url ?? '') ?>">
        </label>
        <label>
            <?= _('Deep-linking URL') ?>
            <input type="url" name="deep_linking_url" value="<?= htmlReady($tool->deep_linking_url ?? '') ?>">
        </label>
        <label>
            <?= _('JWKS-URL') ?>
            <?= tooltipIcon(_('Die URL, mit der der der Austausch von JSON web keys stattfinden kann.')) ?>
            <input type="text" name="jwks_url"
                   value="<?= htmlReady($tool->jwks_url ?? '') ?>">
        </label>
        <label>
            <?= _('Schlüssel-ID') ?>
            <?= tooltipIcon(_('Die ID des Schlüssels, der über die JWKS-URL geladen werden soll.')) ?>
            <input type="text" name="jwks_key_id" value="<?= htmlReady($tool->jwks_key_id ?? '') ?>">
        </label>
        <label>
            <?= _('Öffentlicher Schlüssel des LTI-Tools') ?>
            <?
            $keyring = null;
            if ($tool && !$tool->isNew()) {
                $keyring = $tool->getKeyring();
            }
            $public_key_string = '';
            if ($keyring) {
                $keychain = $keyring->toKeyChain();
                $public_key_string = $keychain->getPublicKey()->getContent();
            }
            ?>
            <textarea name="tool_public_key"><?= htmlReady($public_key_string) ?></textarea>
        </label>
    </div>
    <div class="lti11-field">
        <label class="studiprequired">
            <span class="textlabel"><?= _('Consumer-Key des LTI-Tools') ?></span>
            <span class="asterisk">*</span>
            <input type="text" name="consumer_key" required
                   value="<?= htmlReady($tool->consumer_key ?? '') ?>">
        </label>
        <label class="studiprequired">
            <span class="textlabel"><?= _('Consumer-Secret des LTI-Tools') ?></span>
            <span class="asterisk">*</span>
            <input type="text" name="consumer_secret" required
                   value="<?= htmlReady($tool->consumer_secret ?? '') ?>">
        </label>
    </div>
    <label>
        <input type="checkbox" name="send_lis_person" value="1" <?= !empty($tool->send_lis_person) ? ' checked' : '' ?>>
        <?= _('Personendaten an das LTI-Tool senden') ?>
        <?= tooltipIcon(_('Personendaten dürfen nur an das externe Tool gesendet werden, wenn es keine Datenschutzbedenken gibt. Mit Setzen des Hakens bestätigen Sie, dass die Übermittlung der Daten zulässig ist.')) ?>
    </label>
    <label>
        <?= _('Zusätzliche LTI-Parameter') ?>
        <?= tooltipIcon(_('Ein Wert pro Zeile, Beispiel: Review:Chapter=1.2.56')) ?>
        <textarea name="custom_parameters"><?= htmlReady(
                !empty($link->options['custom_parameters'])
                    ? $link->options['custom_parameters']
                    : $tool->custom_parameters ?? ''
            ) ?></textarea>
    </label>
</fieldset>
<fieldset>
    <legend><?= _('Anzeigeeinstellungen') ?></legend>
    <label>
        <input type="checkbox" name="document_target" value="iframe" <?= isset($link->options['document_target']) && $link->options['document_target'] === 'iframe' ? ' checked' : '' ?>>
        <?= _('Anzeige im IFRAME auf der Seite') ?>
        <?= tooltipIcon(_('Normalerweise wird das externe Tool in einem neuen Fenster angezeigt. Aktivieren Sie diese Option, wenn die Anzeige stattdessen in einem IFRAME erfolgen soll.')) ?>
    </label>
</fieldset>

<footer data-dialog-button>
    <?= Studip\Button::createAccept(_('Speichern'), 'save') ?>
</footer>
