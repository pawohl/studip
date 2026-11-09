<?php
/**
 * @var array<array{label: string, url: string, attributes: array}> $links
 * @var Course $course
 * @var Course[] $siblings
 * @var Course_DetailsController $controller
 * @var string $prelim_discussion
 * @var stdClass $id_sfx
 * @var StudipStudyArea[] $study_areas
 */
?>
<style>
    /* This should be done by an own class (maybe not table? maybe dd?) */
    #tablefix {
        padding: 0;
    }
    #tablefix > header {
        margin: 0px;
    }
    #tablefix table {
        margin-bottom: 0;
        border-bottom: 0;
    }
    #tablefix table tbody tr:last-child td {
        border-bottom: 0;
    }
</style>

<article class="studip" id="tablefix">
    <header>
        <h1><?= _('Allgemeine Informationen') ?></h1>
    </header>
    <table class="default nohover">
        <colgroup>
            <col style="width: 40%">
            <col style="width: 60%">
        </colgroup>
        <tbody>
            <tr>
                <td><strong><?= _('Veranstaltungsname') ?></strong></td>
                <td><?= htmlReady($course->getFullName()) ?></td>
            </tr>
        <? if ($course->untertitel) : ?>
            <tr>
                <td><strong><?= _('Untertitel') ?></strong></td>
                <td><?= htmlReady($course->untertitel) ?></td>
            </tr>
        <? endif ?>
        <? if ($course->veranstaltungsnummer) : ?>
            <tr>
                <td><strong><?= _('Veranstaltungsnummer') ?></strong></td>
                <td><?= htmlReady($course->veranstaltungsnummer) ?></td>
            </tr>
        <? endif ?>
        <? if ($course->start_semester): ?>
            <tr>
                <td>
                    <strong><?= _('Semester') ?></strong>
                </td>
                <td>
                    <?= htmlReady($course->getFullName('sem-duration-name')) ?>
                </td>
            </tr>
        <? endif ?>
            <tr>
                <td>
                    <strong><?= _('Aktuelle Anzahl der Teilnehmenden') ?></strong>
                </td>
                <td><?= $course->getNumParticipants() ?></td>
            </tr>
        <? if ($course->admission_turnout) : ?>
            <tr>
                <td>
                    <strong>
                    <? if ($course->isAdmissionEnabled()) : ?>
                        <?= _('maximale Teilnehmendenzahl') ?>
                    <? else : ?>
                        <?= _('erwartete Teilnehmendenzahl') ?>
                    <? endif ?>
                    </strong>
                </td>
                <td><?= htmlReady($course->admission_turnout) ?></td>
            </tr>
        <? endif ?>
        <? if ($course->isAdmissionEnabled() && $course->getNumWaiting()) : ?>
            <tr>
                <td>
                    <strong><?= _('Wartelisteneinträge') ?></strong>
                </td>
                <td><?= $course->getNumWaiting() ?></td>
            </tr>
        <? endif ?>
            <tr>
                <td><strong><?= _("Heimateinrichtung") ?></strong></td>
                <td>
                    <a href="<?= URLHelper::getScriptLink("dispatch.php/institute/overview", ['auswahl' => $course->institut_id]) ?>">
                        <?= htmlReady($course->home_institut->name) ?>
                    </a>
                </td>
            </tr>
        <? if ($course->institutes->count() > 1): ?>
            <tr>
                <td>
                    <strong><?= _('Beteiligte Einrichtungen') ?></strong>
                </td>
                <td>
                    <?= join(', ', $course->institutes
                                    ->findBy('institut_id', $course->institut_id, '<>')
                                    ->orderBy('name')
                                    ->map(function($i) {
                                        return sprintf('<a href="%s">%s</a>', URLHelper::getScriptLink("dispatch.php/institute/overview", ['auswahl' => $i->id])
                                                                            , htmlReady($i->name));
                            })
                    ) ?>
                </td>
            </tr>
        <? endif ?>
        <tr>
            <td><strong><?= _("Veranstaltungstyp") ?></strong></td>
            <td>
                <?= sprintf(_("%s in der Kategorie %s"), $course->getSemType()->offsetGet('name'), $course->getSemClass()->offsetGet('name')) ?>
            </td>
        </tr>
    <? if ($course->parent) : ?>
        <tr>
            <td><strong><?= _('Hauptveranstaltung') ?></strong></td>
            <td>
                <?= _('Diese Veranstaltung gehört zu einer Hauptveranstaltung') ?>:
                <br><br>
                <a href="<?= $controller->link_for('course/details/', ['sem_id' => $course->parent->id]) ?>"
                   title="<?= htmlReady($course->parent->getFullName()) ?>">
                    <?= htmlReady($course->parent->getFullName()) ?>
                </a>
            <? if (count($siblings) > 0) : ?>
                <br><br>
                <section>
                    <?= _('Ebenfalls zu dieser Hauptveranstaltung gehören:') ?>
                    <ul>
                    <? foreach ($siblings as $sibling): ?>
                        <li>
                            <a href="<?= $controller->link_for('course/details/', ['sem_id' => $sibling->id]) ?>"
                               title="<?= htmlReady($sibling->getFullName()) ?>">
                                <?= htmlReady($sibling->getFullName()) ?>
                            </a>
                        </li>
                    <? endforeach ?>
                    </ul>
                </section>
            <? endif ?>
            </td>
        </tr>
    <? elseif (!empty($children)) : ?>
        <tr>
            <td><strong><?= _('Unterveranstaltungen') ?></strong></td>
            <td>
                <?= _('Dies ist eine Hauptveranstaltung mit folgenden Unterveranstaltungen:') ?>
                <ul>
                <? foreach ($children as $child): ?>
                    <li>
                        <a href="<?= $controller->link_for('course/details/', ['sem_id' => $child->id]) ?>"
                           title="<?= htmlReady($child->getFullName()) ?>">
                            <?= htmlReady($child->getFullName()) ?>
                        </a>
                    </li>
                <? endforeach ?>
                </ul>
            </td>
        </tr>
    <? endif ?>

        <? if ($prelim_discussion) : ?>
            <tr>
                <td><strong><?= _('Vorbesprechung') ?></strong></td>
                <td><?= $prelim_discussion ?></td>
            </tr>
        <? endif ?>
        <? $next_date = $course->getNextDate() ?>
        <? if ($next_date) : ?>
            <tr>
                <td><strong><?= _('Nächster Termin') ?></strong></td>
                <td><?= htmlReady($next_date->getFullname('long-include-room')) ?></td>
            </tr>
        <? else : ?>
            <? $firstTerm = $course->getFirstDate() ?>
            <? if ($firstTerm) : ?>
                <tr>
                    <td><strong><?= _('Erster Termin') ?></strong></td>
                    <td><?= htmlReady($firstTerm->getFullname('long-include-room')) ?></td>
                </tr>
            <? endif ?>
        <? endif ?>
        <? if ($course->art) : ?>
            <tr>
                <td><strong><?= _("Art/Form") ?></strong></td>
                <td><?= htmlReady($course->art) ?></td>
            </tr>
        <? endif ?>
        <? if ($course->teilnehmer != "") : ?>
            <tr>
                <td><strong><?= _("Teilnehmende") ?></strong></td>
                <td>
                    <?= htmlReady($course->teilnehmer, true, true) ?>
                </td>
            </tr>
        <? endif ?>
        <? if ($course->vorrausetzungen != "") : ?>
            <tr>
                <td><strong><?= _("Voraussetzungen") ?></strong></td>
                <td>
                    <?= htmlReady($course->vorrausetzungen, true, true) ?>
                </td>
            </tr>
        <? endif ?>
        <? if ($course->lernorga != "") : ?>
            <tr>
                <td><strong><?= _("Lernorganisation") ?></strong></td>
                <td>
                    <?= htmlReady($course->lernorga, true, true) ?>
                </td>
            </tr>
        <? endif ?>
        <? if ($course->leistungsnachweis != "") : ?>
            <tr>
                <td><strong><?= _("Leistungsnachweis") ?></strong></td>
                <td>
                    <?= htmlReady($course->leistungsnachweis, true, true) ?>
                </td>
            </tr>
        <? endif ?>
        <? foreach ($course->datafields->getTypedDatafield() as $entry) : ?>
            <? if ($entry->isVisible() && $entry->getValue()) : ?>
                <tr>
                    <td><strong><?= htmlReady($entry->getName()) ?></strong></td>
                    <td>
                        <?= $entry->getDisplayValue() ?>
                    </td>
                </tr>
            <? endif ?>
        <? endforeach ?>
        <? if ($course->sonstiges != "") : ?>
            <tr>
                <td><strong><?= _("Sonstiges") ?></strong></td>
                <td>
                    <?= formatLinks($course->sonstiges) ?>
                </td>
            </tr>
        <? endif ?>
        <? if ($course->ects) : ?>
            <tr>
                <td><strong><?= _("ECTS-Punkte") ?></strong></td>
                <td>
                    <?= htmlReady($course->ects, true, true) ?>
                </td>
            </tr>
        <? endif ?>
        </tbody>
    </table>
