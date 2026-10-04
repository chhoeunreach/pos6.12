<?php

namespace Modules\LocalCashierReport\Support;

use Illuminate\Support\Carbon;

class TelegramReport
{
    public static function build(array $report, array $filters, string $businessName, array $locations, array $columns): string
    {
        $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        $money = fn ($value) => '$ ' . $number($value);
        $date = fn ($value) => Carbon::parse($value)->format('ទីd ខែm ឆ្នាំY');
        $dates = $date($filters['start_date']);
        if ($filters['start_date'] !== $filters['end_date']) {
            $dates .= ' - ' . $date($filters['end_date']);
        }
        $lines = ['🍀របាយការណ៍' . $businessName . ($locations ? '-' . implode(', ', $locations) : '') . '  ' . $dates . '🍀'];
        $addPayments = function (array &$target, array $payments) {
            foreach ($payments as $method => $amount) {
                $target[$method] = ($target[$method] ?? 0) + (float) $amount;
            }
        };
        $paymentLines = function (array $payments) use ($columns, $report, $money) {
            $result = [];
            $used = [];
            foreach ($columns as $column) {
                $amount = 0.0;
                foreach ($column['source_methods'] as $method) {
                    $amount += (float) ($payments[$method] ?? 0);
                    $used[$method] = true;
                }
                $label = ['cash' => 'Cash', 'wing' => 'WING', 'aba' => 'ABA', 'acleda' => 'ACLEDA', 'true' => 'E&T', 'card' => 'Card', 'cut' => 'វៃដូរ'][$column['key']] ?? $column['label'];
                $result[] = $label . ' : ' . $money($amount);
            }
            foreach ($payments as $method => $amount) {
                if (! isset($used[$method]) && abs((float) $amount) >= 0.005) {
                    $result[] = ($report['payment_labels'][$method] ?? $method) . ' : ' . $money($amount);
                }
            }

            return $result;
        };
        $totals = function (array $payments) use ($money) {
            $cash = (float) ($payments['cash'] ?? 0);
            $total = array_sum($payments);

            return ['លុយ=' . $money($cash) . ' ធនាគារ=' . $money($total - $cash), 'សរុប=' . $money($total)];
        };
        $separator = ['🌿🌿🌿🌿🌿🌿🌿🌿', '————————————————'];
        $groups = [];
        foreach ($report['rows_by_location'] ?? [] as $location) {
            foreach ($location['customer_groups'] ?? [] as $group) {
                $name = $group['name'];
                $groups[$name] = $groups[$name] ?? ['qty' => 0, 'payments' => []];
                $groups[$name]['qty'] += (float) ($group['qty_total'] ?? 0);
                $addPayments($groups[$name]['payments'], $group['payments'] ?? []);
            }
        }
        $qtyLabel = ($filters['qty_type'] ?? 'invoice_count') === 'sold_quantity' ? 'ដើម' : 'វិក្កយបត្រ';
        $sales = $groups['លក់'] ?? ['qty' => 0, 'payments' => []];
        $lines[] = 'លក់បាន ' . $number($sales['qty']) . $qtyLabel;
        $lines = array_merge($lines, $paymentLines($sales['payments']), [''], $totals($sales['payments']), $separator, ['', '🌻រំលស់🌻']);
        $installmentPayments = [];
        foreach (['រំលស់' => 'រំលស់ហាង', 'Collection Payment' => 'សងប្រាក់រំលស់', 'អ៊ីអន' => 'អ៊ីអន (Report)'] as $key => $label) {
            $group = $groups[$key] ?? ['qty' => 0, 'payments' => []];
            $lines[] = '-' . $label . ($key === 'Collection Payment' ? '' : ' ' . $number($group['qty']) . $qtyLabel);
            $lines[] = implode('  ', $paymentLines($group['payments']));
            $lines[] = '';
            $addPayments($installmentPayments, $group['payments']);
        }
        $lines = array_merge($lines, $totals($installmentPayments), $separator, ['', '🌸ចំណូលផ្សេងៗ🌸']);
        $otherPayments = [];
        foreach ($report['module_dashboard_rows'] ?? [] as $row) {
            $lines[] = '-' . $row['label'];
            $lines[] = implode('  ', $paymentLines($row['payments'] ?? []));
            $lines[] = '';
            $addPayments($otherPayments, $row['payments'] ?? []);
        }
        $customerPayments = $groups['Customer Payment']['payments'] ?? [];
        $lines[] = '-សងប្រាក់ខ្វះ';
        $lines[] = implode('  ', $paymentLines($customerPayments));
        $addPayments($otherPayments, $customerPayments);
        $lines = array_merge($lines, [''], $totals($otherPayments), $separator, ['', '🌷ចំណាយផ្សេងៗ🌷']);
        foreach ($report['expense_detail_rows'] ?? [] as $index => $row) {
            $description = trim((string) ($row['note'] ?? '')) ?: ($row['category_name'] ?? $row['ref_no']);
            $lines[] = ($index + 1) . '. ' . preg_replace('/\s+/u', ' ', $description) . '=' . $money($row['amount'] ?? 0);
        }
        $meta = $report['detail_meta'] ?? [];
        if (($meta['expense_total'] ?? 0) > count($report['expense_detail_rows'] ?? [])) {
            $lines[] = 'បង្ហាញ ' . count($report['expense_detail_rows']) . ' / ' . $meta['expense_total'] . ' ចំណាយ';
        }
        $expensePayments = $report['expense_payment_summary'] ?? [];
        $incomePayments = $report['payment_with_expenses'] ?? [];
        unset($incomePayments['expenses']);
        // Module payments are summarized separately from the main cashier totals.
        foreach ($report['module_dashboard_rows'] ?? [] as $row) {
            $addPayments($incomePayments, $row['payments'] ?? []);
        }
        $remainingPayments = $incomePayments;
        foreach ($expensePayments as $method => $amount) {
            $remainingPayments[$method] = ($remainingPayments[$method] ?? 0) - (float) $amount;
        }
        foreach (['🌼ចំណូលសរុប🌼' => $incomePayments, '🌸ចំណាយសរុប🌸' => $expensePayments, '🌼ទឹកប្រាក់នៅសល់🌼' => $remainingPayments] as $title => $payments) {
            $lines = array_merge($lines, $separator, ['', $title], $paymentLines($payments), [''], $totals($payments));
        }
        $returns = (float) ($report['grand_sell_return'] ?? 0);
        if ($returns != 0) {
            $lines[] = 'ទំនិញត្រឡប់=' . $money($returns);
            $lines[] = 'សរុបក្រោយទំនិញត្រឡប់=' . $money(array_sum($remainingPayments) - $returns);
        }
        $lines[] = '🌿🌱🍀🪴🌴🌳🌸🌾🌷🌱🌼🌻🌸';

        return implode("\n", $lines);
    }
}
