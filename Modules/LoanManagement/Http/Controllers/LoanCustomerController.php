<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\LoanManagement\Http\Requests\StoreLoanCustomerRequest;
use Modules\LoanManagement\Http\Requests\UpdateLoanCustomerRequest;
use Modules\LoanManagement\Services\LoanCustomerService;

class LoanCustomerController extends Controller
{
    protected string $connection = 'mysql_loan';
    protected string $table = 'loan_customers';

    public function __construct(protected LoanCustomerService $customerService)
    {
    }

    public function index(Request $request)
    {
        $tableExists = Schema::connection($this->connection)->hasTable($this->table);
        $customers = collect();
        if ($tableExists) {
            $q = DB::connection($this->connection)->table($this->table.' as c')->orderByDesc('c.id');
            foreach (['phone', 'customer_code', 'id_card_number', 'status'] as $f) {
                if ($request->filled($f) && Schema::connection($this->connection)->hasColumn($this->table, $f)) {
                    $q->where('c.'.$f, 'like', '%'.$request->input($f).'%');
                }
            }
            if ($request->filled('name')) {
                $q->where(function ($query) use ($request) {
                    $like = '%'.$request->input('name').'%';
                    if (Schema::connection($this->connection)->hasColumn($this->table, 'khmer_name')) {
                        $query->where('c.khmer_name', 'like', $like)
                            ->orWhere('c.name', 'like', $like);
                    } else {
                        $query->where('c.name', 'like', $like);
                    }
                });
            }
            if ($request->filled('blacklist_status') && Schema::connection($this->connection)->hasColumn($this->table, 'blacklist_status')) {
                $q->where('c.blacklist_status', (int) $request->input('blacklist_status'));
            }
            if ($request->filled('can_login') && Schema::connection($this->connection)->hasColumn($this->table, 'can_login')) {
                $q->where('c.can_login', (int) $request->input('can_login'));
            }
            if ($request->filled('allow_gps_tracking') && Schema::connection($this->connection)->hasColumn($this->table, 'allow_gps_tracking')) {
                $q->where('c.allow_gps_tracking', (int) $request->input('allow_gps_tracking'));
            }
            $customers = $q->paginate(20)->appends($request->query());
        }
        return view('loanmanagement::customers.index', compact('customers', 'tableExists'));
    }

    public function create()
    {
        return view('loanmanagement::customers.create');
    }

    public function store(StoreLoanCustomerRequest $request)
    {
        $data = $request->validated();
        unset($data['customer_photo']);

        $snapshot = [];
        if (($data['create_mode'] ?? 'new') === 'clone' && ! empty($data['main_contact_id'])) {
            $snapshot = $this->getContactSnapshot((int) $data['main_contact_id']) ?? [];
        }
        $customerId = $this->customerService->create($data, $snapshot);
        if ($request->hasFile('customer_photo')) {
            $photoFileId = $this->storeCustomerPhoto($request, $customerId);
            if (Schema::connection($this->connection)->hasColumn($this->table, 'customer_photo_file_id')) {
                DB::connection($this->connection)
                    ->table($this->table)
                    ->where('id', $customerId)
                    ->update(['customer_photo_file_id' => $photoFileId, 'updated_at' => now()]);
            }
        }
        return redirect()->route('loan-management.customers')->with('status', ['success' => 1, 'msg' => 'Loan customer created successfully.']);
    }

    public function show(int $customer)
    {
        $customerRow = DB::connection($this->connection)->table($this->table)->where('id', $customer)->first();
        abort_if(! $customerRow, 404);
        $customerPhotoUrl = $this->customerPhotoUrl($customerRow);
        $latestLocation = Schema::connection($this->connection)->hasTable('loan_customer_location_latest')
            ? DB::connection($this->connection)->table('loan_customer_location_latest')->where('customer_id', $customer)->first()
            : null;
        $relatedData = $this->getCustomerRelatedData($customer);
        return view('loanmanagement::customers.show', array_merge([
            'customerRow' => $customerRow,
            'customerPhotoUrl' => $customerPhotoUrl,
            'latestLocation' => $latestLocation,
        ], $relatedData));
    }

    public function edit(int $customer)
    {
        $customerRow = DB::connection($this->connection)->table($this->table)->where('id', $customer)->first();
        abort_if(! $customerRow, 404);
        $customerPhotoUrl = $this->customerPhotoUrl($customerRow);
        $latestLocation = Schema::connection($this->connection)->hasTable('loan_customer_location_latest')
            ? DB::connection($this->connection)->table('loan_customer_location_latest')->where('customer_id', $customer)->first()
            : null;
        $relatedData = $this->getCustomerRelatedData($customer);

        return view('loanmanagement::customers.edit', array_merge([
            'customerRow' => $customerRow,
            'customerPhotoUrl' => $customerPhotoUrl,
            'latestLocation' => $latestLocation,
        ], $relatedData));
    }

