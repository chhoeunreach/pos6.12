<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$transferId = 1416281;
$sell = App\Transaction::where('id',$transferId)->where('type','sell_transfer')->first();
if(!$sell){ echo "sell_transfer not found\n"; exit; }
$purchase = App\Transaction::where('transfer_parent_id',$sell->id)->where('type','purchase_transfer')->first();
if(!$purchase){ echo "purchase_transfer not found\n"; exit; }

echo "sell_transfer_id={$sell->id}, status={$sell->status}, location_id={$sell->location_id}\n";
echo "purchase_transfer_id={$purchase->id}, status={$purchase->status}, location_id={$purchase->location_id}\n";

$pls = App\PurchaseLine::where('transaction_id',$purchase->id)->get();
foreach($pls as $pl){
    echo "purchase_line_id={$pl->id}, product_id={$pl->product_id}, variation_id={$pl->variation_id}, qty={$pl->quantity}, qty_sold={$pl->quantity_sold}\n";
}

$ids = $pls->pluck('id')->all();
if(!empty($ids)){
    $maps = App\TransactionSellLinesPurchaseLines::whereIn('purchase_line_id',$ids)->get();
    foreach($maps as $m){
        $sl = App\TransactionSellLine::find($m->sell_line_id);
        $tx = $sl ? App\Transaction::find($sl->transaction_id) : null;
        echo "map_id={$m->id}, purchase_line_id={$m->purchase_line_id}, sell_line_id={$m->sell_line_id}, mapped_qty={$m->quantity}, tx_id=".($tx->id??'').", tx_type=".($tx->type??'').", tx_status=".($tx->status??'')."\n";
    }
}
