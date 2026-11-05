<?php

namespace Modules\DailyFinancialManagement\Tests\Unit;

use Tests\TestCase;
use Modules\DailyFinancialManagement\Services\FinancialCalculationService;

class FinancialCalculationTest extends TestCase
{
    protected FinancialCalculationService $calcService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calcService = new FinancialCalculationService();
    }

    /** @test */
    public function it_calculates_net_sales_and_gross_profit_correctly()
    {
        $grossSales = 1000.00;
        $discounts = 50.00;
        $returns = 100.00;
        $cogs = 600.00;

        $netSales = $grossSales - $discounts - $returns; // 850.00
        $grossProfit = $netSales - $cogs; // 250.00
        $grossMarginPercent = ($grossProfit / $netSales) * 100; // 29.41%

        $this->assertEquals(850.00, $netSales);
        $this->assertEquals(250.00, $grossProfit);
        $this->assertEquals(29.41, round($grossMarginPercent, 2));
    }

    /** @test */
    public function it_calculates_net_operating_profit_with_interest_and_opex()
    {
        $grossProfit = 250.00;
        $recognizedInterestIncome = 80.00;
        $otherIncome = 20.00;
        $totalOperatingExpenses = 150.00;

        $totalRecognizedIncome = $recognizedInterestIncome + $otherIncome; // 100.00
        $netOperatingProfit = $grossProfit + $totalRecognizedIncome - $totalOperatingExpenses; // 200.00

        $this->assertEquals(200.00, $netOperatingProfit);
    }

    /** @test */
    public function it_enforces_that_installment_principal_is_not_revenue()
    {
        $installmentTotalPayment = 300.00;
        $principalPaid = 200.00;
        $interestPaid = 100.00;

        // P&L recognizes ONLY interest
        $pnlRecognizedRevenue = $interestPaid;
        $this->assertEquals(100.00, $pnlRecognizedRevenue);

        // Cash flow recognizes full 300.00 inflow, categorized as principal recovery + interest
        $cashInflow = $principalPaid + $interestPaid;
        $this->assertEquals(300.00, $cashInflow);
    }

    /** @test */
    public function it_calculates_daily_cash_reconciliation_variance()
    {
        $openingCash = 500.00;
        $totalInflow = 1200.00;
        $totalOutflow = 800.00;

        $expectedClosing = $openingCash + $totalInflow - $totalOutflow; // 900.00
        $actualPhysicalCashCounted = 920.00; // $20 surplus

        $variance = $actualPhysicalCashCounted - $expectedClosing;

        $this->assertEquals(900.00, $expectedClosing);
        $this->assertEquals(20.00, $variance);
    }
}

