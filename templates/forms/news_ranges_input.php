<editable-list name="<?= htmlReady($name) ?>"
               quicksearch="<?= htmlReady((string) $searchtype) ?>"
               :items="<?= htmlReady(json_encode($items)) ?>"
               :selectable="<?= htmlReady(json_encode($selectable)) ?>"
               :category_order="<?= htmlReady(json_encode($category_order)) ?>"
               :required="STUDIPFORM_REQUIRED.indexOf('<?= htmlReady($name) ?>') !== -1"
               label="<?= htmlReady($title) ?>"
               @input="output => <?= htmlReady($name) ?> = output">
</editable-list>
