<form action="<?= URLHelper::getLink("dispatch.php/questionnaire/context/".$questionnaire->getId()) ?>"
      method="post"
      class="default"
      <?= Request::isAjax() ? "data-dialog" : "" ?>
    >
    <fieldset>
        <legend><?= _("URL zum Fragebogen") ?></legend>
        <input type="text" aria-label="<?= _('URL zum Fragebogen (nur lesbar)') ?>" readonly value="<?= htmlReady($GLOBALS['ABSOLUTE_URI_STUDIP'].'dispatch.php/questionnaire/answer/'.$questionnaire->getId()) ?>">
        <a href="<?= htmlReady($GLOBALS['ABSOLUTE_URI_STUDIP'] . "dispatch.php/questionnaire/answer/" . $questionnaire->id) ?>"
           data-qr-code title="<?= _('QR-Code zum Link anzeigen') ?>">
            <?= Icon::create('code-qr')->asImg(['class' => 'text-bottom']) ?>
        </a>
    </fieldset>
    <fieldset>
        <legend><?= _("Freigaben bearbeiten") ?></legend>
        <label>
            <input type="checkbox" name="user" value="1"<?= $profile ? " checked" : "" ?>>
            <?= _("Auf der persönlichen Profilseite") ?>
        </label>
        <label>
            <input type="checkbox" name="public" value="1"<?= $public ? " checked" : "" ?>>
            <?= _("Als öffentlicher Link für unangemeldete Nutzer") ?>
        </label>
        <? if ($GLOBALS['perm']->have_perm("root")) : ?>
            <label>
                <input type="checkbox" name="start" value="1"<?= $start ? " checked" : "" ?>>
                <?= _("Auf der Systemstartseite") ?>
            </label>
        <? endif ?>

        <h3><?= _("Veranstaltungen") ?></h3>
        <ul class="clean courseselector">
            <? foreach ($questionnaire->assignments as $assignment) : ?>
            <? if ($assignment['range_type'] === "course") : ?>
                <? $course = Course::find($assignment['range_id']) ?>
                <? if ($course) : ?>
                <li>
                    <label>
                        <input type="checkbox" name="remove_sem[]" value="<?= htmlReady($assignment['range_id']) ?>" style="display: none;">
                        <span>
                            <a href="<?= URLHelper::getLink('dispatch.php/course/go', ['to' => $course->getId()]) ?>">
                                <?= htmlReady((Config::get()->IMPORTANT_SEMNUMBER ? $course->veranstaltungsnummer." " : "").$course->name. ' ('.$course->semester_text.')') ?>
                            </a>
                            <?= Icon::create('trash')->asImg(['class' => 'text-bottom', 'title' => _('Zuweisung zur Veranstaltung aufheben.')]) ?>
                        </span>
                    </label>
                </li>
                <? endif ?>
            <? endif ?>
            <? endforeach ?>
        </ul>
        <?= QuickSearch::get("add_seminar_id", $seminarsearch)->render() ?>

        <h3><?= _("Teilnehmergruppen in Veranstaltungen") ?></h3>
        <ul class="clean statusgroupselector">
            <? foreach ($questionnaire->assignments as $assignment) : ?>
                <? if ($assignment['range_type'] === "statusgruppe") : ?>
                    <li>
                        <label>
                            <input type="checkbox" name="remove_statusgruppe[]" value="<?= htmlReady($assignment['range_id']) ?>" style="display: none;">
                            <? $statusgruppe = Statusgruppen::find($assignment['range_id']) ?>
                            <span>
                                <a href="<?= URLHelper::getLink("dispatch.php/course/go", ['to' => $statusgruppe->getId()]) ?>">
                                    <?= htmlReady($statusgruppe->course['name'].": ".$statusgruppe->name) ?>
                                </a>
                                <?= Icon::create('trash')->asImg(['class' => 'text-bottom', 'title' => _('Zuweisung zur Veranstaltung aufheben.')]) ?>
                            </span>
                        </label>
                    </li>
                <? endif ?>
            <? endforeach ?>
        </ul>
        <?= QuickSearch::get("add_statusgruppe_id", $statusgruppesearch)->render() ?>

        <? if ($GLOBALS['perm']->have_perm("admin")) : ?>
            <h3><?= _("Einrichtungen") ?></h3>
            <ul class="clean instituteselector">
                <? foreach ($questionnaire->assignments as $assignment) : ?>
                    <? if ($assignment['range_type'] === 'institute') : ?>
                        <li>
                            <label>
                                <input type="checkbox" name="remove_inst[]" value="<?= htmlReady($assignment['range_id']) ?>" style="display: none;">
                                <span><?= htmlReady(Institute::find($assignment['range_id'])->name) ?></span>
                                <?= Icon::create('trash', 'clickable', ['title' => _('Zuweisung zur Einrichtung aufheben.')])->asImg(['class' => 'text-bottom']) ?>
                            </label>
                        </li>
                    <? endif ?>
                <? endforeach ?>
            </ul>
            <?= QuickSearch::get('add_institut_id', new InstituteSearch($questionnaire->assignments->filter(function ($q) {
                return $q['range_type'] === 'institute';
            })->pluck('range_id')))->render() ?>
        <? endif ?>

        <?
        foreach (PluginManager::getInstance()->getPlugins(QuestionnaireAssignmentPlugin::class) as $plugin) {
            $template = $plugin->getQuestionnaireAssignmentEditTemplate($questionnaire);
            if ($template) {
                echo $template->render();
            }
        }
        ?>

    </fieldset>

    <footer data-dialog-button>
        <?= \Studip\Button::create(_("Speichern"), 'questionnaire_store_relations') ?>
    </footer>
</form>
