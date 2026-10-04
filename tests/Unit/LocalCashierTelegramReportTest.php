<?php

namespace Tests\Unit;

use Modules\LocalCashierReport\Support\TelegramReport;
use PHPUnit\Framework\TestCase;

class LocalCashierTelegramReportTest extends TestCase
{
    public function test_message_uses_full_summaries_and_includes_module_payments_once(): void
    {
        $report = [
            'rows_by_location' => [
                ['customer_groups' => [
                    ['name' => 'លក់', 'qty_total' => 2, 'payments' => ['cash' => 100, 'custom_pay_2' => 200]],
                    ['name' => 'Collection Payment', 'payments' => ['custom_pay_2' => 10]],
                    ['name' => 'Customer Payment', 'payments' => ['cash' => 5]],
                ]],
                ['customer_groups' => [
                    ['name' => 'លក់', 'qty_total' => 1, 'payments' => ['cash' => 20]],
                ]],
            ],
            'module_dashboard_rows' => [
                ['label' => 'Accessory', 'payments' => ['cash' => 3.5, 'custom_pay_2' => 9.5]],
                ['label' => 'Service', 'payments' => ['custom_pay_2' => 100]],
            ],
            'payment_with_expenses' => ['cash' => 125, 'custom_pay_2' => 210, 'expenses' => 52.5],
            'expense_payment_summary' => ['cash' => 2.5, 'custom_pay_6' => 50],
            'expense_detail_rows' => [['note' => 'Delivery', 'amount' => 52.5]],
            'detail_meta' => ['expense_total' => 1001],
            'grand_sell_return' => 10,
        ];
        $text = $this->build($report);

        $this->assertStringContainsString('🍀របាយការណ៍KY-Branch  ទី03 ខែ10 ឆ្នាំ2026🍀', $text);
        $this->assertStringContainsString('លក់បាន 3ដើម', $text);
        $this->assertStringContainsString('-សងប្រាក់រំលស់', $text);
        $this->assertStringContainsString('-សងប្រាក់ខ្វះ', $text);
        $this->assertStringContainsString('1. Delivery=$ 52.5', $text);
        $this->assertStringContainsString('បង្ហាញ 1 / 1001 ចំណាយ', $text);
        $this->assertStringContainsString('លុយ=$ 128.5 ធនាគារ=$ 319.5' . "\n" . 'សរុប=$ 448', $text);
        $this->assertStringContainsString('លុយ=$ 126 ធនាគារ=$ 269.5' . "\n" . 'សរុប=$ 395.5', $text);
        $this->assertStringContainsString('សរុបក្រោយទំនិញត្រឡប់=$ 385.5', $text);
    }

    public function test_empty_report_and_date_range_do_not_invent_counts_or_amounts(): void
    {
        $text = $this->build([], '2026-10-04', 'invoice_count');

        $this->assertStringContainsString('ទី03 ខែ10 ឆ្នាំ2026 - ទី04 ខែ10 ឆ្នាំ2026', $text);
        $this->assertStringContainsString('លក់បាន 0វិក្កយបត្រ', $text);
        $this->assertStringContainsString('លុយ=$ 0 ធនាគារ=$ 0' . "\n" . 'សរុប=$ 0', $text);
    }

    public function test_unmapped_payment_methods_remain_in_the_message_and_totals(): void
    {
        $text = $this->build([
            'payment_with_expenses' => ['bank_transfer' => 15],
            'payment_labels' => ['bank_transfer' => 'Bank Transfer'],
        ]);

        $this->assertStringContainsString('Bank Transfer : $ 15', $text);
        $this->assertStringContainsString('លុយ=$ 0 ធនាគារ=$ 15' . "\n" . 'សរុប=$ 15', $text);
    }

    private function build(array $report, string $endDate = '2026-10-03', string $qtyType = 'sold_quantity'): string
    {
        $config = require dirname(__DIR__, 2) . '/Modules/LocalCashierReport/Config/config.php';

        return TelegramReport::build($report, [
            'start_date' => '2026-10-03',
            'end_date' => $endDate,
            'qty_type' => $qtyType,
        ], 'KY', ['Branch'], $config['all_sale_static_payment_columns']);
    }
}