</article>

<? $lecturers = $course->getMembersWithStatus('dozent'); ?>
<? $count_lecturers = count($lecturers); ?>
<? if ($count_lecturers) : ?>
    <article class="studip">
        <header>
            <h1><?= htmlReady(get_title_for_status('dozent', $count_lecturers, $course->status))?></h1>
        </header>
        <section>
            <ul class="list-csv">
            <? foreach ($lecturers as $lecturer) : ?>
                <li>
                    <a href="<?= URLHelper::getScriptLink('dispatch.php/profile', ['username' => $lecturer['username']]) ?>">
                        <?= htmlReady($lecturer->getUserFullname() . ($lecturer->label ? " (" . $lecturer->label . ")" : "")) ?>
                    </a>
                </li>
            <? endforeach ?>
            </ul>
         </section>
    </article>
<? endif ?>

<? $tutors = $course->getMembersWithStatus('tutor'); ?>
<? $count_tutors = count($tutors); ?>
<? if ($count_tutors) : ?>
    <article class="studip">
        <header>
            <h1><?= htmlReady(get_title_for_status('tutor', $count_tutors, $course->status)) ?></h1>
        </header>
        <section>
            <ul class="list-csv">
            <? foreach ($tutors as $tutor) : ?>
                <li>
                    <a href="<?= URLHelper::getScriptLink('dispatch.php/profile', ['username' => $tutor['username']]) ?>">
                        <?= htmlReady($tutor->getUserFullname() . ($tutor->label ? " (" . $tutor->label . ")" : "")) ?>
                    </a>
                </li>
            <? endforeach ?>
            </ul>
         </section>
    </article>
