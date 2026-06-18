<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class LedgerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'date' => $this->transaction_date ?? $this->paid_on ?? $this->created_at,
            'type' => $this->type ?? ($this->amount ? 'payment' : 'transaction'),
            'invoice_no' => $this->invoice_no ?? $this->payment_ref_no ?? '',
            'debit' => $this->debit ?? 0,
            'credit' => $this->credit ?? 0,
            'balance' => $this->balance ?? 0,
            'note' => $this->additional_notes ?? $this->note ?? '',
        ];
    }
}