    public function update(UpdateLoanCustomerRequest $request, int $customer)
    {
        $customerRow = DB::connection($this->connection)->table($this->table)->where('id', $customer)->first();
        abort_if(! $customerRow, 404);

        if ((int) $request->input('expected_customer_id', 0) > 0
            && (int) $request->input('expected_customer_id') !== (int) $customerRow->id) {
            abort(409, 'Customer form does not match the customer being updated. Please reload and try again.');
        }

        $expectedCustomerCode = trim((string) $request->input('expected_customer_code', ''));
        $currentCustomerCode = trim((string) ($customerRow->customer_code ?? ''));
        if ($expectedCustomerCode !== '' && $currentCustomerCode !== '' && $expectedCustomerCode !== $currentCustomerCode) {
            abort(409, 'Customer code changed or request target is wrong. Please reload and try again.');
        }

        $data = $request->validated();
        unset($data['customer_photo']);
        if ($request->hasFile('customer_photo')) {
            $data['customer_photo_file_id'] = $this->storeCustomerPhoto($request, $customer);
        }
        if (! empty($request->input('password'))) {
            $data['password'] = $request->input('password');
        }
        $this->customerService->update($customer, $data);
        return redirect()->route('loan-management.customers.edit', $customer)->with('status', ['success' => 1, 'msg' => 'Loan customer updated successfully.']);
    }

    public function destroy(int $customer)
    {
        DB::connection($this->connection)->table($this->table)->where('id', $customer)->delete();
        return redirect()->route('loan-management.customers')->with('status', ['success' => 1, 'msg' => 'Loan customer deleted successfully.']);
    }

    public function blacklist(Request $request, int $customer)
    {
        $request->validate(['blacklist_status' => 'required|boolean', 'blacklist_reason' => 'nullable|string|max:1000']);
        DB::connection($this->connection)->table($this->table)->where('id', $customer)->update($this->filterColumns([
            'blacklist_status' => (int) $request->input('blacklist_status'),
            'blacklist_reason' => $request->input('blacklist_reason'),
            'blacklist_date' => $request->boolean('blacklist_status') ? now() : null,
            'blacklist_by' => $request->boolean('blacklist_status') ? auth()->id() : null,
            'updated_at' => now(),
        ]));
        return back()->with('status', ['success' => 1, 'msg' => 'Blacklist status updated.']);
    }

    public function enableLogin(int $customer)
    {
        DB::connection($this->connection)->table($this->table)->where('id', $customer)->update($this->filterColumns(['can_login' => 1, 'updated_at' => now()]));
        return back()->with('status', ['success' => 1, 'msg' => 'Customer login enabled.']);
    }

    public function disableLogin(int $customer)
    {
        DB::connection($this->connection)->table($this->table)->where('id', $customer)->update($this->filterColumns(['can_login' => 0, 'updated_at' => now()]));
        return back()->with('status', ['success' => 1, 'msg' => 'Customer login disabled.']);
    }

    public function resetPassword(Request $request, int $customer)
    {
        $request->validate(['new_password' => 'required|string|min:8']);
        DB::connection($this->connection)->table($this->table)->where('id', $customer)->update($this->filterColumns([
            'password' => Hash::make((string) $request->input('new_password')),
            'updated_at' => now(),
        ]));
        return back()->with('status', ['success' => 1, 'msg' => 'Customer app password reset successfully.']);
    }

    public function enableGpsTracking(int $customer)
    {
        DB::connection($this->connection)->table($this->table)->where('id', $customer)->update($this->filterColumns(['allow_gps_tracking' => 1, 'gps_tracking_started_at' => now(), 'updated_at' => now()]));
        return back()->with('status', ['success' => 1, 'msg' => 'GPS tracking enabled.']);
    }

    public function disableGpsTracking(int $customer)
    {
        DB::connection($this->connection)->table($this->table)->where('id', $customer)->update($this->filterColumns(['allow_gps_tracking' => 0, 'gps_tracking_stopped_at' => now(), 'updated_at' => now()]));
        return back()->with('status', ['success' => 1, 'msg' => 'GPS tracking disabled.']);
    }

