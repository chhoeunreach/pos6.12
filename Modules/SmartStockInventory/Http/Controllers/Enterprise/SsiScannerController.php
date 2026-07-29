<?php

namespace Modules\SmartStockInventory\Http\Controllers\Enterprise;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SmartStockInventory\Models\Enterprise\SsiAudit;
use Modules\SmartStockInventory\Services\Enterprise\SsiScannerService;

class SsiScannerController extends Controller
{
    public function __construct(private SsiScannerService $scanner)
    {
    }

    public function mobile(Request $request, SsiAudit $audit)
    {
        abort_unless($request->user()->can('ssi.audit.scan'), 403);
        $this->authorizeBusiness($audit);

        return view('smartstockinventory::enterprise.scanner.mobile', compact('audit'));
    }

    public function scan(Request $request, SsiAudit $audit)
    {
        abort_unless($request->user()->can('ssi.audit.scan'), 403);
        $this->authorizeBusiness($audit);
        $data = $request->validate([
            'scan_value' => 'required|string|max:191',
            'quantity' => 'nullable|numeric|min:0.0001',
            'warehouse' => 'nullable|string|max:120',
            'zone' => 'nullable|string|max:120',
            'rack' => 'nullable|string|max:120',
            'shelf' => 'nullable|string|max:120',
            'bin' => 'nullable|string|max:120',
        ]);

        $result = $this->scanner->scan(
            $audit,
            $data['scan_value'],
            (float) ($data['quantity'] ?? 1),
            (int) $request->user()->id,
            $request,
            $data
        );

        return response()->json(['success' => 1, 'data' => $result]);
    }

    private function authorizeBusiness(SsiAudit $audit): void
    {
        abort_unless((int) $audit->business_id === (int) session('user.business_id'), 403);
    }
}
