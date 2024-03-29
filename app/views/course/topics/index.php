<? if (count($topics) > 0) : ?>
<table class="default withdetails">
    <thead>
        <tr>
            <th><?= _("Thema") ?></th>
            <th><?= _("Termine") ?></th>
        </tr>
    </thead>
    <tbody>
    <? foreach ($topics as $key => $topic) : ?>
        <tr class="<?= Request::get("open") === $topic->getId() ? "open" : "" ?>">
            <td><a href="" name="<?=$topic->getId()?>" onClick="jQuery(this).closest('tr').toggleClass('open'); return false;"><?= htmlReady($topic['title']) ?></a></td>
            <td>
                <ul class="clean">
                    <? foreach ($topic->dates as $date) : ?>
                        <li>
                            <a href="<?= URLHelper::getLink("dispatch.php/course/dates/details/".$date->getId()) ?>" data-dialog="buttons=false">
                                <?= Assets::img("icons/16/blue/date", array('class' => "text-bottom")) ?>
                                <?= htmlReady($date->getFullName()) ?>
                            </a>
                        </li>
                    <? endforeach ?>
                </ul>
            </td>
        </tr>
        <tr class="details nohover">
            <td colspan="2">
                <div class="detailscontainer">
                    <table class="default nohover">
                        <tbody>
                        <tr>
                            <td><strong><?= _("Beschreibung") ?></strong></td>
                            <td><?= formatReady($topic['description']) ?></td>
                        </tr>
                        <tr>
                            <td><strong><?= _("Materialien") ?></strong></td>
                            <td>
                                <? $material = false ?>
                                <ul class="clean">
                                    <? $folder = $topic->folder ?>
                                    <? if ($documents_activated && $folder) : ?>
                                        <li>
                                            <a href="<?= URLHelper::getLink("folder.php#anker", array('data[cmd]' => "tree", 'open' => $folder->getId())) ?>">
                                                <?= Assets::img("icons/16/blue/folder-empty", array('class' => "text-bottom")) ?>
                                                <?= _("Dateiordner") ?>
                                            </a>
                                        </li>
                                        <? $material = true ?>
                                    <? endif ?>

                                    <? if ($forum_activated && ($link_to_thread = $topic->forum_thread_url)) : ?>
                                        <li>
                                            <a href="<?= URLHelper::getLink($link_to_thread) ?>">
                                                <?= Assets::img("icons/16/blue/forum", array('class' => "text-bottom")) ?>
                                                <?= _("Thema im Forum") ?>
                                            </a>
                                        </li>
                                        <? $material = true ?>
                                    <? endif ?>
                                </ul>
                                <? if (!$material) : ?>
                                    <?= _("Keine Materialien zu dem Thema vorhanden") ?>
                                <? endif ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <div style="text-align: center;">
                        <? if ($GLOBALS['perm']->have_studip_perm("tutor", $_SESSION['SessionSeminar'])) : ?>
                            <?= \Studip\LinkButton::createEdit(_('Bearbeiten'),
                                                               URLHelper::getURL("dispatch.php/course/topics/edit/".$topic->getId()),
                                                               array('data-dialog' => '')) ?>

                            <? if (!$cancelled_dates_locked && $topic->dates->count()) : ?>
                                <?= \Studip\LinkButton::create(_("Alle Termine ausfallen lassen"), URLHelper::getURL("dispatch.php/course/cancel_dates", array('issue_id' => $topic->getId())), array('data-dialog' => '')) ?>
                            <? endif ?>
                            <? if ($key > 0) : ?>
                                <form action="?" method="post" style="display: inline;">
                                    <input type="hidden" name="move_up" value="<?= $topic->getId() ?>">
                                    <input type="hidden" name="open" value="<?= $topic->getId() ?>">
                                    <?= \Studip\Button::createMoveUp(_("nach oben verschieben")) ?>
                                </form>
                            <? endif ?>
                            <? if ($key < count($topics) - 1) : ?>
                            <form action="?" method="post" style="display: inline;">
                                <input type="hidden" name="move_down" value="<?= $topic->getId() ?>">
                                <input type="hidden" name="open" value="<?= $topic->getId() ?>">
                                <?= \Studip\Button::createMoveDown(_("nach unten verschieben")) ?>
                            </form>
                            <? endif ?>
                        <? endif ?>
                    </div>
                </div>
            </td>
        </tr>
    <? endforeach ?>
    </tbody>
</table>
<? else : ?>
    <? PageLayout::postMessage(MessageBox::info(_("Keine Themen vorhanden."))) ?>
<? endif ?>

<?php
$sidebar = Sidebar::get();
$sidebar->setImage('sidebar/date-sidebar.png');

$actions = new ActionsWidget();
$actions->addLink(_("Alle Themen aufklappen"),
                  null,
                  'icons/16/blue/arr_1down.png',
                  array('onClick' => "jQuery('table.withdetails > tbody > tr:not(.details):not(.open) > :first-child a').click(); return false;"));
if ($GLOBALS['perm']->have_studip_perm("tutor", $_SESSION['SessionSeminar'])) {
    $actions->addLink(
        _("Neues Thema erstellen"),
        URLHelper::getURL("dispatch.php/course/topics/edit"),
        'icons/16/blue/add.png',
        array('data-dialog' => "buttons")
    );
    $actions->addLink(
        _("Themen aus Veranstaltung kopieren"),
        URLHelper::getURL("dispatch.php/course/topics/copy"),
        'icons/16/blue/add/topic.png',
        array('data-dialog' => "buttons")
    );
}
$sidebar->addWidget($actions);