    public function generateTelegramLink(int $customer)
    {
        $row = DB::connection($this->connection)->table($this->table)->where('id', $customer)->first();
        abort_if(! $row, 404);
        abort_if(! empty($row->telegram_chat_id), 422, 'This customer is already connected to Telegram.');

        $botUsername = trim(\Modules\LoanManagement\Services\TelegramSettingsService::botUsername());
        abort_if($botUsername === '', 422, 'Telegram bot is not configured yet. Ask an admin to set it up under System Settings > Telegram Bot.');

        $token = \Illuminate\Support\Str::random(40);
        $expiresAt = now()->addMinutes(\Modules\LoanManagement\Services\TelegramSettingsService::linkTtlMinutes());

        DB::connection($this->connection)->table($this->table)->where('id', $customer)->update($this->filterColumns([
            'telegram_link_token' => $token,
            'telegram_link_token_expires_at' => $expiresAt,
            'updated_at' => now(),
        ]));

        return response()->json([
            'success' => true,
            'link' => 'https://t.me/'.$botUsername.'?start='.$token,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function unlinkTelegram(int $customer)
    {
        $row = DB::connection($this->connection)->table($this->table)->where('id', $customer)->first();
        abort_if(! $row, 404);

        DB::connection($this->connection)->table($this->table)->where('id', $customer)->update($this->filterColumns([
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'telegram_linked_at' => null,
            'updated_at' => now(),
        ]));

        return response()->json(['success' => true]);
    }

    public function syncFromUltimatePos(int $customer)
    {
        $row = DB::connection($this->connection)->table($this->table)->where('id', $customer)->first();
        abort_if(! $row || empty($row->main_contact_id), 404);
        $snapshot = $this->getContactSnapshot((int) $row->main_contact_id);
        abort_if(! $snapshot, 404);
        $this->customerService->update($customer, $snapshot);
        return back()->with('status', ['success' => 1, 'msg' => 'Synced from Ultimate POS.']);
    }

    public function cloneFromUltimatePos()
    {
        return view('loanmanagement::customers.clone_from_pos');
    }

    public function cloneFromUltimatePosStore(Request $request)
    {
        $request->validate(['main_contact_id' => 'required|integer']);
        $snapshot = $this->getContactSnapshot((int) $request->input('main_contact_id'));
        abort_if(! $snapshot, 404);
        $id = $this->customerService->create([
            'name' => $snapshot['name'] ?? 'Unknown',
            'phone' => $snapshot['phone'] ?? '-',
            'status' => 'active',
            'create_mode' => 'clone',
            'main_contact_id' => $snapshot['main_contact_id'] ?? null,
            'business_location_id' => $snapshot['business_location_id'] ?? null,
        ], $snapshot);
        return redirect()->route('loan-management.customers.edit', $id)->with('status', ['success' => 1, 'msg' => 'Customer cloned from Ultimate POS.']);
    }

    public function searchMainContacts(Request $request)
    {
        abort_if(! Schema::hasTable('contacts'), 404);
        $q = trim((string) $request->input('q', ''));
        $query = DB::table('contacts as c')->where('c.type', 'customer');
        $query->select([
            'c.id',
            'c.name',
            'c.mobile',
            'c.alternate_number',
            'c.email',
            'c.contact_id as customer_code',
            'c.supplier_business_name',
            'c.address_line_1',
            'c.address_line_2',
            'c.city',
            'c.state',
            'c.country',
            'c.zip_code',
        ])->limit(50)->orderByDesc('c.id');
        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('c.name', 'like', '%'.$q.'%')
                    ->orWhere('c.mobile', 'like', '%'.$q.'%')
                    ->orWhere('c.contact_id', 'like', '%'.$q.'%')
                    ->orWhere('c.email', 'like', '%'.$q.'%');
            });
        }
        return response()->json(['data' => $query->get()]);
    }

    protected function getContactSnapshot(int $contactId): ?array
    {
        if (! Schema::hasTable('contacts')) return null;
        $q = DB::table('contacts as c')->where('c.id', $contactId);
        $select = [
            'c.id as main_contact_id', 'c.name', 'c.mobile', 'c.alternate_number', 'c.email', 'c.contact_id',
            'c.supplier_business_name', 'c.address_line_1', 'c.address_line_2', 'c.city', 'c.state', 'c.country', 'c.zip_code',
        ];
        if (Schema::hasColumn('contacts', 'gender')) $select[] = 'c.gender';
        if (Schema::hasColumn('contacts', 'dob')) $select[] = 'c.dob';
        if (Schema::hasColumn('contacts', 'date_of_birth')) $select[] = 'c.date_of_birth';
        if (Schema::hasColumn('contacts', 'id_card_number')) $select[] = 'c.id_card_number';
        if (Schema::hasColumn('contacts', 'custom_field1')) $select[] = 'c.custom_field1';
        if (Schema::hasColumn('contacts', 'business_id')) $select[] = 'c.business_id';
        $row = $q->select($select)->first();
        if (! $row) return null;
        $address = trim(implode(' ', array_filter([
            $row->address_line_1 ?? null, $row->address_line_2 ?? null, $row->city ?? null, $row->state ?? null, $row->country ?? null, $row->zip_code ?? null,
        ])));
        return [
            'main_contact_id' => $row->main_contact_id,
            'business_location_id' => $row->business_id ?? null,
            'name' => $row->name,
            'phone' => $row->mobile,
            'alternate_phone' => $row->alternate_number,
            'email' => $row->email,
            'gender' => $row->gender ?? null,
            'date_of_birth' => $row->dob ?? ($row->date_of_birth ?? null),
            'id_card_number' => $row->id_card_number ?? ($row->custom_field1 ?? null),
            'address' => $address,
            'business_name_snapshot' => $row->supplier_business_name ?? null,
        ];
    }

