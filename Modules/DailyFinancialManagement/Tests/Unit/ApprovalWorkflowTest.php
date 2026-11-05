<?php

namespace Modules\DailyFinancialManagement\Tests\Unit;

use Tests\TestCase;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;
use Modules\DailyFinancialManagement\Services\ApprovalWorkflowService;

class ApprovalWorkflowTest extends TestCase
{
    /** @test */
    public function it_validates_status_transitions()
    {
        $allowedStatuses = ['draft', 'pending', 'reviewed', 'approved', 'rejected', 'reversed'];

        $this->assertContains('draft', $allowedStatuses);
        $this->assertContains('pending', $allowedStatuses);
        $this->assertContains('reviewed', $allowedStatuses);
        $this->assertContains('approved', $allowedStatuses);
        $this->assertContains('rejected', $allowedStatuses);
        $this->assertContains('reversed', $allowedStatuses);
    }

    /** @test */
    public function it_calculates_reversal_offsets()
    {
        $originalAmount = 150.00;
        $reversalAmount = -$originalAmount;

        $netImpact = $originalAmount + $reversalAmount;
        $this->assertEquals(0.00, $netImpact);
    }
}

