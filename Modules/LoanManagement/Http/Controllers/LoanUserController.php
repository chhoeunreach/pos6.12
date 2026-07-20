<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class LoanUserController extends Controller
{
    protected string $connection = 'mysql_loan';
    protected string $table = 'loan_users';

    public function index(Request $request)
    {
        if (! auth()->user()->can('loan_management.view') && ! auth()->user()->can('loan_management.setting')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanUsersTable();
        $tableExists = Schema::connection($this->connection)->hasTable($this->table);
        $users = collect();

        if ($tableExists) {
            $q = DB::connection($this->connection)->table($this->table)->orderByDesc('id');

            if ($request->filled('name')) {
                $q->where('name', 'like', '%'.$request->input('name').'%');
            }
            if ($request->filled('username')) {
                $q->where('username', 'like', '%'.$request->input('username').'%');
            }
            if ($request->filled('email')) {
                $q->where('email', 'like', '%'.$request->input('email').'%');
            }
            if ($request->filled('phone')) {
                $q->where('phone', 'like', '%'.$request->input('phone').'%');
            }
            if ($request->filled('status')) {
                $q->where('status', $request->input('status'));
            }

            $users = $q->paginate(20)->appends($request->query());
        }

        return view('loanmanagement::users.index', compact('users', 'tableExists'));
    }

    public function create()
    {
        if (! auth()->user()->can('loan_management.create') && ! auth()->user()->can('loan_management.setting')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanUsersTable();

        return view('loanmanagement::users.create');
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('loan_management.create') && ! auth()->user()->can('loan_management.setting')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanUsersTable();

        $data = $request->validate([
            'name' => 'required|string|max:191',
            'username' => 'required|string|max:191|unique:mysql_loan.loan_users,username',
            'email' => 'nullable|email|max:191|unique:mysql_loan.loan_users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:6|confirmed',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $payload = [
            'name' => trim($data['name']),
            'username' => trim($data['username']),
            'email' => trim($data['email'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::connection($this->connection)->table($this->table)->insertGetId($payload);

        return redirect()->route('loan-management.users.index')
            ->with('status', ['success' => 1, 'msg' => 'Loan user created successfully.']);
    }

    public function show(int $user)
    {
        if (! auth()->user()->can('loan_management.view') && ! auth()->user()->can('loan_management.setting')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanUsersTable();

        $userRow = DB::connection($this->connection)->table($this->table)->where('id', $user)->first();
        abort_if(! $userRow, 404);

        $recentLoans = collect();
        if (Schema::connection($this->connection)->hasTable('loans')) {
            $recentLoans = DB::connection($this->connection)->table('loans')
                ->where('created_by', $user)
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        return view('loanmanagement::users.show', compact('userRow', 'recentLoans'));
    }

    public function edit(int $user)
    {
        if (! auth()->user()->can('loan_management.edit') && ! auth()->user()->can('loan_management.setting')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanUsersTable();

        $userRow = DB::connection($this->connection)->table($this->table)->where('id', $user)->first();
        abort_if(! $userRow, 404);

        return view('loanmanagement::users.edit', compact('userRow'));
    }

    public function update(Request $request, int $user)
    {
        if (! auth()->user()->can('loan_management.edit') && ! auth()->user()->can('loan_management.setting')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanUsersTable();

        $userRow = DB::connection($this->connection)->table($this->table)->where('id', $user)->first();
        abort_if(! $userRow, 404);

        $data = $request->validate([
            'name' => 'required|string|max:191',
            'username' => 'required|string|max:191|unique:mysql_loan.loan_users,username,'.$user.',id',
            'email' => 'nullable|email|max:191|unique:mysql_loan.loan_users,email,'.$user.',id',
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6|confirmed',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $payload = [
            'name' => trim($data['name']),
            'username' => trim($data['username']),
            'email' => trim($data['email'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'status' => $data['status'] ?? 'active',
            'updated_at' => now(),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        DB::connection($this->connection)->table($this->table)->where('id', $user)->update($payload);

        return redirect()->route('loan-management.users.show', $user)
            ->with('status', ['success' => 1, 'msg' => 'Loan user updated successfully.']);
    }

    public function destroy(int $user)
    {
        if (! auth()->user()->can('loan_management.delete') && ! auth()->user()->can('loan_management.setting')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanUsersTable();

        $userRow = DB::connection($this->connection)->table($this->table)->where('id', $user)->first();
        abort_if(! $userRow, 404);

        DB::connection($this->connection)->table($this->table)->where('id', $user)->delete();

        return redirect()->route('loan-management.users.index')
            ->with('status', ['success' => 1, 'msg' => 'Loan user deleted successfully.']);
    }

    public function toggleStatus(int $user)
    {
        if (! auth()->user()->can('loan_management.edit') && ! auth()->user()->can('loan_management.setting')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanUsersTable();

        $userRow = DB::connection($this->connection)->table($this->table)->where('id', $user)->first();
        abort_if(! $userRow, 404);

        $newStatus = ($userRow->status ?? 'active') === 'active' ? 'inactive' : 'active';

        DB::connection($this->connection)->table($this->table)
            ->where('id', $user)
            ->update(['status' => $newStatus, 'updated_at' => now()]);

        return redirect()->back()
            ->with('status', ['success' => 1, 'msg' => 'User status changed to '.$newStatus.'.']);
    }

    public function resetPassword(Request $request, int $user)
    {
        if (! auth()->user()->can('loan_management.edit') && ! auth()->user()->can('loan_management.setting')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanUsersTable();

        $userRow = DB::connection($this->connection)->table($this->table)->where('id', $user)->first();
        abort_if(! $userRow, 404);

        $data = $request->validate([
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        DB::connection($this->connection)->table($this->table)
            ->where('id', $user)
            ->update(['password' => Hash::make($data['new_password']), 'updated_at' => now()]);

        return redirect()->back()
            ->with('status', ['success' => 1, 'msg' => 'Password reset successfully.']);
    }

    protected function ensureLoanUsersTable(): void
    {
        if (Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->connection)->create($this->table, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
