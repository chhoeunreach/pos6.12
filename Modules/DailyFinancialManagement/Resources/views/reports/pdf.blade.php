<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KNEAYERNG Financial Report - {{ $date }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 14px;
            margin: 4px 0 0 0;
            color: #475569;
        }
        .header p {
            font-size: 10px;
            color: #64748b;
            margin: 4px 0 0 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .bg-highlight { background-color: #e0f2fe; }
        .bg-profit { background-color: #dcfce7; }
        .text-green { color: #15803d; }
        .text-red { color: #b91c1c; }
        .signatures {
            margin-top: 40px;
            width: 100%;
        }
        .signatures td {
            border: none;
            padding-top: 40px;
            text-align: center;
            width: 33.33%;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>KNEAYERNG PHONE SHOP</h1>
        <h2>DAILY EXECUTIVE FINANCIAL STATEMENT</h2>
        <p>Date: {{ $date }} | Currency: USD ($)</p>
    </div>

    <!-- Profit & Loss Summary -->
    <table>
        <thead>
            <tr>
                <th colspan="2" style="background-color: #1e3a8a; color: #fff;">1. STATEMENT OF PROFIT AND LOSS</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Gross POS Retail Sales</td>
                <td class="text-right font-bold">${{ number_format($pnl['gross_sales'], 2) }}</td>
            </tr>
            <tr>
                <td>Less: Sales Discounts & Allowances</td>
                <td class="text-right text-red">-${{ number_format($pnl['sales_discounts'], 2) }}</td>
            </tr>
            <tr>
                <td>Less: Sales Returns</td>
                <td class="text-right text-red">-${{ number_format($pnl['sales_returns'], 2) }}</td>
            </tr>
            <tr class="bg-highlight">
                <td class="font-bold">NET COMMERCIAL SALES</td>
                <td class="text-right font-bold">${{ number_format($pnl['net_sales'], 2) }}</td>
            </tr>
            <tr>
                <td>Cost of Goods Sold (COGS)</td>
                <td class="text-right text-red">-${{ number_format($pnl['cogs'], 2) }}</td>
            </tr>
            <tr class="bg-highlight">
                <td class="font-bold">GROSS PROFIT (Margin: {{ $pnl['gross_margin_percent'] }}%)</td>
                <td class="text-right font-bold text-green">${{ number_format($pnl['gross_profit'], 2) }}</td>
            </tr>
            <tr>
                <td>Recognized Installment Interest Income</td>
                <td class="text-right text-green">+${{ number_format($pnl['loan_interest_income'], 2) }}</td>
            </tr>
            <tr>
                <td>Other Manual Operating Income</td>
                <td class="text-right text-green">+${{ number_format($pnl['manual_income'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Operating Expenses (OPEX)</td>
                <td class="text-right text-red">-${{ number_format($pnl['operating_expenses'], 2) }}</td>
            </tr>
            <tr class="bg-profit">
                <td class="font-bold" style="font-size: 13px;">NET OPERATING PROFIT (Net Margin: {{ $pnl['net_margin_percent'] }}%)</td>
                <td class="text-right font-bold text-green" style="font-size: 13px;">${{ number_format($pnl['net_profit'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Cash Flow Summary -->
    <table>
        <thead>
            <tr>
                <th colspan="2" style="background-color: #3b82f6; color: #fff;">2. STATEMENT OF CASH FLOWS</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>POS Cash Inflow</td>
                <td class="text-right text-green">+${{ number_format($cashFlow['pos_cash_collections'], 2) }}</td>
            </tr>
            <tr>
                <td>POS Bank Inflow (ABA, ACLEDA, Wing)</td>
                <td class="text-right text-green">+${{ number_format($cashFlow['pos_bank_collections'], 2) }}</td>
            </tr>
            <tr>
                <td>Installment Principal Collections (Debt Recoveries)</td>
                <td class="text-right text-green">+${{ number_format($cashFlow['loan_principal_collections'], 2) }}</td>
            </tr>
            <tr>
                <td>Installment Interest Collections</td>
                <td class="text-right text-green">+${{ number_format($cashFlow['loan_interest_collections'], 2) }}</td>
            </tr>
            <tr class="bg-highlight">
                <td class="font-bold">TOTAL CASH & BANK INFLOWS</td>
                <td class="text-right font-bold text-green">+${{ number_format($cashFlow['total_inflows'], 2) }}</td>
            </tr>
            <tr>
                <td>Operating Expenses Paid</td>
                <td class="text-right text-red">-${{ number_format($cashFlow['operating_expense_outflows'], 2) }}</td>
            </tr>
            <tr>
                <td>Inventory Purchases Paid</td>
                <td class="text-right text-red">-${{ number_format($cashFlow['inventory_purchase_outflows'], 2) }}</td>
            </tr>
            <tr class="bg-highlight">
                <td class="font-bold">TOTAL CASH & BANK OUTFLOWS</td>
                <td class="text-right font-bold text-red">-${{ number_format($cashFlow['total_outflows'], 2) }}</td>
            </tr>
            <tr class="bg-profit">
                <td class="font-bold">NET CASH FLOW (Change in Liquidity)</td>
                <td class="text-right font-bold {{ $cashFlow['net_cash_flow'] >= 0 ? 'text-green' : 'text-red' }}">
                    {{ $cashFlow['net_cash_flow'] >= 0 ? '+$' : '-$' }}{{ number_format(abs($cashFlow['net_cash_flow']), 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Liquid Reserves -->
    <table>
        <thead>
            <tr>
                <th colspan="2" style="background-color: #059669; color: #fff;">3. LIQUID ASSET RESERVES</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Physical Cash Balance (Vaults & Registers)</td>
                <td class="text-right font-bold">${{ number_format($reconciliation['summary']['expected_cash'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Bank & Digital Balances (ABA, ACLEDA, Wing)</td>
                <td class="text-right font-bold">${{ number_format($reconciliation['summary']['expected_bank'], 2) }}</td>
            </tr>
            <tr class="bg-profit">
                <td class="font-bold">TOTAL VERIFIED LIQUID RESERVES</td>
                <td class="text-right font-bold text-green">${{ number_format($reconciliation['summary']['total_liquid_assets'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                ____________________________<br>
                <strong>Cashier / Prepared By</strong>
            </td>
            <td>
                ____________________________<br>
                <strong>Accountant / Reviewed By</strong>
            </td>
            <td>
                ____________________________<br>
                <strong>Branch Manager / Director</strong>
            </td>
        </tr>
    </table>
</body>
</html>

