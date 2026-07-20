<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Entities\LoanTelegramChatThread;
use Modules\LoanManagement\Services\TelegramChatService;

/**
 * Staff-facing API for the Telegram customer-chat bridge. Fully separate from LoanChatController
 * (the staff's own internal Live Chat tool) - different tables, different service, no shared code.
 */
class LoanTelegramChatController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected TelegramChatService $chatService)
    {
    }

    protected function isAdmin(): bool
    {
        $u = auth()->user();
        return $u && $u->can('loan_management.chat.admin');
    }

    /**
     * Loan-branch (business location) ids the current staff member is permitted to see Telegram
     * contacts for. Null means unrestricted. Mirrors the permitted_locations() +
     * main_location_id/id matching pattern already used in LoanCreateController::locationDropdownData().
     */
    protected function permittedLoanLocationIds(): ?array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        if ($this->isAdmin() || $user->can('access_all_locations')) {
            return null;
        }

        $permitted = $user->permitted_locations();
        if ($permitted === 'all') {
            return null;
        }

        $mainIds = array_values(array_filter((array) $permitted));
        if (empty($mainIds)) {
            return [];
        }

        if (! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            return null;
        }

        return DB::connection('mysql_loan')->table('loan_business_locations')
            ->where(function ($q) use ($mainIds) {
                $q->whereIn('main_location_id', $mainIds)->orWhereIn('id', $mainIds);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function canAccessCustomerLocation(int $customerId): bool
    {
        $locationIds = $this->permittedLoanLocationIds();
        if ($locationIds === null) {
            return true;
        }

        if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'business_location_id')) {
            return true;
        }

        $customerLocationId = DB::connection('mysql_loan')->table('loan_customers')
            ->where('id', $customerId)
            ->value('business_location_id');

        return $customerLocationId === null || in_array((int) $customerLocationId, $locationIds, true);
    }

    protected function canAccessThread(LoanTelegramChatThread $thread): bool
    {
        return $this->canAccessCustomerLocation((int) $thread->customer_id);
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);

        $rows = $this->chatService->listContactsForStaff(
            trim((string) $request->input('search', '')),
            $this->permittedLoanLocationIds()
        );

        return $this->ok('Chats loaded', $rows->values()->all());
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('loan_management.chat.reply') || auth()->user()->can('loan_management.chat.view'), 403);
        $data = $request->validate(['customer_id' => 'required|integer']);

        abort_unless($this->canAccessCustomerLocation((int) $data['customer_id']), 403, 'This customer is outside your assigned branch.');

        $thread = $this->chatService->findOrCreateThread((int) $data['customer_id']);

        return $this->ok('Thread loaded', $this->chatService->formatThread($thread));
    }

    public function show(int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);
        $row = LoanTelegramChatThread::query()->find($thread);
        if (! $row || ! $this->canAccessThread($row)) {
            return $this->fail('Thread not found', 404, (object) []);
        }

        $this->chatService->markRead($row, 'staff');

        return $this->ok('Thread loaded', $this->chatService->formatThread($row));
    }

    public function sendMessage(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.reply') || auth()->user()->can('loan_management.chat.view'), 403);
        $row = LoanTelegramChatThread::query()->find($thread);
        if (! $row || ! $this->canAccessThread($row)) {
            return $this->fail('Thread not found', 404, (object) []);
        }

        $data = $request->validate(['message_type' => 'required|in:text', 'message' => 'required|string']);
        $senderType = $this->isAdmin() ? 'admin' : 'staff';

        $message = $this->chatService->sendTextMessage($row, $senderType, (int) auth()->id(), (string) $data['message']);

        return $this->ok('Message sent', $this->chatService->formatMessage($message));
    }

    public function read(int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);
        $row = LoanTelegramChatThread::query()->find($thread);
        if (! $row || ! $this->canAccessThread($row)) {
            return $this->fail('Thread not found', 404, (object) []);
        }

        $this->chatService->markRead($row, 'staff');

        return $this->ok('Marked as read', (object) []);
    }
}
