<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class LoanInstallmentListController extends Controller
{
    protected function hasCol(string $col): bool
    {
        return Schema::connection('mysql_loan')->hasColumn('loans', $col);
    }

    public function index()
    {
        $locations = [];
        $collectors = [];

        if (Schema::connection('mysql_loan')->hasTable('loans')) {
            if ($this->hasCol('location_name_snapshot')) {
                $locations = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('location_name_snapshot')
                    ->distinct()
                    ->orderBy('location_name_snapshot')
                    ->pluck('location_name_snapshot', 'location_name_snapshot');
            } elseif ($this->hasCol('business_location_id')) {
                $locations = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('business_location_id')
                    ->distinct()
                    ->orderBy('business_location_id')
                    ->pluck('business_location_id', 'business_location_id')
                    ->mapWithKeys(fn ($v) => [$v => 'Location #'.$v]);
            }

            if ($this->hasCol('collector_name_snapshot')) {
                $collectors = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('collector_name_snapshot')
                    ->distinct()
                    ->orderBy('collector_name_snapshot')
                    ->pluck('collector_name_snapshot', 'collector_name_snapshot');
            } elseif ($this->hasCol('collector_id')) {
                $collectors = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('collector_id')
                    ->distinct()
                    ->orderBy('collector_id')
                    ->pluck('collector_id', 'collector_id')
                    ->mapWithKeys(fn ($v) => [$v => 'Collector #'.$v]);
            }
        }

        return view('loanmanagement::loans.index', compact('locations', 'collectors'));
    }

    public function data(Request $request)
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')) {
            return DataTables::of(collect([]))->make(true);
        }

        $q = DB::connection('mysql_loan')->table('loans as l')
            ->selectRaw(
                'l.id, '.
                ($this->hasCol('loan_number') ? 'l.loan_number' : 'CAST(l.id as CHAR)').' as loan_number, '.
                ($this->hasCol('loan_date') ? 'l.loan_date' : 'l.created_at').' as loan_date, '.
                ($this->hasCol('customer_name_snapshot') ? 'l.customer_name_snapshot' : 'NULL').' as customer_name_snapshot, '.
                ($this->hasCol('customer_phone_snapshot') ? 'l.customer_phone_snapshot' : 'NULL').' as customer_phone_snapshot, '.
                ($this->hasCol('location_name_snapshot') ? 'l.location_name_snapshot' : ($this->hasCol('business_location_id') ? "CONCAT('Location #', l.business_location_id)" : 'NULL')).' as location_name_snapshot, '.
                ($this->hasCol('principal_amount') ? 'l.principal_amount' : '0').' as principal_amount, '.
                ($this->hasCol('paid_amount') ? 'l.paid_amount' : '0').' as paid_amount, '.
                ($this->hasCol('balance_amount') ? 'l.balance_amount' : '0').' as balance_amount, '.
                ($this->hasCol('status') ? 'l.status' : "'pending'").' as status, '.
                ($this->hasCol('currency') ? 'l.currency' : "'USD'").' as currency, '.
                ($this->hasCol('source_invoice_no') ? 'l.source_invoice_no' : 'NULL').' as source_invoice_no, '.
                ($this->hasCol('collector_name_snapshot') ? 'l.collector_name_snapshot' : ($this->hasCol('collector_id') ? "CONCAT('Collector #', l.collector_id)" : 'NULL')).' as collector_name_snapshot'
            );

        if ($request->filled('start_date')) $q->whereDate('l.loan_date', '>=', $request->start_date);
        if ($request->filled('end_date')) $q->whereDate('l.loan_date', '<=', $request->end_date);
        if ($request->filled('status') && $this->hasCol('status')) $q->where('l.status', $request->status);
        if ($request->filled('location_name')) {
            if ($this->hasCol('location_name_snapshot')) {
                $q->where('l.location_name_snapshot', $request->location_name);
            } elseif ($this->hasCol('business_location_id')) {
                $id = preg_replace('/\D+/', '', (string) $request->location_name);
                if ($id !== '') $q->where('l.business_location_id', (int) $id);
            }
        }
        if ($request->filled('collector_name')) {
            if ($this->hasCol('collector_name_snapshot')) {
                $q->where('l.collector_name_snapshot', $request->collector_name);
            } elseif ($this->hasCol('collector_id')) {
                $id = preg_replace('/\D+/', '', (string) $request->collector_name);
                if ($id !== '') $q->where('l.collector_id', (int) $id);
            }
        }
        if ($request->filled('customer') && $this->hasCol('customer_name_snapshot')) $q->where('l.customer_name_snapshot', 'like', '%'.$request->customer.'%');

        return DataTables::of($q)
            ->editColumn('principal_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->principal_amount.'</span>')
            ->editColumn('paid_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->paid_amount.'</span>')
            ->editColumn('balance_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->balance_amount.'</span>')
            ->editColumn('status', function ($r) {
                $map = ['draft' => 'default', 'pending' => 'warning', 'approved' => 'info', 'active' => 'primary', 'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'default', 'defaulted' => 'danger'];
                $c = $map[$r->status] ?? 'default';
                return '<span class="label label-'.$c.'">'.ucfirst($r->status).'</span>';
            })
            ->addColumn('action', function ($r) {
                $user = auth()->user();
                $view = '<a href="'.route('loan-management.loans.view', $r->id).'" class="btn btn-xs btn-info">View</a>';
                $edit = ($user && $user->can('loan_management.edit') && in_array(strtolower((string) $r->status), ['draft', 'pending']))
                    ? ' <a href="'.route('loan-management.loans.edit', $r->id).'" class="btn btn-xs btn-primary">Edit</a>'
                    : '';
                $delete = ($user && $user->can('loan_management.delete') && in_array(strtolower((string) $r->status), ['draft', 'pending']))
                    ? ' <button type="button" class="btn btn-xs btn-danger btn-delete-loan" data-url="'.route('loan-management.loans.destroy', $r->id).'">Delete</button>'
                    : '';

                $statusBtn = '';
                if ($user && $user->can('loan_management.approve')) {
                    $statusBtn = ' <div class="btn-group">
                        <button type="button" class="btn btn-xs btn-warning dropdown-toggle" data-toggle="dropdown">Status <span class="caret"></span></button>
                        <ul class="dropdown-menu dropdown-menu-right">
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="pending">Pending</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="approved">Approved</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="active">Active</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="completed">Completed</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="rejected">Rejected</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="cancelled">Cancelled</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="defaulted">Defaulted</a></li>
                        </ul>
                    </div>';
                }

                return $view.$edit.$delete.$statusBtn;
            })
            ->rawColumns(['status', 'principal_amount', 'paid_amount', 'balance_amount', 'action'])
            ->make(true);
    }

    public function show(int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $customerRow = null;
        if (Schema::connection('mysql_loan')->hasTable('loan_customers') && isset($loanRow->customer_id) && $loanRow->customer_id) {
            $customerRow = DB::connection('mysql_loan')->table('loan_customers')->where('id', $loanRow->customer_id)->first();
        }
        $customerDisplayName = $loanRow->customer_name_snapshot ?? null;
        $customerPhoneDisplay = $loanRow->customer_phone_snapshot ?? null;
        $customerAddressDisplay = $loanRow->customer_address_snapshot ?? null;
        $mainContactIdDisplay = $loanRow->main_contact_id ?? null;
        $sourceTypeDisplay = $loanRow->source_type ?? null;
        $sourceTransactionIdDisplay = $loanRow->source_transaction_id ?? null;
        $sourceInvoiceDisplay = $loanRow->source_invoice_no ?? null;
        $sourceFinalTotalDisplay = $loanRow->sell_final_total_snapshot ?? null;
        $sourcePaidDisplay = $loanRow->sell_paid_amount_snapshot ?? null;
        $sourceDueDisplay = $loanRow->sell_due_amount_snapshot ?? null;
        if ($customerRow) {
            $first = isset($customerRow->first_name) ? trim((string) $customerRow->first_name) : '';
            $last = isset($customerRow->last_name) ? trim((string) $customerRow->last_name) : '';
            $fullFromParts = trim($first.' '.$last);
            if (!empty($fullFromParts)) {
                $customerDisplayName = $fullFromParts;
            } elseif (empty($customerDisplayName)) {
                $customerDisplayName = $customerRow->customer_name ?? ($customerRow->name ?? ($customerRow->full_name ?? null));
            }
        }
        if (empty($customerDisplayName)) {
            $customerDisplayName = '-';
        }
        $customerDisplayName = Str::of((string) $customerDisplayName)->squish()->value();

        // Fallback from core contact table if snapshot is incomplete.
        if ((empty($customerDisplayName) || $customerDisplayName === '-') && !empty($loanRow->main_contact_id) && Schema::hasTable('contacts')) {
            $contactCols = Schema::getColumnListing('contacts');
            $selectCols = array_values(array_intersect(['id', 'name', 'mobile', 'address_line_1'], $contactCols));
            if (!empty($selectCols)) {
                $contact = DB::table('contacts')->select($selectCols)->where('id', $loanRow->main_contact_id)->first();
                if ($contact) {
                    if (!empty($contact->name)) {
                        $customerDisplayName = trim((string) $contact->name);
                    }
                    if (empty($customerAddressDisplay) && !empty($contact->address_line_1)) {
                        $customerAddressDisplay = trim((string) $contact->address_line_1);
                    }
                    if (empty($customerPhoneDisplay) && !empty($contact->mobile)) {
                        $customerPhoneDisplay = trim((string) $contact->mobile);
                    }
                    if (empty($mainContactIdDisplay) && !empty($contact->id)) {
                        $mainContactIdDisplay = (int) $contact->id;
                    }
                }
            }
        }

        // Fallback: if contact id is missing, try resolving customer by phone from core contacts.
        if ((empty($customerDisplayName) || $customerDisplayName === '-') && !empty($customerPhoneDisplay) && Schema::hasTable('contacts')) {
            $contactCols = Schema::getColumnListing('contacts');
            $selectCols = array_values(array_intersect(['id', 'name', 'mobile', 'address_line_1', 'alternate_number', 'landline'], $contactCols));
            if (!empty($selectCols)) {
                $contact = DB::table('contacts')
                    ->select($selectCols)
                    ->where(function ($q) use ($customerPhoneDisplay, $contactCols) {
                        $q->where('mobile', $customerPhoneDisplay);
                        if (in_array('alternate_number', $contactCols, true)) {
                            $q->orWhere('alternate_number', $customerPhoneDisplay);
                        }
                        if (in_array('landline', $contactCols, true)) {
                            $q->orWhere('landline', $customerPhoneDisplay);
                        }
                    })
                    ->orderByDesc('id')
                    ->first();

                if ($contact) {
                    if (!empty($contact->name)) {
                        $customerDisplayName = trim((string) $contact->name);
                    }
                    if (empty($customerAddressDisplay) && !empty($contact->address_line_1)) {
                        $customerAddressDisplay = trim((string) $contact->address_line_1);
                    }
                    if (empty($mainContactIdDisplay) && !empty($contact->id)) {
                        $mainContactIdDisplay = (int) $contact->id;
                    }
                }
            }
        }

        $locationRow = null;
        if (Schema::connection('mysql_loan')->hasTable('loan_business_locations') && isset($loanRow->business_location_id) && $loanRow->business_location_id) {
            $locationRow = DB::connection('mysql_loan')->table('loan_business_locations')->where('id', $loanRow->business_location_id)->first();
        }
        $locationDisplayName = $loanRow->location_name_snapshot ?? ($locationRow->name ?? null);
        if (empty($locationDisplayName)) {
            $mainLocationId = $loanRow->main_location_id ?? $loanRow->business_location_id ?? null;
            if (!empty($mainLocationId) && Schema::hasTable('business_locations')) {
                $blCols = Schema::getColumnListing('business_locations');
                if (in_array('name', $blCols, true)) {
                    $mainLocation = DB::table('business_locations')
                        ->select(array_intersect(['id', 'name'], $blCols))
                        ->where('id', $mainLocationId)
                        ->first();
                    if ($mainLocation) {
                        $locationDisplayName = trim((string) ($mainLocation->name ?? ''));
                    }
                }
            }
        }
        if (empty($locationDisplayName)) {
            $locationDisplayName = !empty($loanRow->main_location_id)
                ? ('Location #'.$loanRow->main_location_id)
                : (!empty($loanRow->business_location_id) ? ('Location #'.$loanRow->business_location_id) : '-');
        }
        $locationAddressDisplay = null;
        if ($locationRow) {
            $locationAddressDisplay = $locationRow->address
                ?? $locationRow->location_address_snapshot
                ?? $locationRow->landmark
                ?? null;
        }

        // Fallback from source sell when snapshot fields are missing.
        if (!empty($loanRow->source_transaction_id) && Schema::hasTable('transactions')) {
            $source = DB::table('transactions as t')
                ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
                ->where('t.id', $loanRow->source_transaction_id)
                ->selectRaw('t.id, t.type, t.invoice_no, t.final_total, t.location_id, c.name as customer_name, c.mobile as customer_phone, c.address_line_1 as customer_address, bl.name as location_name, bl.landmark as location_landmark')
                ->first();

            if ($source) {
                if (empty($sourceTypeDisplay)) {
                    $sourceTypeDisplay = $source->type;
                }
                if (empty($sourceTransactionIdDisplay)) {
                    $sourceTransactionIdDisplay = $source->id;
                }
                if (empty($sourceInvoiceDisplay)) {
                    $sourceInvoiceDisplay = $source->invoice_no;
                }
                if ((empty($customerDisplayName) || $customerDisplayName === '-') && !empty($source->customer_name)) {
                    $customerDisplayName = trim((string) $source->customer_name);
                }
                if (empty($customerPhoneDisplay) && !empty($source->customer_phone)) {
                    $customerPhoneDisplay = trim((string) $source->customer_phone);
                }
                if (empty($customerAddressDisplay) && !empty($source->customer_address)) {
                    $customerAddressDisplay = trim((string) $source->customer_address);
                }
                if ((empty($locationDisplayName) || $locationDisplayName === '-') && !empty($source->location_name)) {
                    $locationDisplayName = trim((string) $source->location_name);
                }
                if (empty($locationAddressDisplay) && !empty($source->location_landmark)) {
                    $locationAddressDisplay = trim((string) $source->location_landmark);
                }
                if ($sourceFinalTotalDisplay === null && isset($source->final_total)) {
                    $sourceFinalTotalDisplay = $source->final_total;
                }
                if ($sourcePaidDisplay === null || $sourceDueDisplay === null) {
                    $paid = (float) DB::table('transaction_payments')->where('transaction_id', $source->id)->sum('amount');
                    $due = max(0, (float) ($source->final_total ?? 0) - $paid);
                    if ($sourcePaidDisplay === null) {
                        $sourcePaidDisplay = $paid;
                    }
                    if ($sourceDueDisplay === null) {
                        $sourceDueDisplay = $due;
                    }
                }
            }
        }

        $createdByName = $loanRow->created_by_name_snapshot ?? null;
        if (empty($createdByName) && !empty($loanRow->created_by) && Schema::hasTable('users')) {
            $userCols = Schema::getColumnListing('users');
            $namePieces = [];
            if (in_array('first_name', $userCols, true) && in_array('last_name', $userCols, true)) {
                $u = DB::table('users')->select('first_name', 'last_name')->where('id', $loanRow->created_by)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->first_name), trim((string) $u->last_name)];
                }
            } elseif (in_array('username', $userCols, true)) {
                $u = DB::table('users')->select('username')->where('id', $loanRow->created_by)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->username)];
                }
            } elseif (in_array('name', $userCols, true)) {
                $u = DB::table('users')->select('name')->where('id', $loanRow->created_by)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->name)];
                }
            }
            $createdByName = trim(implode(' ', array_filter($namePieces)));
        }
        if (empty($createdByName)) {
            $createdByName = !empty($loanRow->created_by) ? ('User #'.$loanRow->created_by) : '-';
        }
        $createdByName = Str::of((string) $createdByName)->squish()->value();

        $collectorDisplayName = $loanRow->collector_name_snapshot ?? null;
        $collectorUserId = $loanRow->collector_id ?? ($loanRow->assigned_to ?? null);
        if (empty($collectorDisplayName) && !empty($collectorUserId) && Schema::hasTable('users')) {
            $userCols = Schema::getColumnListing('users');
            $namePieces = [];
            if (in_array('first_name', $userCols, true) && in_array('last_name', $userCols, true)) {
                $u = DB::table('users')->select('first_name', 'last_name')->where('id', $collectorUserId)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->first_name), trim((string) $u->last_name)];
                }
            } elseif (in_array('username', $userCols, true)) {
                $u = DB::table('users')->select('username')->where('id', $collectorUserId)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->username)];
                }
            } elseif (in_array('name', $userCols, true)) {
                $u = DB::table('users')->select('name')->where('id', $collectorUserId)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->name)];
                }
            }
            $collectorDisplayName = trim(implode(' ', array_filter($namePieces)));
        }
        if (empty($collectorDisplayName)) {
            $collectorDisplayName = !empty($collectorUserId) ? ('User #'.$collectorUserId) : '-';
        }
        $collectorDisplayName = Str::of((string) $collectorDisplayName)->squish()->value();

        $items = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_items')) {
            $items = DB::connection('mysql_loan')->table('loan_items')->where('loan_id', $loan)->get();
        }

        $productItems = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_product_items')) {
            $productItemsQuery = DB::connection('mysql_loan')->table('loan_product_items');
            if (Schema::connection('mysql_loan')->hasColumn('loan_product_items', 'loan_id')) {
                $productItems = $productItemsQuery->where('loan_id', $loan)->get();
            } elseif (Schema::connection('mysql_loan')->hasColumn('loan_product_items', 'loan_item_id') && $items->count() > 0) {
                $itemIds = $items->pluck('id')->filter()->values();
                $productItems = $itemIds->isEmpty()
                    ? collect([])
                    : $productItemsQuery->whereIn('loan_item_id', $itemIds)->get();
            } else {
                $productItems = collect([]);
            }
        }

        $schedules = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            $schedules = DB::connection('mysql_loan')->table('loan_payment_schedules')
                ->where('loan_id', $loan)
                ->orderBy('due_date')
                ->get();
        }

        $payments = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            $payments = DB::connection('mysql_loan')->table('loan_payments')
                ->where('loan_id', $loan)
                ->orderByDesc('paid_date')
                ->get();
        }

        $statusLogs = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_status_logs')) {
            $statusLogs = DB::connection('mysql_loan')->table('loan_status_logs')
                ->where('loan_id', $loan)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('loanmanagement::loans.show', compact('loanRow', 'customerRow', 'customerDisplayName', 'customerPhoneDisplay', 'customerAddressDisplay', 'mainContactIdDisplay', 'locationRow', 'locationDisplayName', 'locationAddressDisplay', 'sourceTypeDisplay', 'sourceTransactionIdDisplay', 'sourceInvoiceDisplay', 'sourceFinalTotalDisplay', 'sourcePaidDisplay', 'sourceDueDisplay', 'createdByName', 'collectorDisplayName', 'items', 'productItems', 'schedules', 'payments', 'statusLogs'));
    }

    public function edit(int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $customerName = trim((string) ($loanRow->customer_name_snapshot ?? ''));
        $customerPhone = trim((string) ($loanRow->customer_phone_snapshot ?? ''));
        $customerAddress = trim((string) ($loanRow->customer_address_snapshot ?? ''));
        $mainContactId = $loanRow->main_contact_id ?? null;

        if ((empty($customerName) || $customerName === '-') && !empty($mainContactId) && Schema::hasTable('contacts')) {
            $contact = DB::table('contacts')
                ->select('id', 'name', 'mobile', 'address_line_1')
                ->where('id', $mainContactId)
                ->first();
            if ($contact) {
                if (empty($customerName)) $customerName = trim((string) ($contact->name ?? ''));
                if (empty($customerPhone)) $customerPhone = trim((string) ($contact->mobile ?? ''));
                if (empty($customerAddress)) $customerAddress = trim((string) ($contact->address_line_1 ?? ''));
            }
        }

        if ((empty($customerName) || $customerName === '-') && !empty($customerPhone) && Schema::hasTable('contacts')) {
            $contact = DB::table('contacts')
                ->select('id', 'name', 'mobile', 'address_line_1', 'alternate_number', 'landline')
                ->where(function ($q) use ($customerPhone) {
                    $q->where('mobile', $customerPhone)
                        ->orWhere('alternate_number', $customerPhone)
                        ->orWhere('landline', $customerPhone);
                })
                ->orderByDesc('id')
                ->first();
            if ($contact) {
                if (empty($customerName)) $customerName = trim((string) ($contact->name ?? ''));
                if (empty($customerAddress)) $customerAddress = trim((string) ($contact->address_line_1 ?? ''));
                if (empty($mainContactId)) $mainContactId = (int) $contact->id;
            }
        }

        $locationName = trim((string) ($loanRow->location_name_snapshot ?? ''));
        $locationAddress = '';
        $locationId = $loanRow->main_location_id ?? ($loanRow->business_location_id ?? null);
        if (!empty($locationId) && Schema::hasTable('business_locations')) {
            $loc = DB::table('business_locations')
                ->select('id', 'name', 'landmark')
                ->where('id', $locationId)
                ->first();
            if ($loc) {
                if (empty($locationName)) $locationName = trim((string) ($loc->name ?? ''));
                $locationAddress = trim((string) ($loc->landmark ?? ''));
            }
        }

        $sourceType = $loanRow->source_type ?? null;
        $sourceTransactionId = $loanRow->source_transaction_id ?? null;
        $sourceInvoice = $loanRow->source_invoice_no ?? null;
        $sourceFinalTotal = $loanRow->sell_final_total_snapshot ?? null;
        $sourcePaid = $loanRow->sell_paid_amount_snapshot ?? null;
        $sourceDue = $loanRow->sell_due_amount_snapshot ?? null;

        if (!empty($sourceTransactionId) && Schema::hasTable('transactions')) {
            $source = DB::table('transactions as t')
                ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
                ->where('t.id', $sourceTransactionId)
                ->selectRaw('t.id, t.type, t.invoice_no, t.final_total, c.name as customer_name, c.mobile as customer_phone, c.address_line_1 as customer_address, bl.name as location_name, bl.landmark as location_landmark')
                ->first();
            if ($source) {
                if (empty($sourceType)) $sourceType = $source->type;
                if (empty($sourceInvoice)) $sourceInvoice = $source->invoice_no;
                if (empty($customerName)) $customerName = trim((string) ($source->customer_name ?? ''));
                if (empty($customerPhone)) $customerPhone = trim((string) ($source->customer_phone ?? ''));
                if (empty($customerAddress)) $customerAddress = trim((string) ($source->customer_address ?? ''));
                if (empty($locationName)) $locationName = trim((string) ($source->location_name ?? ''));
                if (empty($locationAddress)) $locationAddress = trim((string) ($source->location_landmark ?? ''));
                if ($sourceFinalTotal === null) $sourceFinalTotal = $source->final_total;
                if ($sourcePaid === null || $sourceDue === null) {
                    $paid = (float) DB::table('transaction_payments')->where('transaction_id', $source->id)->sum('amount');
                    $due = max(0, (float) ($source->final_total ?? 0) - $paid);
                    if ($sourcePaid === null) $sourcePaid = $paid;
                    if ($sourceDue === null) $sourceDue = $due;
                }
            }
        }

        $customerName = $customerName !== '' ? $customerName : '-';
        $customerPhone = $customerPhone !== '' ? $customerPhone : '-';
        $customerAddress = $customerAddress !== '' ? $customerAddress : '-';
        $locationName = $locationName !== '' ? $locationName : '-';
        $locationAddress = $locationAddress !== '' ? $locationAddress : '-';

        return view('loanmanagement::loans.edit', compact(
            'loanRow',
            'customerName',
            'customerPhone',
            'customerAddress',
            'mainContactId',
            'locationName',
            'locationAddress',
            'locationId',
            'sourceType',
            'sourceTransactionId',
            'sourceInvoice',
            'sourceFinalTotal',
            'sourcePaid',
            'sourceDue'
        ));
    }

    public function update(Request $request, int $loan)
    {
        $data = $request->validate([
            'loan_date' => 'nullable|date',
            'principal_amount' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'duration_months' => 'nullable|integer|min:1|max:360',
            'note' => 'nullable|string|max:1000',
        ]);

        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update(array_merge($data, ['updated_at' => now()]));

        return redirect()->route('loan-management.loans.view', $loan)->with('status', 'Loan updated successfully.');
    }

    public function changeStatus(Request $request, int $loan)
    {
        $payload = $request->validate([
            'status' => 'required|in:draft,pending,approved,active,completed,rejected,cancelled,defaulted',
        ]);

        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update([
            'status' => $payload['status'],
            'updated_at' => now(),
        ]);

        if (Schema::connection('mysql_loan')->hasTable('loan_status_logs')) {
            $cols = Schema::connection('mysql_loan')->getColumnListing('loan_status_logs');
            $row = [
                'loan_id' => $loan,
                'status' => $payload['status'],
                'changed_by' => auth()->id(),
                'note' => 'Status changed from installment list',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            DB::connection('mysql_loan')->table('loan_status_logs')->insert(array_intersect_key($row, array_flip($cols)));
        }

        return response()->json(['success' => true, 'message' => 'Status updated']);
    }

    public function destroy(int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->delete();
        return response()->json(['success' => true, 'message' => 'Loan deleted']);
    }
}
