<style>
    .themen_list, .dozenten_list {
        margin: 0px;
        padding: 0px;
        list-style-type: none;
    }
</style>
<?php
    $lastSemester = null;
    $allSemesters = array();
    foreach ($dates as $key => $date) {
        $currentSemester = Semester::findByTimestamp($date['date']);
        if ($currentSemester && (
            !$lastSemester ||
            $currentSemester->getId() !== $lastSemester->getId()
        )) {
            $allSemesters[] = $currentSemester;
            $lastSemester = $currentSemester;
        }
    }
    $lostDateKeys = array();

    if (!count($dates)) {
        PageLayout::postMessage(
            MessageBox::info(_('Keine Termine vorhanden'))
        );
    }

    foreach ($allSemesters as $semester) {
?>
<table class="dates default">
    <caption><?= htmlReady($semester['name']) ?></caption>
    <thead>
        <tr class="sortable">
            <th class="sortasc"><?= _('Zeit') ?></th>
            <th><?= _('Typ') ?></th>
            <th><?= _('Thema') ?></th>
            <th><?= _('Raum') ?></th>
        </tr>
    </thead>
    <tbody>
    <?php
        // print dates
        foreach ($dates as $key => $date) {
            $dateSemester = Semester::findByTimestamp($date['date']);
            if ($dateSemester &&
                $semester->getId() === $dateSemester->getId()
            ) {
                 if (is_null($is_next_date) && $date['end_time'] >= time() && !is_a($date, "CourseExDate")) {
                     $is_next_date = $key;
                 }
                 echo $this->render_partial(
                    'course/dates/_date_row.php',
                    array('date' => $date, 'is_next_date' => $is_next_date === $key)
                );
            } elseif (!$dateSemester && !in_array($key, $lostDateKeys)) {
                $lostDateKeys[] = $key;
            }
        }
    ?>
    </tbody>
</table>
<?php
    }

    if (count($lostDateKeys)) {
?>
<table class="dates default">
    <caption><?= _('Ohne Semester') ?></caption>
    <thead>
    <tr class="sortable">
        <th class="sortasc"><?= _('Zeit') ?></th>
        <th><?= _('Typ') ?></th>
        <th><?= _('Thema') ?></th>
        <th><?= _('Raum') ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
        foreach ($lostDateKeys as $key) {
            $date = $dates[$key];
            echo $this->render_partial(
                'course/dates/_date_row.php',
                compact('date', 'dates', 'key')
            );
        }
    ?>
    </tbody>
</table>
<?php
    }
?>
<script>
jQuery(function($) {
    $('.dates').tablesorter({
        textExtraction: function(node) {
            var $node = $(node);
            return String($node.data('timestamp') || $node.text()).trim();
        },
        cssAsc: 'sortasc',
        cssDesc: 'sortdesc',
        sortList: [[0, 0]],
    });
});
</script>
<?php
$sidebar = Sidebar::get();
$sidebar->setImage('sidebar/date-sidebar.png');

$actions = new ActionsWidget();
$actions->addLink(
    _('Als Doc-Datei runterladen'),
    URLhelper::getURL('dispatch.php/course/dates/export'),
    'icons/16/blue/file-word.png'
);
$sidebar->addWidget($actions);
