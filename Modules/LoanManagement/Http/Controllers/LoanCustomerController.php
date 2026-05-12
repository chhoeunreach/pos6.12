<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
            foreach (['name', 'phone', 'customer_code', 'id_card_number', 'status'] as $f) {
                if ($request->filled($f) && Schema::connection($this->connection)->hasColumn($this->table, $f)) {
                    $q->where('c.'.$f, 'like', '%'.$request->input($f).'%');
                }
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
        $snapshot = [];
        if (($data['create_mode'] ?? 'new') === 'clone' && ! empty($data['main_contact_id'])) {
            $snapshot = $this->getContactSnapshot((int) $data['main_contact_id']) ?? [];
        }
        $this->customerService->create($data, $snapshot);
        return redirect()->route('loan-management.customers')->with('status', ['success' => 1, 'msg' => 'Loan customer created successfully.']);
    }

    public function show(int $customer)
    {
        $customerRow = DB::connection($this->connection)->table($this->table)->where('id', $customer)->first();
        abort_if(! $customerRow, 404);
        $latestLocation = Schema::connection($this->connection)->hasTable('loan_customer_location_latest')
            ? DB::connection($this->connection)->table('loan_customer_location_latest')->where('customer_id', $customer)->first()
            : null;
        $loans = Schema::connection($this->connection)->hasTable('loans')
            ? DB::connection($this->connection)->table('loans')->where('customer_id', $customer)->orderByDesc('id')->limit(20)->get()
            : collect();
        $payments = Schema::connection($this->connection)->hasTable('loan_payments')
            ? DB::connection($this->connection)->table('loan_payments')->where('customer_id', $customer)->orderByDesc('id')->limit(20)->get()
            : collect();
        return view('loanmanagement::customers.show', compact('customerRow', 'latestLocation', 'loans', 'payments'));
    }

    public function edit(int $customer)
    {
        $customerRow = DB::connection($this->connection)->table($this->table)->where('id', $customer)->first();
        abort_if(! $customerRow, 404);
        $latestLocation = Schema::connection($this->connection)->hasTable('loan_customer_location_latest')
            ? DB::connection($this->connection)->table('loan_customer_location_latest')->where('customer_id', $customer)->first()
            : null;
        return view('loanmanagement::customers.edit', compact('customerRow', 'latestLocation'));
    }

    public function update(UpdateLoanCustomerRequest $request, int $customer)
    {
        $data = $request->validated();
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
}

