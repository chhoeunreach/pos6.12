<?php

namespace Modules\LocalCashierReport\Services;

use Modules\LocalCashierReport\Support\ReportLanguage;
use ZipStream\ZipStream;

class FinancialDashboardWordExport
{
    public function download(array $report, array $filters, string $businessName, string $currencySymbol)
    {
        $t = fn ($key) => ReportLanguage::text($key);
        $xml = static fn ($value) => htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $money = fn ($value) => $value === null ? $t('not_available') : ($value < 0 ? '('.$currencySymbol.number_format(abs($value), 2).')' : $currencySymbol.number_format($value, 2));
        $quantity = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
        $columns = [$t('financial_overview'), ...array_column($report['branches'], 'name'), $t('total')];
        $headerUnits = [$filters['start_date'].' — '.$filters['end_date']];
        foreach ([...array_values($report['branches']), $report['total']] as $branch) $headerUnits[] = $quantity($branch['units']).' '.$t('units');
        $metricRow = function ($metric) use ($report, $t, $money) {
            $cells = [$t('finance_'.$metric)];
            foreach ([...array_values($report['branches']), $report['total']] as $branch) {
                $value = $branch[$metric];
                $cells[] = $value === null ? $t('not_available') : (in_array($metric, ['gross_margin', 'margin'], true) ? number_format($value, 2).'%' : $money($value));
            }
            return ['cells' => $cells, 'kind' => $metric === 'net_profit' ? 'result' : (in_array($metric, ['net_sales', 'gross_profit', 'expenses'], true) ? 'subtotal' : 'normal'),
                'hint' => in_array($metric, ['net_sales', 'gross_profit', 'net_profit'], true) ? $t('finance_formula_'.$metric) : ''];
        };
        $tableRows = [];
        foreach (['main_sales', 'accessory_sales', 'service_sales', 'net_sales', 'cost', 'gross_profit', 'gross_margin'] as $metric) $tableRows[] = $metricRow($metric);
        $tableRows[] = ['section' => $t('expense_breakdown')];
        if ($report['unavailable']) {
            $tableRows[] = ['notice' => $t('finance_unavailable')];
        } elseif (!$report['expenses']) {
            $tableRows[] = ['notice' => $t('finance_no_expenses')];
        } else {
            foreach ($report['expenses'] as $expense) {
                $cells = [$expense['name'] === '__uncategorized__' ? $t('uncategorized') : $expense['name']];
                foreach (array_keys($report['branches']) as $key) $cells[] = $money($expense['values'][$key] ?? 0);
                $tableRows[] = ['cells' => [...$cells, $money($expense['total'])], 'kind' => 'normal', 'hint' => ''];
            }
        }
        foreach (['expenses', 'net_profit', 'margin'] as $metric) $tableRows[] = $metricRow($metric);
        $total = $report['total'];
        $top = $report['top_location'];
        $cards = [
            ['label' => $t('finance_gross_profit'), 'value' => $money($total['gross_profit']), 'note' => $t('gross_profit_formula'), 'fill' => '168F7E', 'color' => 'FFFFFF'],
            ['label' => $t('finance_net_sales'), 'value' => $money($total['net_sales']), 'note' => $filters['start_date'].' — '.$filters['end_date'], 'fill' => '163D70', 'color' => 'FFFFFF'],
            ['label' => $t('total_units_sold'), 'value' => $quantity($total['units']).' '.$t('units'), 'note' => $t('units_net_returns'), 'fill' => '553180', 'color' => 'FFFFFF'],
            ['label' => $t('top_location'), 'value' => $top['name'] ?? '—', 'note' => $top ? $quantity($top['units']).' '.$t('units') : ($total['units'] === null ? $t('not_available') : $t('no_sales')), 'fill' => 'E9EEF3', 'color' => '173B67'],
        ];
        $chartRows = array_values(array_filter($report['expenses'], fn ($row) => $row['total'] > 0));
        if (count($chartRows) > 8) {
            $rest = array_slice($chartRows, 8);
            $chartRows = array_slice($chartRows, 0, 8);
            $chartRows[] = ['name' => $t('finance_remaining_categories').' ('.count($rest).')', 'share' => array_sum(array_column($rest, 'share')), 'total' => array_sum(array_column($rest, 'total'))];
        }
        $document = view('localcashierreport::financial_word_document', compact('report', 'filters', 'businessName', 'currencySymbol', 'columns', 'headerUnits', 'tableRows', 'cards', 'chartRows', 'money', 't', 'xml'))->render();

        return response()->streamDownload(function () use ($document) {
            $zip = new ZipStream(sendHttpHeaders: false, enableZip64: false, defaultEnableZeroHeader: false);
            $zip->addFile('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>');
            $zip->addFile('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
            $zip->addFile('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFile('word/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Khmer OS" w:cs="Khmer OS"/><w:sz w:val="20"/><w:szCs w:val="20"/><w:lang w:val="en-US" w:eastAsia="km-KH" w:bidi="km-KH"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="100"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style><w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:rPr><w:b/><w:color w:val="173B67"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="200"/></w:pPr><w:rPr><w:b/><w:color w:val="173B67"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:style></w:styles>');
            $zip->addFile('word/document.xml', $document);
            $zip->finish();
        }, 'financial_dashboard_'.$filters['start_date'].'_'.$filters['end_date'].'.docx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    }
}
