<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoanAbaPaywayController extends Controller
{
    use ApiResponseTrait;

    protected string $conn = 'mysql_loan';

    public function create(Request $request)
    {
        $data = $request->validate([
            'loan_id' => 'nullable|integer',
            'payment_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'payment_option' => 'nullable|string|max:50',
        ]);

        $ref = 'ABA-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
        $payload = [
            'loan_id' => $data['loan_id'] ?? null,
            'payment_id' => $data['payment_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'merchant_ref_no' => $ref,
            'payment_option' => $data['payment_option'] ?? 'khqr',
            'amount' => (float) $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'status' => 'pending',
            'request_payload' => json_encode($data),
            'response_payload' => json_encode(['mock_checkout_url' => url('/loan-management/payway/'.$ref)]),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::connection($this->conn)->table('loan_aba_payway_transactions')
            ->insertGetId($this->safeColumns('loan_aba_payway_transactions', $payload));

        return $this->ok('ABA PayWay transaction created', [
            'id' => $id,
            'merchant_ref_no' => $ref,
            'status' => 'pending',
            'checkout_url' => url('/loan-management/payway/'.$ref),
            'khqr' => 'KHQR-'.$ref,
        ]);
    }

    public function checkStatus(Request $request)
    {
        $data = $request->validate([
            'merchant_ref_no' => 'required|string',
        ]);

        $row = DB::connection($this->conn)->table('loan_aba_payway_transactions')
            ->where('merchant_ref_no', $data['merchant_ref_no'])
            ->first();

        if (! $row) {
            return $this->fail('Transaction not found', 404, (object) []);
        }

        return $this->ok('ABA PayWay status loaded', [
            'id' => (int) $row->id,
            'merchant_ref_no' => (string) $row->merchant_ref_no,
            'status' => (string) $row->status,
            'amount' => $this->money($row->amount ?? 0),
            'currency' => (string) ($row->currency ?? 'USD'),
            'verified_at' => $row->verified_at ?? null,
        ]);
    }

    protected function safeColumns(string $table, array $payload): array
    {
        $columns = Schema::connection($this->conn)->hasTable($table)
            ? Schema::connection($this->conn)->getColumnListing($table)
            : [];
        return array_intersect_key($payload, array_flip($columns));
    }
}