    protected function filterColumns(array $payload): array
    {
        $columns = Schema::connection($this->connection)->getColumnListing($this->table);
        return array_intersect_key($payload, array_flip($columns));
    }

    protected function storeCustomerPhoto(Request $request, ?int $customerId = null): int
    {
        abort_unless(Schema::connection($this->connection)->hasTable('loan_files'), 500, 'loan_files table is not available.');

        $file = $request->file('customer_photo');
        $disk = 'public';
        $folder = $customerId ? 'loan-customers/'.$customerId : 'loan-customers/temp';
        $path = $file->store($folder, $disk);

        $payload = [
            'fileable_type' => 'loan_customer',
            'fileable_id' => $customerId ?? 0,
            'category' => 'customer_photo',
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $columns = Schema::connection($this->connection)->getColumnListing('loan_files');

        return (int) DB::connection($this->connection)->table('loan_files')->insertGetId(array_intersect_key($payload, array_flip($columns)));
    }

    protected function customerPhotoUrl(object $customer): ?string
    {
        if (empty($customer->customer_photo_file_id) || ! Schema::connection($this->connection)->hasTable('loan_files')) {
            return null;
        }

        $file = DB::connection($this->connection)->table('loan_files')->where('id', $customer->customer_photo_file_id)->first();
        if (! $file || empty($file->path)) {
            return null;
        }

        return Storage::disk($file->disk ?? 'public')->url($file->path);
    }

    protected function attachFileToCustomer(int $fileId, int $customerId): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_files')) {
            return;
        }

        DB::connection($this->connection)->table('loan_files')->where('id', $fileId)->update(array_intersect_key([
            'fileable_type' => 'loan_customer',
            'fileable_id' => $customerId,
            'updated_at' => now(),
        ], array_flip(Schema::connection($this->connection)->getColumnListing('loan_files'))));
    }

    protected function getCustomerRelatedData(int $customer): array
    {
        $loans = collect();
        $payments = collect();

        if (Schema::connection($this->connection)->hasTable('loans')) {
            $loans = DB::connection($this->connection)
                ->table('loans')
                ->where('customer_id', $customer)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        if (Schema::connection($this->connection)->hasTable('loan_payments')) {
            $paymentsQuery = DB::connection($this->connection)
                ->table('loan_payments as p')
                ->leftJoin('loans as l', 'l.id', '=', 'p.loan_id')
                ->where(function ($query) use ($customer) {
                    $query->where('p.customer_id', $customer)
                        ->orWhere('l.customer_id', $customer);
                })
                ->select([
                    'p.*',
                    'l.loan_number',
                    'l.customer_id as loan_customer_id',
                ])
                ->orderByDesc('p.id')
                ->limit(20);

            if (Schema::connection($this->connection)->hasColumn('loan_payments', 'paid_date')) {
                $paymentsQuery->orderByDesc('p.paid_date');
            } elseif (Schema::connection($this->connection)->hasColumn('loan_payments', 'paid_at')) {
                $paymentsQuery->orderByDesc('p.paid_at');
            }

            $payments = $paymentsQuery->get();
        }

        return [
            'loans' => $loans,
            'payments' => $payments,
            'loanSummary' => [
                'count' => $loans->count(),
                'principal' => (float) $loans->sum(fn ($loan) => (float) ($loan->principal_amount ?? 0)),
                'balance' => (float) $loans->sum(fn ($loan) => (float) ($loan->balance_amount ?? 0)),
            ],
            'paymentSummary' => [
                'count' => $payments->count(),
                'amount' => (float) $payments->sum(function ($payment) {
                    return (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);
                }),
            ],
        ];
    }
}
