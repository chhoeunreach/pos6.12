<?php

namespace Modules\LoanManagement\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Entities\LoanProduct;
use Modules\LoanManagement\Services\BusinessSettingsService;
use Modules\LoanManagement\Services\CreateStandaloneLoanService;
use Modules\LoanManagement\Services\LoanCustomerService;

class PublicAppController extends Controller
{
    use ApiResponseTrait;

    public function home()
    {
        if (! BusinessSettingsService::isCmsEnabled()) {
            return Route::has('login')
                ? redirect()->route('login')
                : redirect('/login');
        }

        $products = $this->catalogProducts();
        $categories = collect($products)->pluck('category')->filter(fn ($c) => trim((string)$c) !== '')->unique()->values()->all();
        $brands = collect($products)->pluck('brand')->filter(fn ($b) => trim((string)$b) !== '')->unique()->values()->all();

        return view('loanmanagement::public.home', [
            'settings' => BusinessSettingsService::get(),
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    public function register()
    {
        return view('loanmanagement::public.register', [
            'settings' => BusinessSettingsService::get(),
        ]);
    }

    public function storeRegistration(Request $request, LoanCustomerService $customers)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'khmer_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:50',
            'telegram' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'password' => 'required|string|min:8|confirmed',
            'installment_items' => 'nullable|string|max:10000',
        ]);

        $phone = trim((string) $data['phone']);
        $exists = Schema::connection('mysql_loan')->hasTable('loan_customers')
            && DB::connection('mysql_loan')->table('loan_customers')->where('phone', $phone)->exists();
        if ($exists) {
            return back()
                ->withErrors(['phone' => 'This phone number is already registered. Please login or contact support.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $installmentNote = $this->installmentRequestNote((string) ($data['installment_items'] ?? ''));
        unset($data['installment_items']);

        $customerId = $customers->create(array_merge($data, [
            'phone' => $phone,
            'login_phone' => $phone,
            'username' => $phone,
            'can_login' => 1,
            'status' => 'active',
            'customer_type' => 'public_registration',
            'note' => $installmentNote,
        ]));

        $customer = LoanCustomer::query()->find($customerId);
        if ($customer) {
            // Disallow concurrent logins: log out admin/web session if active
            if (Auth::guard('web')->check() || Auth::check()) {
                Auth::guard('web')->logout();
                Auth::logout();
            }
            Auth::guard('customer_loan')->login($customer);
            $request->session()->regenerate();
        }

        return redirect()
            ->route('loan-management.public.customer-dashboard')
            ->with('status', 'Registration complete. Welcome to your customer dashboard.');
    }

    public function customerLogin()
    {
        if (! BusinessSettingsService::isCustomerLoginEnabled()) {
            return redirect()->route('loan-management.public.home')
                ->with('status', 'Customer login portal is currently disabled by administrator.');
        }

        if (Auth::guard('customer_loan')->check()) {
            return redirect()->route('loan-management.public.customer-dashboard');
        }

        $demoCustomers = LoanCustomer::query()
            ->where('can_login', 1)
            ->where('status', 'active')
            ->select('id', 'name', 'phone', 'login_phone', 'username')
            ->take(3)
            ->get();

        return view('loanmanagement::public.customer_login', [
            'settings' => BusinessSettingsService::get(),
            'demoCustomers' => $demoCustomers,
        ]);
    }

    public function customerLoginStore(Request $request)
    {
        if (! BusinessSettingsService::isCustomerLoginEnabled()) {
            return redirect()->route('loan-management.public.home')
                ->with('status', 'Customer login portal is currently disabled by administrator.');
        }

        $credentials = $request->validate([
            'login' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        $login = trim((string) $credentials['login']);
        $customer = LoanCustomer::query()
            ->where(function ($q) use ($login) {
                $q->where('username', $login)
                    ->orWhere('phone', $login)
                    ->orWhere('login_phone', $login);
            })
            ->where('can_login', 1)
            ->where('status', 'active')
            ->first();

        // Disallow concurrent logins: log out admin/web session if active
        if (Auth::guard('web')->check() || Auth::check()) {
            Auth::guard('web')->logout();
            Auth::logout();
        }

        if (! $customer || ! Auth::guard('customer_loan')->attempt(['id' => $customer->id, 'password' => $credentials['password']], $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'These credentials do not match our records.'])->onlyInput('login');
        }

        $request->session()->regenerate();
        $customer->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('loan-management.public.customer-dashboard'));
    }

    public function customerLogout(Request $request)
    {
        Auth::guard('customer_loan')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectTo = $request->query('redirect') ?? $request->input('redirect');
        if ($redirectTo && (str_starts_with($redirectTo, '/') || filter_var($redirectTo, FILTER_VALIDATE_URL))) {
            return redirect($redirectTo);
        }

        return redirect()->route('loan-management.public.home');
    }

    public function customerDashboard()
    {
        $customer = Auth::guard('customer_loan')->user();
        if (! $customer) {
            return redirect()->route('loan-management.public.customer-login');
        }

        $loans = DB::connection('mysql_loan')->table('loans')
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $payments = DB::connection('mysql_loan')->table('loan_payments')
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $pendingLoans = $loans->filter(function ($l) {
            return in_array($l->status, ['pending', 'draft', 'pending_approval'], true);
        })->values();

        $activeLoans = $loans->filter(function ($l) {
            return in_array($l->status, ['active', 'approved', 'in_progress'], true);
        })->values();

        $completedLoans = $loans->filter(function ($l) {
            return in_array($l->status, ['completed', 'paid', 'closed'], true);
        })->values();

        $cancelledLoans = $loans->filter(function ($l) {
            return in_array($l->status, ['cancelled', 'rejected', 'declined'], true);
        })->values();

        return view('loanmanagement::public.customer_dashboard', [
            'settings' => BusinessSettingsService::get(),
            'customer' => $customer,
            'loans' => $loans,
            'pendingLoans' => $pendingLoans,
            'activeLoans' => $activeLoans,
            'completedLoans' => $completedLoans,
            'cancelledLoans' => $cancelledLoans,
            'totalLoanCount' => $loans->count(),
            'pendingCount' => $pendingLoans->count(),
            'activeCount' => $activeLoans->count(),
            'completedCount' => $completedLoans->count(),
            'payments' => $payments,
        ]);
    }

    public function cancelCustomerLoanRequest(Request $request, int $id)
    {
        $customer = Auth::guard('customer_loan')->user();
        if (! $customer) {
            return redirect()->route('loan-management.public.customer-login');
        }

        $loan = DB::connection('mysql_loan')->table('loans')
            ->where('id', $id)
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $loan) {
            return back()->withErrors(['error' => 'Loan request not found.']);
        }

        if (! in_array($loan->status, ['pending', 'draft', 'pending_approval'], true)) {
            return back()->withErrors(['error' => 'Only pending loan requests can be cancelled.']);
        }

        DB::connection('mysql_loan')->table('loans')
            ->where('id', $id)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        if (Schema::connection('mysql_loan')->hasTable('loan_status_logs')) {
            DB::connection('mysql_loan')->table('loan_status_logs')->insert([
                'loan_id' => $id,
                'status' => 'cancelled',
                'from_status' => $loan->status,
                'to_status' => 'cancelled',
                'changed_by' => null,
                'changed_by_name_snapshot' => 'Customer: ' . $customer->name,
                'note' => 'Customer cancelled their own installment loan request.',
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('status', 'Your installment loan request (#' . ($loan->loan_number ?? $loan->id) . ') has been cancelled successfully.');
    }

    public function updateProfilePhoto(Request $request)
    {
        $customer = Auth::guard('customer_loan')->user();
        if (! $customer) {
            return redirect()->route('loan-management.public.customer-login');
        }

        $request->validate([
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
        ]);

        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $disk = 'public';
            $folder = 'loan-customers/'.$customer->id;
            $path = $file->store($folder, $disk);

            $payload = [
                'fileable_type' => 'loan_customer',
                'fileable_id' => $customer->id,
                'category' => 'customer_photo',
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::connection('mysql_loan')->hasTable('loan_files')) {
                $columns = Schema::connection('mysql_loan')->getColumnListing('loan_files');
                $fileId = (int) DB::connection('mysql_loan')->table('loan_files')->insertGetId(array_intersect_key($payload, array_flip($columns)));

                if (Schema::connection('mysql_loan')->hasColumn('loan_customers', 'customer_photo_file_id')) {
                    DB::connection('mysql_loan')->table('loan_customers')->where('id', $customer->id)->update([
                        'customer_photo_file_id' => $fileId,
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return back()->with('status', 'Profile photo updated successfully!');
    }

    public function customerLoanRequest(Request $request)
    {
        $customer = Auth::guard('customer_loan')->user();
        if (! $customer) {
            return redirect()->route('loan-management.public.customer-login');
        }

        $defaultInterestRate = (float) (BusinessSettingsService::get()['default_interest_rate'] ?? 1.5);
        $locations = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            $locations = DB::connection('mysql_loan')->table('loan_business_locations')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->select(['id', 'name'])
                ->get();
        }

        return view('loanmanagement::public.customer_loan_request', [
            'settings' => BusinessSettingsService::get(),
            'customer' => $customer,
            'defaultInterestRate' => $defaultInterestRate,
            'locations' => $locations,
            'catalogProducts' => $this->catalogProducts(),
        ]);
    }

    public function storeCustomerLoanRequest(Request $request, CreateStandaloneLoanService $loanService)
    {
        $customer = Auth::guard('customer_loan')->user();
        if (! $customer) {
            return redirect()->route('loan-management.public.customer-login');
        }

        $validated = $request->validate([
            'principal_amount' => 'required|numeric|min:1',
            'duration_months' => 'required|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0',
            'interest_type' => 'nullable|string|in:flat,reducing_balance',
            'payment_frequency' => 'nullable|string|in:monthly,weekly,daily',
            'first_due_date' => 'nullable|date',
            'down_payment' => 'nullable|numeric|min:0',
            'business_location_id' => 'nullable|integer',
            'items_json' => 'nullable|string',
            'khmer_name' => 'nullable|string|max:255',
            'id_card_number' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'alternate_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'workplace' => 'nullable|string|max:255',
            'monthly_income' => 'nullable|numeric|min:0',
            'guarantor_name' => 'nullable|string|max:255',
            'guarantor_phone' => 'nullable|string|max:50',
            'guarantor_relationship' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:2000',
            'id_card_front' => 'nullable|image|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'id_card_back' => 'nullable|image|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'income_proof' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'collateral_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        // Update customer profile with any newly provided details
        $customerUpdates = [];
        if (! empty($validated['khmer_name'])) $customerUpdates['khmer_name'] = $validated['khmer_name'];
        if (! empty($validated['id_card_number'])) $customerUpdates['id_card_number'] = $validated['id_card_number'];
        if (! empty($validated['workplace'])) $customerUpdates['workplace'] = $validated['workplace'];
        if (isset($validated['monthly_income'])) $customerUpdates['monthly_income'] = (float) $validated['monthly_income'];
        if (! empty($validated['alternate_phone'])) $customerUpdates['alternate_phone'] = $validated['alternate_phone'];
        if (! empty($validated['address'])) $customerUpdates['address'] = $validated['address'];
        if (! empty($validated['guarantor_name'])) $customerUpdates['family_contact_name'] = $validated['guarantor_name'];
        if (! empty($validated['guarantor_phone'])) $customerUpdates['family_contact_phone'] = $validated['guarantor_phone'];

        if (! empty($customerUpdates)) {
            $customerUpdates['updated_at'] = now();
            DB::connection('mysql_loan')->table('loan_customers')->where('id', $customer->id)->update($customerUpdates);
        }

        $items = [];
        if (! empty($validated['items_json'])) {
            $decoded = json_decode($validated['items_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $qty = max(1, (int) ($item['qty'] ?? 1));
                    $price = (float) ($item['price'] ?? 0);
                    $items[] = [
                        'item_name' => $item['name'] ?? 'Product',
                        'product_name' => $item['name'] ?? 'Product',
                        'sku' => $item['sku'] ?? null,
                        'price' => $price,
                        'unit_price' => $price,
                        'qty' => $qty,
                        'quantity' => $qty,
                        'subtotal' => round($price * $qty, 2),
                    ];
                }
            }
        }

        $loanDate = now()->toDateString();
        $firstDueDate = ! empty($validated['first_due_date'])
            ? Carbon::parse($validated['first_due_date'])->toDateString()
            : now()->addMonth()->toDateString();

        $loanData = [
            'action_type' => 'create_pending',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone ?: $customer->username,
            'customer_address' => $validated['address'] ?? $customer->address,
            'province_name' => $customer->province,
            'district_name' => $customer->district,
            'commune_name' => $customer->commune,
            'village_name' => $customer->village,
            'business_location_id' => $validated['business_location_id'] ?? null,
            'loan_date' => $loanDate,
            'principal_amount' => (float) $validated['principal_amount'],
            'down_payment' => (float) ($validated['down_payment'] ?? 0),
            'duration_months' => (int) $validated['duration_months'],
            'interest_rate' => (float) ($validated['interest_rate'] ?? 0),
            'interest_type' => $validated['interest_type'] ?? 'flat',
            'payment_frequency' => $validated['payment_frequency'] ?? 'monthly',
            'first_due_date' => $firstDueDate,
            'guarantor_name' => $validated['guarantor_name'] ?? null,
            'guarantor_phone' => $validated['guarantor_phone'] ?? null,
            'note' => $validated['note'] ?? 'Online installment loan request from customer portal.',
            'items' => $items,
        ];

        try {
            $loanId = $loanService->createStandaloneLoan($loanData);

            // Handle file attachments if uploaded
            $fileCategories = [
                'id_card_front' => 'id_front',
                'id_card_back' => 'id_back',
                'income_proof' => 'income_proof',
                'collateral_photo' => 'collateral',
            ];

            if (Schema::connection('mysql_loan')->hasTable('loan_files')) {
                $columns = Schema::connection('mysql_loan')->getColumnListing('loan_files');
                foreach ($fileCategories as $inputName => $category) {
                    if ($request->hasFile($inputName)) {
                        $file = $request->file($inputName);
                        $disk = 'public';
                        $folder = 'loan-files/'.$loanId;
                        $path = $file->store($folder, $disk);

                        $filePayload = [
                            'fileable_type' => 'loan',
                            'fileable_id' => $loanId,
                            'category' => $category,
                            'disk' => $disk,
                            'path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getClientMimeType(),
                            'size_bytes' => $file->getSize(),
                            'uploaded_by' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        DB::connection('mysql_loan')->table('loan_files')->insert(
                            array_intersect_key($filePayload, array_flip($columns))
                        );
                    }
                }
            }

            if (! empty($validated['guarantor_name']) && Schema::connection('mysql_loan')->hasTable('loan_guarantors')) {
                $gColumns = Schema::connection('mysql_loan')->getColumnListing('loan_guarantors');
                $gPayload = [
                    'loan_id' => $loanId,
                    'customer_id' => $customer->id,
                    'name' => $validated['guarantor_name'],
                    'phone' => $validated['guarantor_phone'] ?? null,
                    'relationship' => $validated['guarantor_relationship'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                DB::connection('mysql_loan')->table('loan_guarantors')->insert(
                    array_intersect_key($gPayload, array_flip($gColumns))
                );
            }

            return redirect()
                ->route('loan-management.public.customer-dashboard')
                ->with('status', 'Your installment loan request (ID #'.$loanId.') has been submitted successfully and is pending review.');
        } catch (\Throwable $e) {
            Log::error('Customer loan request error: '.$e->getMessage(), ['exception' => $e]);
            return back()->withErrors(['error' => 'Unable to submit loan request: '.$e->getMessage()])->withInput();
        }
    }

    protected function catalogProducts(): array
    {
        $products = [];

        // 1. Standalone Loan Products (Modules\LoanManagement\Entities\LoanProduct)
        if (Schema::connection('mysql_loan')->hasTable('loan_products')) {
            $loanProducts = LoanProduct::query()
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->get();

            foreach ($loanProducts as $lp) {
                $meta = is_array($lp->meta_json) ? $lp->meta_json : (json_decode((string) $lp->meta_json, true) ?: []);
                $products[] = [
                    'id' => $lp->id,
                    'product_id' => $lp->id,
                    'variation_id' => 0,
                    'name' => $lp->name,
                    'sku' => $lp->sku ?: '',
                    'imei' => $lp->imei ?: '',
                    'price' => round((float) $lp->selling_price, 2),
                    'cost_price' => round((float) ($lp->cost_price ?? 0), 2),
                    'image_url' => $lp->image_url,
                    'brand' => $lp->brand ?: ($meta['brand'] ?? ''),
                    'category' => $lp->category ?: ($meta['category'] ?? 'General'),
                    'min_down_payment_percent' => $lp->min_down_payment_percent,
                    'description' => $lp->description ?: ($meta['description'] ?? ''),
                    'qty_available' => (int) ($lp->qty_available ?? 1),
                ];
            }
        }

        // 2. POS Products (if available and not already populated)
        if (empty($products) && Schema::hasTable('products')) {
            $query = DB::table('products as p');

            if (Schema::hasTable('variations')) {
                $query->leftJoin('variations as v', 'v.product_id', '=', 'p.id');
            }

            $selects = [
                'p.id as product_id',
                'p.name as name',
            ];

            $selects[] = Schema::hasColumn('products', 'sku') ? 'p.sku as sku' : DB::raw('NULL as sku');
            $selects[] = Schema::hasColumn('products', 'image') ? 'p.image as image' : DB::raw('NULL as image');
            $selects[] = Schema::hasTable('variations') ? 'v.id as variation_id' : DB::raw('NULL as variation_id');
            $selects[] = Schema::hasTable('variations') && Schema::hasColumn('variations', 'name') ? 'v.name as variation_name' : DB::raw('NULL as variation_name');
            $selects[] = Schema::hasTable('variations') && Schema::hasColumn('variations', 'sub_sku') ? 'v.sub_sku as sub_sku' : DB::raw('NULL as sub_sku');
            $selects[] = Schema::hasTable('variations') && Schema::hasColumn('variations', 'default_sell_price') ? 'v.default_sell_price as price' : DB::raw('0 as price');

            if (Schema::hasColumn('products', 'not_for_selling')) {
                $query->where(function ($q) {
                    $q->whereNull('p.not_for_selling')->orWhere('p.not_for_selling', 0);
                });
            }

            if (Schema::hasColumn('products', 'is_inactive')) {
                $query->where(function ($q) {
                    $q->whereNull('p.is_inactive')->orWhere('p.is_inactive', 0);
                });
            }

            $posProducts = $query->select($selects)->orderByDesc('p.id')->limit(24)->get();
            foreach ($posProducts as $row) {
                $products[] = $this->formatCatalogProduct($row);
            }
        }

        return $products;
    }

    protected function formatCatalogProduct(object $row): array
    {
        $name = trim((string) ($row->name ?? 'Product'));
        $variation = trim((string) ($row->variation_name ?? ''));
        if ($variation !== '' && strtolower($variation) !== 'dummy') {
            $name .= ' - '.$variation;
        }

        return [
            'id' => (int) ($row->variation_id ?: $row->product_id),
            'product_id' => (int) ($row->product_id ?? 0),
            'variation_id' => (int) ($row->variation_id ?? 0),
            'name' => $name,
            'sku' => trim((string) ($row->sub_sku ?? $row->sku ?? '')),
            'imei' => '',
            'price' => round((float) ($row->price ?? 0), 2),
            'cost_price' => 0.0,
            'image_url' => $this->productImageUrl((string) ($row->image ?? '')),
            'brand' => '',
            'category' => 'General',
            'min_down_payment_percent' => 0,
            'description' => '',
            'qty_available' => 1,
        ];
    }

    protected function productImageUrl(string $image): ?string
    {
        $image = trim($image);
        if ($image === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        foreach (['uploads/img/'.$image, 'uploads/products/'.$image, $image] as $path) {
            $path = ltrim($path, '/');
            if (is_file(public_path($path))) {
                return asset($path);
            }
        }

        return null;
    }

    protected function installmentRequestNote(string $itemsJson): ?string
    {
        $items = json_decode($itemsJson, true);
        if (! is_array($items) || empty($items)) {
            return null;
        }

        $lines = ['Public installment request:'];
        $total = 0;
        foreach (array_slice($items, 0, 20) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? 'Product'));
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $price = round((float) ($item['price'] ?? 0), 2);
            $total += $qty * $price;
            $sku = trim((string) ($item['sku'] ?? ''));
            $lines[] = '- '.$name.($sku !== '' ? ' (SKU: '.$sku.')' : '').' x'.$qty.' = '.number_format($qty * $price, 2);
        }

        $lines[] = 'Estimated total: '.number_format($total, 2);

        return implode("\n", $lines);
    }

    public function appSettings()
    {
        return $this->ok('App settings loaded', [
            'app_name' => 'LoanManagement',
            'support_chat' => true,
            'support_gps' => true,
            'support_aba_payway' => true,
            'support_file_upload' => true,
            'chat_polling_seconds' => (int) config('loanmanagement.chat.polling_interval_seconds', config('loanmanagement.chat_polling_seconds', 5)),
            'customer_api_guard' => (string) config('loanmanagement.customer_api_guard', 'customer_loan_api'),
        ]);
    }

    public function appVersion()
    {
        return $this->ok('App version loaded', [
            'module' => 'LoanManagement',
            'version' => (string) config('loanmanagement.version', '1.0.0'),
            'min_flutter_version' => '1.0.0',
        ]);
    }
}
