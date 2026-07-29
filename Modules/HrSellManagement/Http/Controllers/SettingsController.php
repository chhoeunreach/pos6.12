<?php

namespace Modules\HrSellManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HrSellManagement\Services\HrSellService;

class SettingsController extends Controller
{
    public function __construct(private HrSellService $service)
    {
    }

    public function index()
    {
        abort_unless($this->canSettings(), 403);
        $setting = $this->service->setting((int) session('user.business_id'));

        return view('hrsellmanagement::settings.index', compact('setting'));
    }

    public function update(Request $request)
    {
        abort_unless($this->canSettings(), 403);
        $data = $request->validate([
            'commission_type' => 'required|string|in:percent,fixed',
            'commission_value' => 'required|numeric|min:0',
            'require_approval' => 'nullable|boolean',
            'approval_levels' => 'nullable|string|max:500',
        ]);
        $setting = $this->service->setting((int) session('user.business_id'));
        $setting->commission_type = $data['commission_type'];
        $setting->commission_value = (float) $data['commission_value'];
        $setting->require_approval = (int) ($data['require_approval'] ?? 0);
        $setting->approval_levels = array_values(array_filter(array_map('trim', explode(',', (string) ($data['approval_levels'] ?? 'supervisor,manager')))));
        $setting->updated_by = auth()->id();
        $setting->save();

        return back()->with('status', ['success' => 1, 'msg' => 'HR sell settings updated']);
    }

    private function canSettings(): bool
    {
        $user = auth()->user();

        return $user->can('hr_sell.settings') || $user->can('superadmin') || $user->can('business_settings.access');
    }
}
