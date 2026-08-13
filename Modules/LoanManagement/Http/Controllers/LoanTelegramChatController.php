<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\Services\WkhtmltopdfPdfService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\LoanManagement\Entities\LoanTelegramChatMessage;
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

    protected function canUseTelegramChat(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->can('loan_management.chat.view')
            || $user->can('loan_management.chat.reply')
            || $user->can('loan_management.chat.delete')
            || $user->can('loan_management.chat.admin')
        );
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

        $bankBranchLocationIds = $this->userBankBranchLoanLocationIds($user);
        if (! empty($bankBranchLocationIds)) {
            return $bankBranchLocationIds;
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

    protected function userBankBranchLoanLocationIds($user): array
    {
        $branch = $this->userBankBranchValue($user);
        if ($branch === '') {
            return [];
        }

        if (! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            return is_numeric($branch) ? [(int) $branch] : [];
        }

        $query = DB::connection('mysql_loan')->table('loan_business_locations')
            ->where(function ($q) use ($branch) {
                if (is_numeric($branch)) {
                    $q->where('id', (int) $branch);
                    if (Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_location_id')) {
                        $q->orWhere('main_location_id', (int) $branch);
                    }
                } else {
                    $q->where('name', $branch);
                    if (Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'location_code')) {
                        $q->orWhere('location_code', $branch);
                    }
                }
            });

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all() ?: (is_numeric($branch) ? [(int) $branch] : []);
    }

    protected function userBankBranchValue($user): string
    {
        $details = $user->bank_details ?? null;
        if (is_string($details)) {
            $details = json_decode($details, true) ?: [];
        }
        if (! is_array($details)) {
            return '';
        }

        return trim((string) ($details['branch_id'] ?? $details['branch'] ?? ''));
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
            $this->permittedLoanLocationIds(),
            [
                'location_id' => $request->input('location_id'),
                'telegram_status' => $request->input('telegram_status'),
            ]
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

        $data = $request->validate([
            'message_type' => 'required|in:text,image,file,audio,location',
            'message' => 'nullable|string|max:5000',
            'file' => 'nullable|file|max:20480',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'address' => 'nullable|string|max:1000',
            'duration_seconds' => 'nullable|integer|min:0|max:3600',
        ]);
        $senderType = $this->isAdmin() ? 'admin' : 'staff';
        abort_unless($data['message_type'] !== 'text' || trim((string) ($data['message'] ?? '')) !== '', 422, 'Message is required.');
        abort_unless(! in_array($data['message_type'], ['image', 'file', 'audio'], true) || $request->hasFile('file'), 422, 'Please attach a file.');
        abort_unless($data['message_type'] !== 'location' || (isset($data['latitude'], $data['longitude'])), 422, 'Location coordinates are required.');

        $message = match ($data['message_type']) {
            'image' => $this->chatService->sendImageMessage($row, $senderType, (int) auth()->id(), $request->file('file'), (string) ($data['message'] ?? '')),
            'file' => $this->chatService->sendFileMessage($row, $senderType, (int) auth()->id(), $request->file('file'), 'file', (string) ($data['message'] ?? '')),
            'audio' => $this->chatService->sendAudioMessage($row, $senderType, (int) auth()->id(), $request->file('file'), (int) ($data['duration_seconds'] ?? 0), (string) ($data['message'] ?? '')),
            'location' => $this->chatService->sendLocationMessage($row, $senderType, (int) auth()->id(), (float) $data['latitude'], (float) $data['longitude'], (string) ($data['address'] ?? '')),
            default => $this->chatService->sendTextMessage($row, $senderType, (int) auth()->id(), (string) ($data['message'] ?? '')),
        };

        return $this->ok('Message sent', $this->chatService->formatMessage($message));
    }

    public function sendInvoiceImage(Request $request, int $thread, WkhtmltopdfPdfService $renderService)
    {
        abort_unless(auth()->user()->can('loan_management.chat.reply') || auth()->user()->can('loan_management.chat.view'), 403);

        $row = LoanTelegramChatThread::query()->find($thread);
        if (! $row || ! $this->canAccessThread($row)) {
            return $this->fail('Thread not found', 404, (object) []);
        }

        $data = $request->validate([
            'loan_id' => 'required|integer',
            'message' => 'nullable|string|max:1000',
        ]);

        $loan = DB::connection('mysql_loan')->table('loans')->where('id', (int) $data['loan_id'])->first();
        if (! $loan) {
            return $this->fail('Loan not found', 404, (object) []);
        }
        if (! empty($loan->customer_id) && (int) $loan->customer_id !== (int) $row->customer_id) {
            return $this->fail('This loan does not belong to the selected customer.', 422, (object) []);
        }

        $html = app(LoanInstallmentListController::class)->print((int) $data['loan_id'])->render();
        $html = preg_replace('/<div class="no-print".*?<\/div>/is', '', $html, 1) ?: $html;
        $imageCss = '<style>body{background:#fff!important}.page{margin:0 auto!important}.no-print{display:none!important}</style>';
        $html = str_ireplace('</head>', $imageCss.'</head>', $html);

        $tmpDir = storage_path('app/temp/telegram-invoices');
        if (! File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        $filename = 'loan-invoice-'.Str::slug((string) ($loan->loan_number ?? $loan->id), '-').'-'.time().'.png';
        $path = $tmpDir.DIRECTORY_SEPARATOR.$filename;

        try {
            $renderService->saveHtmlToImage($html, $path);

            $uploaded = new UploadedFile($path, $filename, 'image/png', null, true);
            $senderType = $this->isAdmin() ? 'admin' : 'staff';
            $caption = trim((string) ($data['message'] ?? ''));
            if ($caption === '') {
                $caption = 'Invoice: '.($loan->loan_number ?? $loan->id);
            }

            $message = $this->chatService->sendImageMessage($row, $senderType, (int) auth()->id(), $uploaded, $caption);

            return $this->ok('Invoice image sent', $this->chatService->formatMessage($message));
        } catch (\Throwable $e) {
            return $this->fail('Unable to create invoice image: '.$e->getMessage(), 500, (object) []);
        } finally {
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    public function updateMessage(Request $request, int $thread, int $message)
    {
        abort_unless($this->canUseTelegramChat(), 403);

        $row = LoanTelegramChatThread::query()->find($thread);
        if (! $row || ! $this->canAccessThread($row)) {
            return $this->fail('Thread not found', 404, (object) []);
        }

        $messageRow = LoanTelegramChatMessage::query()
            ->where('thread_id', $row->id)
            ->where('id', $message)
            ->first();

        if (! $messageRow) {
            return $this->fail('Message not found', 404, (object) []);
        }

        abort_unless($this->canUpdateMessage($messageRow), 403, 'You do not have permission to update this message.');
        abort_unless($messageRow->message_type === 'text', 422, 'Only text messages can be updated.');

        $data = $request->validate(['message' => 'required|string|max:5000']);

        try {
            $updated = $this->chatService->updateTextMessage($messageRow, (string) $data['message']);
        } catch (\Throwable $e) {
            return $this->fail('Unable to update message in Telegram: '.$e->getMessage(), 422, (object) []);
        }

        return $this->ok('Message updated', $this->chatService->formatMessage($updated));
    }

    public function destroyMessage(int $thread, int $message)
    {
        abort_unless($this->canUseTelegramChat(), 403);

        $row = LoanTelegramChatThread::query()->find($thread);
        if (! $row || ! $this->canAccessThread($row)) {
            return $this->fail('Thread not found', 404, (object) []);
        }

        $messageRow = LoanTelegramChatMessage::query()
            ->where('thread_id', $row->id)
            ->where('id', $message)
            ->first();

        if (! $messageRow) {
            return $this->fail('Message not found', 404, (object) []);
        }

        try {
            $this->chatService->deleteMessage($messageRow);
        } catch (\Throwable $e) {
            return $this->fail('Unable to delete message in Telegram: '.$e->getMessage(), 422, (object) []);
        }

        return $this->ok('Message deleted', (object) []);
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

    protected function canUpdateMessage(LoanTelegramChatMessage $message): bool
    {
        return $this->canUseTelegramChat()
            && in_array($message->sender_type, ['staff', 'admin'], true);
    }
}
