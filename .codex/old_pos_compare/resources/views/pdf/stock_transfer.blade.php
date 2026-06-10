<!doctype html>
<html lang="km">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Transfer</title>
    <style>
      /* Khmer font support (wkhtmltopdf needs local file access enabled). */
      @font-face {
        font-family: 'Khmer OS Battambang';
        src: url('fonts/KhmerOSbattambang.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
      }

      html, body, * {
        font-family: 'Khmer OS Battambang', DejaVu Sans, sans-serif;
        font-size: 12px;
        color: #111;
      }

      .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
      }

      .title {
        font-size: 18px;
        font-weight: 700;
        margin: 0;
      }

      .meta {
        font-size: 12px;
        line-height: 1.4;
      }

      table {
        width: 100%;
        border-collapse: collapse;
      }

      th, td {
        border: 1px solid #222;
        padding: 6px 8px;
        vertical-align: top;
      }

      th {
        background: #f3f3f3;
      }

      .text-right {
        text-align: right;
      }

      .muted {
        color: #666;
        font-size: 11px;
      }

      @page { size: A4; margin: 8mm; }
    </style>
  </head>
  <body>
    <div class="header">
      <div>
        <p class="title">📦 Stock Transfer</p>
        <div class="meta">
          <div><strong>Ref No:</strong> {{ $sell_transfer->ref_no ?? '' }}</div>
          <div><strong>Date:</strong> {{ !empty($sell_transfer->transaction_date) ? \Carbon\Carbon::parse($sell_transfer->transaction_date)->format('Y-m-d H:i') : '' }}</div>
          <div><strong>From:</strong> {{ $location_details['sell']->name ?? '' }}</div>
          <div><strong>To:</strong> {{ $location_details['purchase']->name ?? '' }}</div>
        </div>
      </div>
      <div class="meta text-right">
        <div class="muted">Ultimate POS</div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width: 40px;">#</th>
          <th>Product</th>
          <th style="width: 120px;">Qty</th>
          <th style="width: 140px;">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        @php($totalQty = 0.0)
        @foreach(($sell_transfer->sell_lines ?? []) as $i => $line)
          @php($qty = (float) ($line->quantity ?? 0))
          @php($totalQty += $qty)
          <tr>
            <td>{{ $i + 1 }}</td>
            <td>
              {{ $line->product->name ?? '' }}
              @if(!empty($line->variations) && !empty($line->variations->sub_sku))
                <div class="muted">{{ $line->variations->sub_sku }}</div>
              @endif
              @if(!empty($lot_n_exp_enabled) && !empty($line->lot_details))
                <div class="muted">
                  Lot: {{ $line->lot_details->lot_number ?? '-' }}
                  @if(!empty($line->lot_details->exp_date))
                    - Exp: {{ @format_date($line->lot_details->exp_date) }}
                  @endif
                </div>
              @endif
            </td>
            <td class="text-right">{{ number_format($qty, 2) }} {{ $line->sub_unit->short_name ?? ($line->product->unit->short_name ?? 'Pc(s)') }}</td>
            <td class="text-right">{{ number_format((float) ($line->unit_price_inc_tax ?? 0) * $qty, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2" class="text-right">Purchase Total:</th>
          <th class="text-right">{{ number_format($totalQty, 2) }}</th>
          <th></th>
        </tr>
      </tfoot>
    </table>
  </body>
</html>