<? endif ?>

<? if (CourseConfig::get($course->id)->COURSE_PUBLIC_TOPICS && count($course->topics)) : ?>
    <article class="studip">
        <header>
            <h1><?= _("Themen") ?></h1>
        </header>
        <section>
            <? foreach ($course->topics as $key => $topic) {
                if ($key > 0) {
                    echo ", ";
                }
                echo " ".htmlReady($topic['title']);
            } ?>
        </section>
    </article>
<? endif ?>

<? if (isset($public_files)) : ?>
    <?= $this->render_partial('profile/public_files') ?>
<? endif ?>

<article class="studip">
    <header>
        <h1><?= _('Räume und Zeiten') ?></h1>
    </header>
    <section>
        <?= $course->getAllDatesInSemester()->toHtml(true) ?>
    </section>
</article>
<? if ($studymodules) : ?>

    <article class="studip">
        <header>
            <h1><?= _('Studienmodule') ?></h1>
        </header>
        <section>
            <ul class="list-unstyled">
                <? foreach ($studymodules as $module) : ?>
                    <li>
                        <a class="module-info" href="<?= URLHelper::getLink($module['nav']->getUrl())?>">
                            <?= htmlReady($module['title']) ?>
                            <? if ($module['nav']->getImage()) : ?>
                                <?= $module['nav']->getImage()->asImg($module['nav']->getLinkAttributes()) ?>
                            <? endif ?>
                            <span><?= htmlReady($module['nav']->getTitle())?></span>
                        </a>
                    </li>
                <? endforeach ?>
            </ul>
        </section>
    </article>
<? endif ?>

<? if (isset($studyAreaTree) && $studyAreaTree->required_children) : ?>
    <?
    $semester_id = '';
    if ($course->end_semester && $course->end_semester !== Semester::findCurrent()) {
        $semester_id = $course->end_semester->id;
    }
    ?>
    <article class="studip">
        <header>
            <h1><?= _('Studienbereiche') ?></h1>
        </header>
        <section>
            <ul class="collapsable css-tree">
                <?= $this->render_partial(
                    'study_area/tree.php',
                    [
                        'node' => $studyAreaTree,
                        'semester_id' => $semester_id,
                        'open' => true,
                        'dont_open' => Config::get()->COURSE_SEM_TREE_CLOSED_LEVELS
                    ]
                ) ?>
            </ul>
        </section>
    </article>
<? endif ?>

<? if (!empty($study_areas)) : ?>
    <article class="studip">
        <header>
            <h1><?= _('Studienbereiche') ?></h1>
        </header>
        <section>
            <ul class="list-unstyled">
                <?
                $url_params = [];
                if ($course->end_semester && $course->end_semester !== Semester::findCurrent()) {
                    $url_params['semester'] = $course->end_semester->id;
                }
                ?>
                <? foreach ($study_areas as $area) : ?>
                    <?
                    $url_params['node_id'] = 'StudipStudyArea_' . $area->id;
                    ?>
                    <li>
                        <a href="<?= URLHelper::getLink('dispatch.php/search/courses', $url_params, true) ?>">
                            <?= htmlReady($area->getPath(' > ')) ?>
                        </a>
                    </li>
                <? endforeach ?>
            </ul>
        </section>
    </article>
<? endif ?>

