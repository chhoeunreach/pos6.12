<?php

namespace Modules\LoanManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStandaloneLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (
            auth()->user()->can('loan_management.loans.create')
            || auth()->user()->can('loan_management.create')
        );
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|integer',
            'customer_name' => 'required|string|max:191',
            'customer_english_name' => 'required|string|max:191',
            'customer_khmer_name' => 'required|string|max:191',
            'customer_phone' => 'nullable|string|max:191',
            'alternate_phone' => 'nullable|string|max:191',
            'customer_address' => 'nullable|string|max:1000',
            'province_code' => 'nullable|string|max:20',
            'province_name' => 'nullable|string|max:191',
            'district_code' => 'nullable|string|max:20',
            'district_name' => 'nullable|string|max:191',
            'commune_code' => 'nullable|string|max:20',
            'commune_name' => 'nullable|string|max:191',
            'village_code' => 'nullable|string|max:20',
            'village_name' => 'nullable|string|max:191',
            'customer_group_name' => 'nullable|string|max:255',
            'id_card_number' => 'nullable|string|max:100',
            'loan_number' => 'nullable|string|max:191',
            'loan_date' => 'required|date',
            'principal_amount' => 'required|numeric|min:0.01',
            'down_payment' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'interest_type' => 'required|in:flat,reducing_balance',
            'duration_months' => 'required|integer|min:1|max:360',
            'payment_frequency' => 'required|in:monthly,weekly,daily',
            'first_due_date' => 'required|date',
            'currency' => 'required|in:USD,KHR',
            'exchange_rate' => 'nullable|numeric|min:0',
            'penalty_type' => 'nullable|string|max:50',
            'penalty_amount' => 'nullable|numeric|min:0',
            'assigned_collector_id' => 'nullable|integer',
            'note' => 'nullable|string|max:1000',
            'business_location_id' => 'nullable|integer',
            'items' => 'nullable|array',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.sku' => 'nullable|string|max:255',
            'items.*.imei' => 'nullable|string|max:255',
            'items.*.color' => 'nullable|string|max:255',
            'items.*.storage' => 'nullable|string|max:255',
            'items.*.serial_number' => 'nullable|string|max:255',
            'items.*.qty' => 'nullable|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.product_photo' => 'nullable|string',
            'items.*.product_ocr_raw_text' => 'nullable|string',
            'payment' => 'nullable|array',
            'payment.amount' => 'nullable|numeric|min:0',
            'payment.paid_date' => 'nullable|date',
            'payment.method' => 'nullable|string|max:100',
            'payment.payment_method_id' => 'nullable|integer',
            'payment.currency' => 'nullable|in:USD,KHR',
            'payment.exchange_rate' => 'nullable|numeric|min:0',
            'payment.status' => 'nullable|in:completed,pending,failed',
            'payment.account_name' => 'nullable|string|max:255',
            'payment.account_number' => 'nullable|string|max:255',
            'payment.transaction_id' => 'nullable|string|max:255',
            'payment.channel' => 'nullable|string|max:100',
            'payment.reference_number' => 'nullable|string|max:255',
            'payment.note' => 'nullable|string|max:1000',
            'payments' => 'nullable|array',
            'payments.*.amount' => 'nullable|numeric|min:0',
            'payments.*.paid_date' => 'nullable|date',
            'payments.*.method' => 'nullable|string|max:100',
            'payments.*.reference_number' => 'nullable|string|max:255',
            'payments.*.currency' => 'nullable|in:USD,KHR',
            'payments.*.exchange_rate' => 'nullable|numeric|min:0',
            'id_card_image' => 'nullable|string',
            'customer_profile_image' => 'nullable|string',
            'id_card_ocr_raw_text' => 'nullable|string',
            'id_card_ocr_fields' => 'nullable|array',
            'id_card_ocr_fields.id_card_number' => 'nullable|string|max:100',
            'id_card_ocr_fields.khmer_name' => 'nullable|string|max:191',
            'id_card_ocr_fields.english_name' => 'nullable|string|max:191',
            'id_card_ocr_fields.address' => 'nullable|string|max:1000',
            'documents' => 'nullable|array',
            'documents.*' => 'nullable|string',
            'document_text' => 'nullable|string|max:5000',
            'document_links' => 'nullable|array',
            'document_links.*' => 'nullable|url|max:1000',
            'action_type' => 'required|in:draft,create,create_approve',
        ];
    }
}
