<style>
    .themen_list, .dozenten_list {
        margin: 0px;
        padding: 0px;
        list-style-type: none;
    }
    #dates .themen_list .remove_topic {
        display: none;
    }
</style>

<table class="default" id="dates">
    <thead>
        <tr>
            <th></th>
            <th><?= _("Zeit") ?></th>
            <th><?= _("Typ") ?></th>
            <th><?= _("Thema") ?></th>
            <th><?= _("Raum") ?></th>
        </tr>
    </thead>
    <tbody>
        <? if (count($dates)) : ?>
            <? $lastSemester = null ?>
            <? foreach ($dates as $key => $date) : ?>
                <? $currentSemester = Semester::findByTimestamp($date['date']) ?>
                <? if (!$lastSemester || ($currentSemester->getId() !== $lastSemester->getId())) : ?>
                <tr class="nohover">
                    <td colspan="5">
                        <h4><?= htmlReady($currentSemester['name']) ?></h4>
                    </td>
                </tr>
                    <? $lastSemester = $currentSemester ?>
                <? endif ?>
                <?= $this->render_partial("course/dates/_date_row.php", compact("date", "dates", "key")) ?>
            <? endforeach ?>
        <? else : ?>
        <tr>
            <td colspan="5" style="text-align: center;"><?= _("Keine Termine vorhanden") ?></td>
        </tr>
        <? endif ?>
    </tbody>
</table>


<?php
$sidebar = Sidebar::get();
$sidebar->setImage(Assets::image_path("sidebar/date-sidebar.png"));

$actions = new ActionsWidget();
$actions->addLink(
    _("Exportieren"),
    URLhelper::getURL("dispatch.php/course/dates/export")
);
$sidebar->addWidget($actions);