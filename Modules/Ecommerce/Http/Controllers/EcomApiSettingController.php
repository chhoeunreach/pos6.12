<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Ecommerce\Entities\EcomApiSetting;

class EcomApiSettingController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();

        $business_id = request()->session()->get('user.business_id');
        $locations = BusinessLocation::forDropdown($business_id, false, false, true, true);
        $settings = EcomApiSetting::where('business_id', $business_id)
            ->orderBy('id', 'desc')
            ->get();

        return view('ecommerce::ecom_api_settings.index', compact('locations', 'settings'));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $business_id = $request->session()->get('user.business_id');
        $request->validate([
            'location_id' => 'required|exists:business_locations,id',
            'shop_domain' => 'nullable|string|max:255',
        ]);

        $location_exists = BusinessLocation::where('business_id', $business_id)
            ->where('id', $request->input('location_id'))
            ->exists();

        if (! $location_exists) {
            abort(403, 'Unauthorized action.');
        }

        $setting = EcomApiSetting::create([
            'business_id' => $business_id,
            'location_id' => $request->input('location_id'),
            'shop_domain' => $this->normalizeDomain($request->input('shop_domain')),
            'api_token' => $this->generateToken(),
            'is_active' => true,
        ]);

        return redirect()
            ->action([self::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => 'Ecommerce API token created.'])
            ->with('new_api_token', $setting->api_token);
    }

    public function deactivate($id)
    {
        $this->authorizeAccess();

        $setting = $this->findForBusiness($id);
        $setting->is_active = false;
        $setting->save();

        return redirect()
            ->action([self::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => 'Ecommerce API token deactivated.']);
    }

    public function regenerate($id)
    {
        $this->authorizeAccess();

        $setting = $this->findForBusiness($id);
        $setting->api_token = $this->generateToken();
        $setting->is_active = true;
        $setting->save();

        return redirect()
            ->action([self::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => 'Ecommerce API token regenerated.'])
            ->with('new_api_token', $setting->api_token);
    }

    protected function authorizeAccess()
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
    }

    protected function findForBusiness($id)
    {
        return EcomApiSetting::where('business_id', request()->session()->get('user.business_id'))
            ->findOrFail($id);
    }

    protected function generateToken()
    {
        do {
            $token = Str::random(80);
        } while (EcomApiSetting::where('api_token', $token)->exists());

        return $token;
    }

    protected function normalizeDomain($domain)
    {
        $domain = trim((string) $domain);

        if ($domain === '') {
            return null;
        }

        $parsed_url = parse_url(str_contains($domain, '://') ? $domain : 'http://'.$domain);
        $host = $parsed_url['host'] ?? null;
        if (! empty($host)) {
            $domain = $host;
            if (! empty($parsed_url['port'])) {
                $domain .= ':'.$parsed_url['port'];
            }
        }

        return strtolower(rtrim($domain, '/'));
    }
}
