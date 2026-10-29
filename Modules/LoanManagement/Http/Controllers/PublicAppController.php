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
use Illuminate\Http\UploadedFile;
use Modules\LoanManagement\Entities\Loan;

class PublicAppController extends Controller
{
    use ApiResponseTrait;

    public function home(Request $request)
    {
        if (! BusinessSettingsService::isCmsEnabled()) {
            return Route::has('login')
                ? redirect()->route('login')
                : redirect('/login');
        }

        $langParam = $request->query('lang') ?? $request->query('language');
        if ($langParam && in_array($langParam, ['en', 'km'], true)) {
            $user = $request->session()->get('user', []);
            $user['language'] = $langParam;
            $request->session()->put('user', $user);
            $request->session()->put('user.language', $langParam);
            cookie()->queue(cookie()->forever('lm_lang', $langParam));
            app()->setLocale($langParam);
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
        if (request()->has('product_id') || request()->has('apply') || request()->has('cart')) {
            return redirect()->route('loan-management.public.home', array_filter([
                'apply' => 1,
                'product_id' => request('product_id'),
                'months' => request('months'),
                'down_payment' => request('down_payment'),
            ]));
        }

        return view('loanmanagement::public.register', [
            'settings' => BusinessSettingsService::get(),
        ]);
    }

    public function storeRegistration(Request $request, LoanCustomerService $customers)
    {
        if (! BusinessSettingsService::isCustomerLoginEnabled()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Customer portal registration is disabled.'], 403);
            }
            return response('Customer portal registration is disabled.', 403);
        }

        $isInstallmentRequest = $request->boolean('installment_request') || $request->has('installment_items');

        if ($isInstallmentRequest && $request->has('installment_items')) {
            $rawItems = $request->input('installment_items');
            $items = is_string($rawItems) ? json_decode($rawItems, true) : (is_array($rawItems) ? $rawItems : null);
            if (! is_array($items)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'installment_items' => ['Invalid cart items format.'],
                ]);
            }
            $catalogMap = collect($this->catalogProducts())->keyBy('id');
            foreach ($items as $item) {
                if (! is_array($item) || ! isset($item['id']) || ! $catalogMap->has($item['id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'installment_items' => ['One or more items in the cart are invalid or no longer available.'],
                    ]);
                }
                if (! isset($item['qty']) || (int) $item['qty'] <= 0 || (int) $item['qty'] > 99) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'installment_items' => ['Invalid item quantity.'],
                    ]);
                }
            }
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'khmer_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:50',
            'telegram' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => $isInstallmentRequest ? 'required|string|max:1000' : 'nullable|string|max:1000',
            'password' => $isInstallmentRequest ? 'nullable|string' : 'required|string|min:8|confirmed',
            'preferred_months' => 'nullable|integer|min:1|max:120',
            'preferred_down_payment' => 'nullable|numeric|min:0',
            'installment_items' => 'nullable',
            'id_card_front' => 'nullable|array|max:8',
            'id_card_front.*' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'id_card_back' => 'nullable|array|max:8',
            'id_card_back.*' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'income_proof' => 'nullable|array|max:8',
            'income_proof.*' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'collateral_photo' => 'nullable|array|max:8',
            'collateral_photo.*' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
        ]);

        $phone = trim((string) $data['phone']);
        $exists = Schema::connection('mysql_loan')->hasTable('loan_customers')
            && DB::connection('mysql_loan')->table('loan_customers')->where('phone', $phone)->exists();
        if ($exists) {
            return back()
                ->withErrors(['phone' => 'This phone number is already registered. Please login or contact support.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        if ($isInstallmentRequest) {
            $installmentNote = $this->installmentRequestNote((string) ($data['installment_items'] ?? ''));
            if (!empty($data['preferred_months'])) {
                $installmentNote .= "\nPreferred installment term: ".(int) $data['preferred_months'].' months (subject to staff review).';
            }
            if (isset($data['preferred_down_payment']) && $data['preferred_down_payment'] !== null && $data['preferred_down_payment'] !== '') {
                $installmentNote .= "\nProposed down payment: ".number_format((float) $data['preferred_down_payment'], 2).'.';
            }
            unset($data['installment_items'], $data['preferred_months'], $data['preferred_down_payment'], $data['password'], $data['status'], $data['can_login']);
            unset($data['id_card_front'], $data['id_card_back'], $data['income_proof'], $data['collateral_photo']);
            $customerId = $customers->create(array_merge($data, [
                'can_login' => 0,
                'status' => 'pending',
                'customer_type' => 'public_installment_request',
                'note' => $installmentNote,
            ]));
            $this->storePendingCustomerDocuments($request, $customerId);
            $message = 'Your installment request is pending review. Our staff will contact you to follow up.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'status' => 'pending'], 201);
            }
            return redirect()
                ->route('loan-management.public.home')
                ->with('installment_request_success', $message);
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
        if ($disabled = $this->customerPortalDisabledRedirect()) {
            return $disabled;
        }

        if (Auth::guard('customer_loan')->check()) {
            return redirect()->route('loan-management.public.customer-dashboard');
        }

        $settings = BusinessSettingsService::get();
        $demoLogin = BusinessSettingsService::isDemoCustomerLoginEnabled()
            ? \Modules\LoanManagement\Services\PortalDemoService::credentials('customer')
            : null;

        return view('loanmanagement::public.customer_login', compact('settings', 'demoLogin'));
    }

    public function customerLoginStore(Request $request)
    {
        if ($disabled = $this->customerPortalDisabledRedirect()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Customer login portal is currently disabled.'], 403);
            }
            return $disabled;
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
                    ->orWhere('login_phone', $login)
                    ->orWhere('email', $login);
            })
            ->where('can_login', 1)
            ->where('status', 'active')
            ->first();

        if (! $customer || ! Auth::guard('customer_loan')->attempt(['id' => $customer->id, 'password' => $credentials['password']], $request->boolean('remember'))) {
            if ($request->expectsJson()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['login' => 'These credentials do not match our records.']);
            }
            return back()->withErrors(['login' => 'These credentials do not match our records.'])->onlyInput('login');
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->forget('url.intended');
        }
        $request->session()->regenerate();
        $customer->forceFill(['last_login_at' => now()])->save();

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('loan-management.public.customer-dashboard')]);
        }

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
        if ($disabled = $this->customerPortalDisabledRedirect()) {
            return $disabled;
        }

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
                'fileable_type' => LoanCustomer::class,
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
            'id_card_front' => 'nullable|array|max:8',
            'id_card_front.*' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'id_card_back' => 'nullable|array|max:8',
            'id_card_back.*' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'income_proof' => 'nullable|array|max:8',
            'income_proof.*' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'collateral_photo' => 'nullable|array|max:8',
            'collateral_photo.*' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
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
                $disk = 'public';
                $folder = 'loan-files/'.$loanId;

                foreach ($fileCategories as $inputName => $category) {
                    $files = $request->file($inputName);
                    if (! is_array($files)) {
                        $files = $files ? [$files] : [];
                    }

                    foreach ($files as $file) {
                        if (! $file instanceof UploadedFile || ! $file->isValid()) {
                            continue;
                        }

                        $path = $file->store($folder, $disk);
                        if ($path === false) {
                            continue;
                        }

                        $filePayload = [
                            'fileable_type' => Loan::class,
                            'fileable_id' => $loanId,
                            'category' => $category,
                            'disk' => $disk,
                            'path' => $path,
                            'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
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
        if (! is_array($items)) {
            return null;
        }
        if (empty($items)) {
            return "Public installment request:\n- Staff will help customer choose a product.";
        }

        $catalogMap = collect($this->catalogProducts())->keyBy('id');
        $lines = ['Public installment request:'];
        $total = 0;
        foreach (array_slice($items, 0, 20) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? null;
            $catItem = $id ? $catalogMap->get($id) : null;
            $name = $catItem ? ($catItem['name'] ?? 'Product') : trim((string) ($item['name'] ?? 'Product'));
            $sku = $catItem ? ($catItem['sku'] ?? '') : trim((string) ($item['sku'] ?? ''));
            $price = $catItem ? (float) ($catItem['price'] ?? 0) : round((float) ($item['price'] ?? 0), 2);
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $total += $qty * $price;
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


    public function homeImage()
    {
        $path = module_path('LoanManagement', 'Resources/assets/cms-home/hero.jpg');
        $uploaded = BusinessSettingsService::get()['home_hero_path'];
        if (is_string($uploaded) && str_starts_with($uploaded, 'loan-management/cms/') && ! str_contains($uploaded, '..') && \Illuminate\Support\Facades\Storage::disk('public')->exists($uploaded)) {
            $path = \Illuminate\Support\Facades\Storage::disk('public')->path($uploaded);
        }
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'no-cache']);
    }

    protected function customerPortalDisabledRedirect()
    {
        if (BusinessSettingsService::isCustomerLoginEnabled()) {
            return null;
        }

        if (Auth::guard('customer_loan')->check()) {
            Auth::guard('customer_loan')->logout();
        }

        return redirect()->route('loan-management.public.home')
            ->with('status', 'Customer login portal is currently disabled by administrator.');
    }

    public function storePendingCustomerDocuments(Request $request, int $customerId): void
    {
        if ($customerId <= 0 || ! Schema::connection('mysql_loan')->hasTable('loan_files')) {
            return;
        }

        $categories = [
            'id_card_front' => 'id_front',
            'id_card_back' => 'id_back',
            'income_proof' => 'income_proof',
            'collateral_photo' => 'collateral',
        ];

        $columns = Schema::connection('mysql_loan')->getColumnListing('loan_files');
        $disk = 'public';
        $folder = 'loan-customers/'.$customerId;
        $references = [];

        foreach ($categories as $inputName => $category) {
            $files = $request->file($inputName);
            if (! is_array($files)) {
                $files = $files ? [$files] : [];
            }
            foreach ($files as $file) {
                if (! $file instanceof \Illuminate\Http\UploadedFile || ! $file->isValid()) {
                    continue;
                }

                $path = $file->store($folder, $disk);
                if ($path === false) {
                    continue;
                }

                $payload = [
                    'fileable_type' => LoanCustomer::class,
                    'fileable_id' => $customerId,
                    'category' => $category,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                    'uploaded_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $fileId = (int) DB::connection('mysql_loan')->table('loan_files')->insertGetId(
                    array_intersect_key($payload, array_flip($columns))
                );
                $references[$category] = $references[$category] ?? $fileId;
            }
        }

        if ($references && Schema::connection('mysql_loan')->hasTable('loan_customers')) {
            $updates = [];
            foreach (['id_front' => 'id_front_file_id', 'id_back' => 'id_back_file_id'] as $category => $column) {
                if (! empty($references[$category]) && Schema::connection('mysql_loan')->hasColumn('loan_customers', $column)) {
                    $updates[$column] = $references[$category];
                }
            }
            if ($updates) {
                $updates['updated_at'] = now();
                DB::connection('mysql_loan')->table('loan_customers')->where('id', $customerId)->update($updates);
            }
        }
    }
}
