<style>
  @font-face {
    font-family: 'Khmer OS Battambang';
    src: url('{{ 'file://' . storage_path("fonts/KhmerOSbattambang.ttf") }}') format("truetype");
    font-weight: normal;
    font-style: normal;
  }

  @font-face {
    font-family: 'NotoSansKhmer';
    src: url('{{ 'file://' . storage_path("fonts/NotoSansKhmer-Regular.ttf") }}') format("truetype");
    font-weight: normal;
    font-style: normal;
  }

  body, p, strong, td, th, h1, h2, h3, h4, h5 {
    font-family: 'Khmer OS Battambang', 'NotoSansKhmer', DejaVu Sans, sans-serif;
  }

  @media print {
    table.table.bg-gray > tbody > tr > td,
    table.table.bg-gray > tbody > tr > th,
    table.table.bg-gray > tfoot > tr > td,
    table.table.bg-gray > tfoot > tr > th {
      padding: 4px 6px !important;
    }

    .lot-row td {
      padding: 2px 6px !important;
      border-top: none !important;
    }

    .lot-full {
      width: 100% !important;
      font-size: 9px !important;
      line-height: 1.1 !important;
    }

    .lot-list {
      width: 100% !important;
      display: flex !important;
      flex-wrap: wrap !important;
      gap: 2px 4px !important;
      margin-top: 2px !important;
    }

    .lot-badge {
      font-size: 8px !important;
      line-height: 1 !important;
      padding: 1px 4px !important;
      border: 1px solid #ccc !important;
      border-radius: 8px !important;
      white-space: nowrap !important;
    }

    .purchase-total-row td {
      font-weight: bold !important;
      font-size: 11px !important;
    }
  }
</style>

<div class="row">
  <div class="col-xs-12">
    <h2 class="page-header">
      @lang('lang_v1.stock_transfers') (<b>@lang('purchase.ref_no'):</b> #{{ $sell_transfer->ref_no }})
      <small class="pull-right"><b>@lang('messages.date'):</b> {{ @format_date($sell_transfer->transaction_date) }}</small>
    </h2>
  </div>
</div>
<div class="row invoice-info">
  <div class="col-sm-4 invoice-col">
    @lang('lang_v1.location_from'):
    <address>
      <strong>{{ $location_details['sell']->name }}</strong>
    </address>
  </div>

  <div class="col-md-4 invoice-col">
    @lang('lang_v1.location_to'):
    <address>
      <strong>{{ $location_details['purchase']->name }}</strong>
    </address>
  </div>

  <div class="col-sm-4 invoice-col">
    <b>@lang('purchase.ref_no'):</b> #{{ $sell_transfer->ref_no }}<br/>
    <b>@lang('messages.date'):</b> {{ @format_date($sell_transfer->transaction_date) }}<br/>
  </div>
</div>

<br>
<div class="row">
  <div class="col-xs-12">
    <div class="table-responsive">
      <table class="table bg-gray">
        <tr class="bg-green">
          <th>#</th>
          <th>@lang('sale.product')</th>
          <th>@lang('sale.qty')</th>
          <th class="show_price_with_permission">@lang('sale.subtotal')</th>
        </tr>
        @php
          $total = 0.00;
          $purchase_total_qty = 0.0;
          $purchase_total_unit_names = [];
          $grouped_lines = [];

          foreach ($sell_transfer->sell_lines as $line) {
            $unit_id = !empty($line->sub_unit_id) ? $line->sub_unit_id : ($line->product->unit_id ?? '');
            $key = $line->product_id . '_' . $line->variation_id . '_' . $unit_id;

            $display_unit = '';
            if (!empty($line->sub_unit)) {
              $display_unit = $line->sub_unit->short_name;
            } elseif (!empty($line->product) && !empty($line->product->unit)) {
              $display_unit = $line->product->unit->short_name;
            }
            if ($display_unit !== '') {
              $purchase_total_unit_names[] = $display_unit;
            }

            if (!isset($grouped_lines[$key])) {
              $product_name = $line->product->name ?? '';
              $variation_text = '';
              if (!empty($line->product) && $line->product->type == 'variable') {
                $pv_name = $line->variations->product_variation->name ?? '';
                $v_name = $line->variations->name ?? '';
                $variation_text = implode(' - ', array_filter([$pv_name, $v_name]));
              }
              $sku = $line->variations->sub_sku ?? '';

              $grouped_lines[$key] = [
                'product_name' => $product_name,
                'variation_text' => $variation_text,
                'sub_sku' => $sku,
                'unit' => $display_unit,
                'quantity' => 0.0,
                'unit_price' => (float) ($line->unit_price_inc_tax ?? 0),
                'subtotal' => 0.0,
                'lots' => [],
              ];
            }

            $qty = (float) ($line->quantity ?? 0);
            $purchase_total_qty += $qty;

            $grouped_lines[$key]['quantity'] += $qty;
            $line_subtotal = (float) ($line->unit_price_inc_tax ?? 0) * $qty;
            $grouped_lines[$key]['subtotal'] += $line_subtotal;
            $total += $line_subtotal;

            if (!empty($lot_n_exp_enabled) && !empty($line->lot_details)) {
              $lot_no = $line->lot_details->lot_number ?? '';
              $exp_date = $line->lot_details->exp_date ?? null;
              if ($lot_no !== '' || !empty($exp_date)) {
                $grouped_lines[$key]['lots'][] = ['lot_number' => $lot_no, 'exp_date' => $exp_date];
              }
            }
          }

          $purchase_total_unit_names = array_values(array_unique(array_filter($purchase_total_unit_names)));
          $purchase_total_unit = count($purchase_total_unit_names) === 1 ? $purchase_total_unit_names[0] : 'Pc(s)';
        @endphp

        @foreach($grouped_lines as $group)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>
              {{ $group['product_name'] }}
              @if(!empty($group['variation_text']))
                - {{ $group['variation_text'] }}
              @endif
              @if(!empty($group['sub_sku']))
                - {{ $group['sub_sku'] }}
              @endif
            </td>
            <td>{{ @format_quantity($group['quantity']) }} {{ $group['unit'] }}</td>
            <td class="show_price_with_permission">
              <span class="display_currency" data-currency_symbol="true">{{ $group['subtotal'] }}</span>
            </td>
          </tr>

          @if(!empty($group['lots']))
            <tr class="lot-row">
              <td colspan="4">
                <div class="lot-full">
                  <strong>Lot &amp; Expiry:</strong>
                  <div class="lot-list">
                    @foreach($group['lots'] as $lot)
                      @php
                        $lot_label = '';
                        if (!empty($lot['lot_number'])) {
                          $lot_label .= $lot['lot_number'];
                        }
                        if (!empty($lot['exp_date'])) {
                          $lot_label .= ($lot_label !== '' ? ' - ' : '') . @format_date($lot['exp_date']);
                        }
                      @endphp
                      @if($lot_label !== '')
                        <span class="lot-badge">{{ $lot_label }}</span>
                      @endif
                    @endforeach
                  </div>
                </div>
              </td>
            </tr>
          @endif
        @endforeach

        <tr class="purchase-total-row">
          <td colspan="4">
            Purchase Total: {{ @format_quantity($purchase_total_qty) }} {{ $purchase_total_unit }}
          </td>
        </tr>
      </table>
    </div>
  </div>
</div>
<br>
<div class="row">
  
  <div class="col-xs-6">
    <div class="table-responsive">
      <table class="table show_price_with_permission">
        <tr>
          <th >@lang('purchase.net_total_amount'): </th>
          <td></td>
          <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $total }}</span></td>
        </tr>
        @if( !empty( $sell_transfer->shipping_charges ) )
          <tr>
            <th>@lang('purchase.additional_shipping_charges'):</th>
            <td><b>(+)</b></td>
            <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell_transfer->shipping_charges }}</span></td>
          </tr>
        @endif
        <tr>
          <th>@lang('purchase.purchase_total'):</th>
          <td></td>
          <td><span class="display_currency pull-right" data-currency_symbol="true" >{{ $sell_transfer->final_total }}</span></td>
        </tr>
      </table>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-sm-6">
    <strong>@lang('purchase.additional_notes'):</strong><br>
    <p class="well well-sm no-shadow bg-gray">
      @if($sell_transfer->additional_notes)
        {{ $sell_transfer->additional_notes }}
      @else
        --
      @endif
    </p>
  </div>
</div>

{{-- Barcode --}}
<div class="row print_section">
  <div class="col-xs-12">
    <img class="center-block" src="data:image/png;base64,{{DNS1D::getBarcodePNG($sell_transfer->ref_no, 'C128', 2,30,array(39, 48, 54), true)}}">
  </div>
</div>
