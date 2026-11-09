<div style="padding-bottom: 20px;">
<? $bc_points = $breadcrumb->getTrail(); ?>
<? $sum_points = count($bc_points) - 1; ?>
<? $index = 0; ?>
<? foreach($bc_points as $type => $point):?>
    <? $id2 = array_values((array) ($point['add'] ?? []))[0] ?? null; ?>
    <? $link = $controller->action_link('' . $point['actn'], $point['id'] ?? null, $id2); ?>
    <? if (isset($point['add']) && is_array($point['add'])) : ?>
        <? $mvv_object = $type::find($point['id']); ?>
        <? if ($mvv_object && $type == 'Fach' && $additional_object = Abschluss::find($point['add']['Abschluss'])) : ?>
            <a href="<?= $link ?>"><?= htmlReady($mvv_object->getDisplayName() . ' (' . $additional_object->name . ')') ?></a>
        <? endif; ?>
        <? if ($mvv_object && $type == 'StgteilBezeichnung' && $additional_object = StudiengangTeil::find($point['add']['StudiengangTeil'])) : ?>
            <a href="<?= $link ?>"><?= htmlReady($mvv_object->getDisplayName() . ': ' . $additional_object->getDisplayName()) ?></a>
        <? endif; ?>
    <? else : ?>
        <? if ($type == 'StudiengangTeil' && $mvv_object = $type::find($point['id'])) : ?>
            <a href="<?= $link ?>"><?= htmlReady($mvv_object->getDisplayName()) ?></a>
        <? elseif (!empty($point['id']) && $mvv_object = $type::find($point['id'])) : ?>
            <a href="<?= $link ?>"><?= htmlReady($mvv_object->getDisplayName(0)) ?></a>
        <? else : ?>
            <a href="<?= $link ?>"><?= htmlReady($point['name']) ?></a>
        <? endif; ?>
    <? endif; ?>
    <? if ($point['actn'] == $controller->action) break; ?>
    <?= $index++ < $sum_points ? '>' : null; ?>
<? endforeach; ?>
</div>
