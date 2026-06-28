<?php
$file = 'Modules\SmartStockInventory\Http\Controllers\LotController.php';
$content = file_get_contents($file);
// replace lot_number link
$content = preg_replace('/(<a\s+href=")[^"]*(" target="_blank">)/i', '${1}javascript:void(0)" class="lot-history-link" data-lot=" . e($row->lot_number) . "${2}', $content);
// replace action link
$content = preg_replace('/(<a\s+class="btn btn-xs btn-info\s+href=")[^"]*(" target="_blank"><i class="fa fa-history"><\/i>)/i', '${1}" class="btn btn-xs btn-info lot-history-btn" data-lot=" . e($row->lot_number) . "${2}', $content);
file_put_contents($file, $content);
echo "Updated\n";