<?
// Ausgabe der Modulzuordnung MVV
if (!empty($mvv_tree)) : ?>
    <article class="studip">
        <header>
            <h1><?= _('Modulzuordnungen') ?></h1>
        </header>
        <section>
            <ul class="collapsable css-tree">
                <?= $this->render_partial(
                    'shared/mvv_tree.php',
                    ['tree' => $mvv_tree, 'node' => 'start', 'id_sfx' => $id_sfx]
                ) ?>
            </ul>
        </section>
    </article>
<? endif; ?>

<? if (!empty($mvv_paths)) : ?>
    <article class="studip">
        <header>
            <h1><?= _('Modulzuordnungen') ?></h1>
        </header>
        <section>
            <ul class="list-unstyled">
                <? foreach ($mvv_paths as $mvv_path) : ?>
                <li>
                    <a data-dialog href="<?= URLHelper::getLink(
                        'dispatch.php/search/module/overview/'
                            . $mvv_path['modul_id'] . '/' . $course->start_semester->id,
                        [
                            'abschnitt_id' => $mvv_path['abschnitt_id'],
                        ]
                    ) ?>">
                        <?= htmlReady(implode(' > ', $mvv_path['names'])) ?>
                    </a>
                </li>
                <? endforeach ?>
            </ul>
        </section>
    </article>
<? endif; ?>

<? if (trim($course->beschreibung)) : ?>
    <article class="studip">
        <header>
            <h1><?= _("Kommentar/Beschreibung") ?></h1>
        </header>
        <section>
            <?= formatLinks($course->beschreibung) ?>
        </section>
    </article>
<? endif ?>

<? if ($courseset = $course->getCourseSet()) : ?>
    <article class="studip">
        <header>
            <h1><?=_("Anmelderegeln")?></h1>
        </header>
        <section>
            <div>
                <?= sprintf(_('Diese Veranstaltung gehört zum Anmeldeset "%s".'), htmlReady($courseset->getName())) ?>
            </div>
            <div id="courseset_<?= $courseset->getId() ?>">
                <?= $courseset->toString(true) ?>
            </div>
        </section>
    </article>
<? endif ?>

<? if ($course->admission_prelim == 1 || $course->admission_binding == 1) : ?>
    <article class="studip">
        <header>
            <h1><?= _('Anmeldemodus') ?></h1>
        </header>
        <? if ($course->admission_prelim == 1) : ?>
            <section>
                <p><?= _("Die Auswahl der Teilnehmenden wird nach der Eintragung manuell vorgenommen.") ?></p>
                <? if ($course->admission_prelim_txt) : ?>
                    <p><?= formatReady($course->admission_prelim_txt) ?></p>
                <? else : ?>
                        <p><?=
                            _("Nutzer/-innen, die sich für diese Veranstaltung eintragen möchten,
                    erhalten nähere Hinweise und können sich dann noch gegen eine Teilnahme entscheiden.")?>
                        </p>
                <? endif ?>
            </section>
        <? endif ?>
        <? if ($course->admission_binding == 1) : ?>
            <section>
                <p><?= _("Die Anmeldung ist verbindlich, Teilnehmende können sich nicht selbst austragen.") ?></p>
            </section>
        <? endif ?>
    </article>
<? endif ?>

<? if (!empty($course_domains)): ?>
    <article class="studip">
        <header>
            <h1><?= _("Zugelassenene Nutzerdomänen") ?></h1>
        </header>
        <ul>
            <? foreach ($course_domains as $domain): ?>
                <li><?= htmlReady($domain->name) ?></li>
            <? endforeach ?>
        </ul>
    </article>
<? endif ?>

<? foreach (PluginManager::getInstance()->getPlugins(DetailspagePlugin::class) as $plugin) : ?>
    <? $template = $plugin->getDetailspageTemplate($course) ?>
    <? if ($template) : ?>
        <article class="studip">
            <header>
                <h1><?= htmlReady($template->title) ?></h1>
            </header>
            <?= $template->render() ?>
        </article>
    <? endif ?>
<? endforeach ?>


<? if (count($links) > 0 || Request::get('from')) : ?>
    <footer data-dialog-button>
    <? foreach ($links as $link): ?>
        <?= Studip\LinkButton::create(
            $link['label'],
            URLHelper::getURL($link['url']),
            $link['attributes']
        ) ?>
    <? endforeach ?>
    <? if (Request::get('from')): ?>
        <?= Studip\LinkButton::createCancel(_('Zurück'), URLHelper::getURL(Request::get('from')))?>
    <? endif ?>
    </footer>
<? endif ?>
<? if (Request::bool('link_to_course') && $GLOBALS['perm']->have_studip_perm('autor', $course->id)) : ?>
    <footer data-dialog-button>
        <?= \Studip\LinkButton::create(_('Direkt zur Veranstaltung'), URLHelper::getURL('dispatch.php/course/overview', ['cid' => $course->id]))?>
    </footer>
<? endif ?>
<?= Feedback::getHTML($course->id, 'Course'); ?>
