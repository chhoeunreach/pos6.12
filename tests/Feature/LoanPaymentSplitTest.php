<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\LoanManagement\Http\Controllers\LoanPaymentController;
use Tests\TestCase;

class LoanPaymentSplitTest extends TestCase
{
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.loan_split_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $schema = Schema::connection('loan_split_test');
        $schema->create('loans', function (Blueprint $t) {
            $t->increments('id');
            $t->string('currency');
            $t->string('status')->default('active');
            $t->double('paid_amount')->default(0);
            $t->double('balance_amount')->default(0);
        });
        $schema->create('loan_payments', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('loan_id');
            $t->date('paid_date')->nullable();
            $t->integer('schedule_id')->nullable();
            $t->double('total_paid_base');
            $t->string('payment_method_snapshot')->nullable();
            $t->string('status')->default('confirmed');
        });
        $schema->create('loan_payment_details', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('payment_id');
            $t->string('payment_method_snapshot');
            $t->string('currency')->default('USD');
            $t->double('exchange_rate')->default(1);
            $t->double('amount');
            $t->double('amount_base');
            $t->string('reference_number')->nullable();
        });
        $schema->create('loan_payment_schedules', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('loan_id');
            $t->double('schedule_amount');
            $t->double('paid_amount');
            $t->double('balance_amount');
        });
        $db = DB::connection('loan_split_test');
        $db->table('loans')->insert(['id' => 1, 'currency' => 'USD']);
        $db->table('loan_payments')->insert(['id' => 1, 'loan_id' => 1, 'schedule_id' => 1, 'total_paid_base' => 100]);
        $db->table('loan_payment_schedules')->insert(['id' => 1, 'loan_id' => 1, 'schedule_amount' => 200, 'paid_amount' => 100, 'balance_amount' => 100]);
        $db->table('loan_payment_details')->insert([
            ['id' => 1, 'payment_id' => 1, 'payment_method_snapshot' => 'Cash', 'amount' => 60, 'amount_base' => 60],
            ['id' => 2, 'payment_id' => 1, 'payment_method_snapshot' => 'ABA', 'amount' => 40, 'amount_base' => 40],
        ]);
        $this->controller = new class extends LoanPaymentController {
            protected string $connection = 'loan_split_test';
            protected function paymentMethodOptions($loan = null): array { return ['cash' => 'Cash', 'aba' => 'ABA', 'other' => 'Other']; }
        };
    }

    protected function tearDown(): void
    {
        DB::purge('loan_split_test');
        parent::tearDown();
    }

    private function save(array $details, int $amount = 100)
    {
        $request = Request::create('/loan-management/payments/1', 'PUT', [
            'paid_date' => '2026-10-29', 'amount' => $amount, 'schedule_id' => 1,
            'payment_details' => $details,
        ]);
        $request->headers->set('Accept', 'application/json');
        return $this->controller->update($request, 1);
    }

    public function test_split_can_be_saved_reopened_and_reduced_to_one_method(): void
    {
        $response = $this->save([
            ['id' => 1, 'method' => 'cash', 'amount' => 20],
            ['id' => 2, 'method' => 'aba', 'amount' => 70, 'reference_number' => 'ABA-123'],
            ['method' => 'other', 'amount' => 30],
        ], 120);
        $this->assertTrue($response->getData()->success);
        $db = DB::connection('loan_split_test');
        $rows = $db->table('loan_payment_details')->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertEquals(120, $rows->sum('amount_base'));
        $this->assertSame('ABA-123', $rows[1]->reference_number);
        $this->assertEquals(120, $db->table('loans')->value('paid_amount'));
        $this->assertEquals(80, $db->table('loan_payment_schedules')->value('balance_amount'));
        $this->assertCount(3, $this->controller->edit(1)->getData()['details']);
        $this->save([2 => ['id' => 2, 'method' => 'aba', 'amount' => 100]]);
        $this->assertSame([2], $db->table('loan_payment_details')->pluck('id')->all());
        $this->assertEquals(100, $db->table('loan_payment_schedules')->value('balance_amount'));
    }

    public function test_mismatched_total_is_rejected_without_changes(): void
    {
        try {
            $this->save([['method' => 'cash', 'amount' => 20], ['method' => 'aba', 'amount' => 70]]);
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_details', $e->errors());
        }
        $this->assertEquals(100, DB::connection('loan_split_test')->table('loan_payment_details')->sum('amount_base'));
    }

    public function test_foreign_detail_id_rolls_back_the_payment_update(): void
    {
        try {
            $this->save([['id' => 999, 'method' => 'cash', 'amount' => 150]], 150);
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_details.0.id', $e->errors());
        }
        $this->assertEquals(100, DB::connection('loan_split_test')->table('loan_payments')->value('total_paid_base'));
    }

    public function test_existing_foreign_currency_conversion_is_preserved(): void
    {
        $db = DB::connection('loan_split_test');
        $db->table('loan_payment_details')->where('id', 1)->update(['currency' => 'KHR', 'amount' => 240000, 'exchange_rate' => 4000]);
        $this->save([['id' => 1, 'method' => 'cash', 'amount' => 50], ['id' => 2, 'method' => 'aba', 'amount' => 50]]);
        $row = $db->table('loan_payment_details')->find(1);
        $this->assertSame('KHR', $row->currency);
        $this->assertEquals(200000, $row->amount);
        $this->assertEquals(4000, $row->exchange_rate);
    }
}
