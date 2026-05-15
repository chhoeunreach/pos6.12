<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        return (int) ($thread->staff_id ?? 0) === (int) auth()->id();
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('loan_management.chat.view'), 403);

        $q = LoanChatThread::query();
        if (! $this->isAdmin()) {
            $q->where('staff_id', auth()->id());
        }
        if ($request->filled('status')) $q->where('status', $request->input('status'));
        if ($request->filled('priority')) $q->where('priority', $request->input('priority'));
        if ($request->filled('customer_id')) $q->where('customer_id', $request->input('customer_id'));
        if ($request->filled('staff_id') && $this->isAdmin()) $q->where('staff_id', $request->input('staff_id'));
        $q->with(['customer', 'loan']);
        if (LoanChatService::hasThreadColumn('is_pinned')) {
            $q->orderByDesc('is_pinned');
        }
        if (LoanChatService::hasThreadColumn('last_message_at')) {
            $q->orderByDesc('last_message_at');
        }
        $rows = $q->orderByDesc('id')->limit(200)->get();
        $request->attributes->set('loan_chat_viewer_type', $this->isAdmin() ? 'admin' : 'staff');
        $request->attributes->set('loan_chat_viewer_id', (int) auth()->id());
        foreach ($rows as $row) {
            $this->chatService->markSeen($row, 'staff');
        }
        return $this->ok('Chats loaded', ChatThreadResource::collection($rows)->resolve());
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
            'avatar_url' => 'nullable|string|max:2048',
            'is_pinned' => 'nullable|boolean',
            'is_muted' => 'nullable|boolean',
        ]);
        if (! $this->isAdmin()) {
            $data['staff_id'] = (int) auth()->id();
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
        return $this->ok('Thread loaded', (new ChatThreadResource($row))->resolve());
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
            $request->validate([
                'file' => 'required|file|mimes:mp3,m4a,aac,wav,ogg,webm|mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/aac,audio/wav,audio/ogg,audio/webm|max:51200',
            ]);
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
        $this->chatService->assignStaff($row, (int) $data['staff_id']);
        return $this->ok('Thread assigned', (new ChatThreadResource($row))->resolve());
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

    public function close(int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.close'), 403);
        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->canViewThread($row) && ! $this->isAdmin()) abort(403);
        $this->chatService->closeThread($row, (int) auth()->id());
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

    public function webInbox()
    {
        return view('loanmanagement::chat.inbox');
    }

    public function webDetail(int $thread)
    {
        return view('loanmanagement::chat.detail', ['threadId' => $thread]);
    }
}
