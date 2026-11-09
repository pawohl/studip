<editable-list name="<?= htmlReady($name) ?>"
               quicksearch="<?= htmlReady((string) $searchtype) ?>"
               :items="<?= htmlReady(json_encode($selected_items)) ?>"
               :selectable="<?= htmlReady(json_encode($selectable)) ?>"
               :category_order="<?= htmlReady(json_encode($category_order)) ?>"
               @input="output => <?= htmlReady($name) ?> = output">
</editable-list>
