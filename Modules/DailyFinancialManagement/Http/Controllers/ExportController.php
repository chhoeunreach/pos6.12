<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\DailyFinancialManagement\Entities\FinancialDailyClosing;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;
use Modules\DailyFinancialManagement\Services\FinancialCalculationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    protected FinancialCalculationService $calcService;

    public function __construct(FinancialCalculationService $calcService)
    {
        $this->calcService = $calcService;
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $businessId = session('business.id', 1);
        $type = $request->input('type', 'transactions');
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $fileName = "KNEAYERNG_{$type}_{$startDate}_to_{$endDate}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($businessId, $type, $startDate, $endDate, $locationId) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel Khmer font rendering
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($type === 'profit_loss') {
                $pnl = $this->calcService->calculateProfitAndLoss($businessId, $startDate, $endDate, $locationId);

                fputcsv($handle, ['KNEAYERNG PHONE SHOP - PROFIT & LOSS REPORT']);
                fputcsv($handle, ["Period: {$startDate} to {$endDate}"]);
                fputcsv($handle, []);
                fputcsv($handle, ['Metric', 'Amount (USD)', '% of Net Sales']);
                fputcsv($handle, ['Gross POS Sales', number_format($pnl['gross_sales'], 2), '']);
                fputcsv($handle, ['Less: Sales Discounts', number_format($pnl['sales_discounts'], 2), '']);
                fputcsv($handle, ['Less: Sales Returns', number_format($pnl['sales_returns'], 2), '']);
                fputcsv($handle, ['Net Sales Revenue', number_format($pnl['net_sales'], 2), '100.00%']);
                fputcsv($handle, ['Cost of Goods Sold (COGS)', number_format($pnl['cogs'], 2), '']);
                fputcsv($handle, ['Gross Profit', number_format($pnl['gross_profit'], 2), "{$pnl['gross_margin_percent']}%"]);
                fputcsv($handle, ['Installment Interest Income', number_format($pnl['loan_interest_income'], 2), '']);
                fputcsv($handle, ['Other Operating Income', number_format($pnl['manual_income'], 2), '']);
                fputcsv($handle, ['Total Recognized Income', number_format($pnl['total_recognized_income'], 2), '']);
                fputcsv($handle, ['Operating Expenses (OPEX)', number_format($pnl['operating_expenses'], 2), '']);
                fputcsv($handle, ['Net Operating Profit (EBIT)', number_format($pnl['net_profit'], 2), "{$pnl['net_margin_percent']}%"]);
            } else {
                // Transactions Ledger Export
                fputcsv($handle, [
                    'Date',
                    'Ref No',
                    'Type',
                    'Category',
                    'Branch Location',
                    'Description',
                    'Amount',
                    'Currency',
                    'Base Amount (USD)',
                    'Payment Method',
                    'Approval Status',
                    'Created By',
                ]);

                $txs = FinancialTransaction::with(['category', 'location', 'creator'])
                    ->where('business_id', $businessId)
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->when($locationId, fn ($q) => $q->where('business_location_id', $locationId))
                    ->orderBy('transaction_date', 'asc')
                    ->cursor();

                foreach ($txs as $tx) {
                    fputcsv($handle, [
                        $tx->transaction_date->format('Y-m-d'),
                        $tx->ref_no,
                        strtoupper($tx->transaction_type),
                        $tx->category ? $tx->category->name_en : 'N/A',
                        $tx->location ? $tx->location->name : 'N/A',
                        $tx->description,
                        $tx->amount,
                        $tx->currency,
                        $tx->amount_in_base_currency,
                        $tx->payment_method,
                        strtoupper($tx->approval_status),
                        $tx->creator ? $tx->creator->user_full_name : 'System',
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $businessId = session('business.id', 1);
        $type = $request->input('type', 'executive');
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));
        $startDate = $request->input('start_date', $date);
        $endDate = $request->input('end_date', $date);
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $pnl = $this->calcService->calculateProfitAndLoss($businessId, $startDate, $endDate, $locationId);
        $cashFlow = $this->calcService->calculateCashFlow($businessId, $startDate, $endDate, $locationId);
        $reconciliation = $this->calcService->calculateReconciliation($businessId, $date, $locationId);

        $closing = FinancialDailyClosing::with(['location', 'closer', 'approver'])
            ->where('business_id', $businessId)
            ->where('closing_date', $date)
            ->when($locationId, fn ($q) => $q->where('business_location_id', $locationId))
            ->first();

        $pdf = Pdf::loadView('dailyfinancialmanagement::reports.pdf', compact(
            'type',
            'date',
            'startDate',
            'endDate',
            'pnl',
            'cashFlow',
            'reconciliation',
            'closing'
        ));

        return $pdf->download("KNEAYERNG_{$type}_{$date}.pdf");
    }
}

