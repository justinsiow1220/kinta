<?php
echo view('admin/partials/master-data/page', [
    'title' => $title,
    'kind' => 'sales-orders',
    'fields' => $fields,
    'rows' => $rows,
    'editRecord' => $editRecord ?? null,
    'sectors' => $sectors ?? [],
]);
?>
