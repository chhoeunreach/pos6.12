<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\LoanManagement\Entities\LoanChatThread;
use Modules\LoanManagement\Http\Requests\Chat\MarkChatReadRequest;
use Modules\LoanManagement\Http\Requests\Chat\MarkChatTypingRequest;
use Modules\LoanManagement\Http\Requests\Chat\SendChatMessageRequest;
use Modules\LoanManagement\Http\Resources\ChatMessageResource;
use Modules\LoanManagement\Http\Resources\ChatThreadResource;
use Modules\LoanManagement\Services\LoanChatService;

class LoanChatController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected LoanChatService $chatService)
    {
    }

    protected function isAdmin(): bool
    {
        $u = auth()->user();
        return $u && $u->can('loan_management.chat.admin');
    }

    protected function canViewThread(LoanChatThread $thread): bool
    {
        if ($this->isAdmin()) return true;
        $userId = (int) auth()->id();
        return (int) ($thread->staff_id ?? 0) === $userId
            || (int) ($thread->assigned_staff_id ?? 0) === $userId
            || empty($thread->staff_id)
            || empty($thread->assigned_staff_id);
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);

        $webSupportInbox = $request->is('loan-management/chat-api/*') || $request->is('loan-management/chat-api/chats');
        $adminInbox = $this->isAdmin() || $webSupportInbox;
        $rows = $this->chatService->getStaffInbox((int) auth()->id(), $adminInbox, $request->all())->take(200);
        $request->attributes->set('loan_chat_viewer_type', $this->isAdmin() ? 'admin' : 'staff');
        $request->attributes->set('loan_chat_viewer_id', (int) auth()->id());
        $data = ChatThreadResource::collection($rows)->resolve();

        if ($webSupportInbox && (string) $request->input('view', 'all') === 'all') {
            $data = $this->appendCustomerChatTargets($data, $request);
        }

        return $this->ok('Chats loaded', $data);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('loan_management.chat.reply'), 403);
        $data = $request->validate([
            'customer_id' => 'nullable|integer',
            'staff_id' => 'nullable|integer',
            'loan_id' => 'nullable|integer',
            'subject' => 'nullable|string|max:255',
            'type' => 'nullable|in:customer_staff,customer_collector,customer_admin,staff_admin',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'assigned_team' => 'nullable|string|max:80',
            'avatar_url' => 'nullable|string|max:2048',
            'is_pinned' => 'nullable|boolean',
            'is_muted' => 'nullable|boolean',
        ]);
        if (! $this->isAdmin()) {
            $data['staff_id'] = (int) auth()->id();
        }

        if (! empty($data['customer_id'])) {
            $existing = LoanChatThread::query()
                ->where('customer_id', (int) $data['customer_id'])
                ->whereIn('status', ['open', 'pending', 'active'])
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                $request->attributes->set('loan_chat_viewer_type', $this->isAdmin() ? 'admin' : 'staff');
                $request->attributes->set('loan_chat_viewer_id', (int) auth()->id());
                return $this->ok('Thread loaded', (new ChatThreadResource($existing))->resolve());
            }
        }

        $thread = $this->chatService->createThread(array_merge($data, [
            'created_by_type' => $this->isAdmin() ? 'admin' : 'staff',
            'created_by_id' => (int) auth()->id(),
            'participants' => [
                ['type' => $this->isAdmin() ? 'admin' : 'staff', 'id' => (int) auth()->id(), 'name' => trim((string) (auth()->user()->first_name ?? auth()->user()->username ?? ''))],
            ],
        ]));
        return $this->ok('Thread created', (new ChatThreadResource($thread))->resolve());
    }

    public function show(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return $this->fail('Thread not found', 404, (object) []);
        $row = $this->chatService->showThread($row, true);
        $request->attributes->set('loan_chat_viewer_type', $this->isAdmin() ? 'admin' : 'staff');
        $request->attributes->set('loan_chat_viewer_id', (int) auth()->id());
        $this->chatService->markSeen($row, 'staff');
        foreach ($row->messages as $message) {
            if ($message->sender_type === 'customer') {
                $this->chatService->markDelivered($message);
            }
        }
        $data = (new ChatThreadResource($row))->resolve();
        $data['sidebar'] = $this->chatService->getCustomerSidebarData($row);
        return $this->ok('Thread loaded', $data);
    }

    public function sendMessage(SendChatMessageRequest $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.reply'), 403);
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return $this->fail('Thread not found', 404, (object) []);
        $data = $request->validated();
        $senderType = $this->isAdmin() ? 'admin' : 'staff';
        $senderName = trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? '')));
        $metadata = (array) ($data['metadata'] ?? []);
        if (! empty($data['reply_to_message_id'])) {
            $metadata['reply_to_message_id'] = (int) $data['reply_to_message_id'];
        }

        $type = $data['message_type'];
        if (in_array($type, ['image', 'file'], true)) {
            $request->validate([
                'file' => $type === 'image'
                    ? 'required|file|mimes:jpg,jpeg,png,webp|max:51200'
                    : 'required|file|mimes:pdf,doc,docx,xls,xlsx,txt,zip|max:51200',
            ]);
            $msg = $this->chatService->sendFileMessage(
                $row,
                $senderType,
                (int) auth()->id(),
                $request->file('file'),
                $type,
                $data['message'] ?? null,
                $metadata,
                $data['local_uuid'] ?? null
            );
        } elseif ($type === 'audio') {
            $msg = $this->chatService->sendAudioMessage(
                $row,
                $senderType,
                (int) auth()->id(),
                $request->file('file'),
                $data['audio_duration_seconds'] ?? null,
                $data['message'] ?? null,
                $metadata,
                $data['local_uuid'] ?? null
            );
        } elseif ($type === 'location') {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);
            $msg = $this->chatService->sendLocationMessage(
                $row,
                $senderType,
                (int) auth()->id(),
                (float) $data['latitude'],
                (float) $data['longitude'],
                $data['address'] ?? null,
                $metadata,
                $data['local_uuid'] ?? null
            );
        } else {
            $request->validate(['message' => 'required|string']);
            $msg = $this->chatService->sendTextMessage(
                $row,
                $senderType,
                (int) auth()->id(),
                (string) $data['message'],
                $metadata,
                $data['local_uuid'] ?? null
            );
            $msg->sender_name_snapshot = $msg->sender_name_snapshot ?: $senderName;
            $msg->save();
        }

        if (! empty($senderName) && empty($msg->sender_name_snapshot)) {
            $msg->sender_name_snapshot = $senderName;
        }
        if (LoanChatService::hasMessageColumn('reply_to_message_id')) {
            $msg->reply_to_message_id = $data['reply_to_message_id'] ?? $msg->reply_to_message_id;
        }
        if (LoanChatService::hasMessageColumn('reaction')) {
            $msg->reaction = $data['reaction'] ?? $msg->reaction;
        }
        $msg->save();

        $request->attributes->set('loan_chat_viewer_type', $this->isAdmin() ? 'admin' : 'staff');
        $request->attributes->set('loan_chat_viewer_id', (int) auth()->id());
        $this->chatService->markSeen($row, 'staff');
        return $this->ok('Message sent', (new ChatMessageResource($msg))->resolve());
    }

    public function assign(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.assign'), 403);
        $data = $request->validate(['staff_id' => 'required|integer']);
        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->isAdmin() && ! $this->canViewThread($row)) abort(403);
        $this->chatService->assignChat($row, (int) $data['staff_id'], $request->input('assigned_team'));
        return $this->ok('Thread assigned', (new ChatThreadResource($row))->resolve());
    }

    public function transfer(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.transfer') || auth()->user()->can('loan_management.chat.assign'), 403);
        $data = $request->validate([
            'staff_id' => 'required|integer',
            'assigned_team' => 'nullable|string|max:80',
        ]);
        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->isAdmin() && ! $this->canViewThread($row)) abort(403);
        $this->chatService->transferChat($row, (int) $data['staff_id'], $data['assigned_team'] ?? null);
        return $this->ok('Thread transferred', (new ChatThreadResource($row))->resolve());
    }

    public function read(MarkChatReadRequest $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return $this->fail('Thread not found', 404, (object) []);
        $this->chatService->markAsRead($row, $this->isAdmin() ? 'admin' : 'staff', (int) auth()->id());
        return $this->ok('Marked as read', (object) []);
    }

    public function typing(MarkChatTypingRequest $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return $this->fail('Thread not found', 404, (object) []);
        $this->chatService->markTyping($row, 'staff');
        return $this->ok('Typing updated', (object) []);
    }

    public function close(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.close'), 403);
        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->canViewThread($row) && ! $this->isAdmin()) abort(403);
        $this->chatService->closeThread($row, (int) auth()->id(), $request->input('reason'));
        return $this->ok('Thread closed', (object) []);
    }

    public function reopen(int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.close'), 403);
        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->canViewThread($row) && ! $this->isAdmin()) abort(403);
        $this->chatService->reopenThread($row);
        return $this->ok('Thread reopened', (object) []);
    }

    public function pin(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);
        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->canViewThread($row) && ! $this->isAdmin()) abort(403);
        $this->chatService->pinChat($row, $request->boolean('is_pinned', true));
        return $this->ok('Thread pin updated', (new ChatThreadResource($row))->resolve());
    }

    public function mute(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);
        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->canViewThread($row) && ! $this->isAdmin()) abort(403);
        $this->chatService->muteChat($row, $request->boolean('is_muted', true));
        return $this->ok('Thread mute updated', (new ChatThreadResource($row))->resolve());
    }

    public function destroy(int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.delete'), 403);

        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->canViewThread($row) && ! $this->isAdmin()) abort(403);

        try {
            $this->chatService->deleteEmptyThread($row);
        } catch (ValidationException $e) {
            return $this->fail(
                'This chat already has messages and cannot be deleted. You can close it instead.',
                422,
                (object) []
            );
        }

        return $this->ok('Empty chat deleted successfully.', (object) []);
    }

    public function webInbox()
    {
        return view('loanmanagement::chat.inbox', ['initialThreadId' => null]);
    }

    public function webDetail(int $thread)
    {
        return view('loanmanagement::chat.inbox', ['initialThreadId' => $thread]);
    }

    protected function appendCustomerChatTargets(array $threads, Request $request): array
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_customers')) {
            return $threads;
        }

        $existingCustomerIds = [];
        foreach ($threads as $thread) {
            if (! empty($thread['customer_id'])) {
                $existingCustomerIds[(int) $thread['customer_id']] = true;
            }
        }

        $query = DB::connection('mysql_loan')->table('loan_customers');
        if (Schema::connection('mysql_loan')->hasColumn('loan_customers', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                foreach (['name', 'khmer_name', 'phone', 'login_phone', 'customer_code'] as $column) {
                    if (Schema::connection('mysql_loan')->hasColumn('loan_customers', $column)) {
                        $inner->orWhere($column, 'like', '%'.$search.'%');
                    }
                }
            });
        }

        $customers = $query->orderByDesc('id')->limit(300)->get();
        foreach ($customers as $customer) {
            if (isset($existingCustomerIds[(int) $customer->id])) {
                continue;
            }

            $name = (string) ($customer->name ?? $customer->khmer_name ?? 'Customer');
            $phone = (string) ($customer->phone ?? $customer->login_phone ?? '');
            $threads[] = [
                'id' => null,
                'customer_id' => (int) $customer->id,
                'thread_number' => '',
                'display_name' => $name,
                'display_subtitle' => trim($phone.($phone ? ' - ' : '').'New chat'),
                'avatar_url' => '',
                'customer_name' => $name,
                'customer_phone' => $phone,
                'staff_name' => '',
                'assigned_staff_name' => '',
                'assigned_staff_id' => null,
                'assigned_team' => '',
                'type' => 'customer_staff',
                'priority' => 'normal',
                'status' => 'new',
                'is_online' => false,
                'is_pinned' => false,
                'is_muted' => false,
                'is_closed' => false,
                'unread_count' => 0,
                'last_message' => '',
                'last_message_type' => 'text',
                'last_message_at' => null,
                'last_message_time' => null,
                'last_sender_name' => '',
                'typing' => false,
                'is_customer_only' => true,
                'message_count' => 0,
                'can_delete' => false,
            ];
        }

        return $threads;
    }
}
