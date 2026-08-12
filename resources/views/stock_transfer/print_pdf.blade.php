@php
    $sell_transfer = $sell_transfer ?? ($transfer ?? null);

    if (! empty($sell_transfer)) {
        $sell_transfer->loadMissing([
            'sell_lines',
            'sell_lines.product',
            'sell_lines.product.unit',
            'sell_lines.variations',
            'sell_lines.variations.product_variation',
            'sell_lines.lot_details',
            'sell_lines.sell_line_purchase_lines.purchase_line',
            'sell_lines.sub_unit',
            'location',
            'transferParent.location',
        ]);
    }

    $business = null;
    if (! empty($sell_transfer) && ! empty($sell_transfer->business_id)) {
        $business = \App\Business::with('currency')->find($sell_transfer->business_id);
    }

    if (! empty($business)) {
        session(['business' => $business]);

        if (! empty($business->currency)) {
            session(['currency' => [
                'id' => $business->currency->id,
                'code' => $business->currency->code,
                'symbol' => $business->currency->symbol,
                'thousand_separator' => $business->currency->thousand_separator,
                'decimal_separator' => $business->currency->decimal_separator,
            ]]);
        }
    }

    $purchase_transfer = $purchase_transfer ?? ($sell_transfer->transferParent ?? null);
    $location_details = $location_details ?? [
        'sell' => $sell_transfer->location ?? null,
        'purchase' => $purchase_transfer->location ?? null,
    ];

    if (! isset($lot_n_exp_enabled)) {
        $lot_n_exp_enabled = ! empty($business)
            && ((int) $business->enable_lot_number === 1 || (int) $business->enable_product_expiry === 1);
    }

    $is_pdf = true;
@endphp

<!DOCTYPE html>
<html lang="km">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Stock Transfer {{ $sell_transfer->ref_no ?? '' }}</title>
    <style>
      @page { size: A4; margin: 8mm; }

      @font-face {
        font-family: 'Khmer OS Battambang';
        src: url('fonts/KhmerOSbattambang.ttf') format("truetype");
        font-weight: normal;
        font-style: normal;
      }

      @font-face {
        font-family: 'NotoSansKhmer';
        src: url('fonts/NotoSansKhmer-Regular.ttf') format("truetype");
        font-weight: normal;
        font-style: normal;
      }

      html, body, * {
        font-family: 'Khmer OS Battambang', 'NotoSansKhmer', DejaVu Sans, sans-serif !important;
        font-size: 12px;
        color: #111;
      }

      body { margin: 0; }
      .row { width: 100%; clear: both; }
      .col-xs-12 { width: 100%; }
      .col-xs-6, .col-sm-6 { width: 50%; float: left; }
      .col-sm-4, .col-md-4 { width: 33.333333%; float: left; }
      .pull-right { float: right; }
      .center-block { display: block; margin-left: auto; margin-right: auto; }
      .page-header { margin: 0 0 12px; padding-bottom: 8px; border-bottom: 1px solid #ddd; font-size: 20px; }
      .invoice-info { margin-bottom: 8px; }
      .table-responsive { width: 100%; }
      table.table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
      table.table th, table.table td { border: 1px solid #bbb; padding: 4px 6px; vertical-align: top; }
      table.table th { font-weight: bold; }
      .bg-gray { background: #f7f7f7; }
      .bg-green { background: #dff0d8; }
      .well { border: 1px solid #ddd; padding: 8px; min-height: 28px; }
      .no-shadow { box-shadow: none; }
      address { margin: 4px 0; font-style: normal; }
      .lot-row td { padding: 2px 6px; border-top: none; }
      .lot-full { width: 100%; font-size: 9px; line-height: 1.1; }
      .lot-list { width: 100%; margin-top: 2px; }
      .lot-badge { font-size: 8px; line-height: 1; padding: 1px 4px; border: 1px solid #ccc; border-radius: 8px; white-space: nowrap; display: inline-block; margin: 1px 2px; }
      .purchase-total-row td { font-weight: bold; font-size: 11px; }
      .print_section img.center-block { max-width: 100%; width: 280px; height: auto; }
    </style>
  </head>
  <body>
    @if(! empty($sell_transfer))
      @include('stock_transfer.print')
    @else
      <h2>Stock Transfer</h2>
      <p>No transfer data found.</p>
    @endif
  </body>
</html>
