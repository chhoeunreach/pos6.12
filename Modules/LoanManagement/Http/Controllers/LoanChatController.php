<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Entities\LoanChatThread;
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
        return $u && ($u->can('loan_management.chat.admin') || $u->can('loan_management.chat.assign'));
    }

    protected function canViewThread(LoanChatThread $thread): bool
    {
        if ($this->isAdmin()) return true;
        return (int) ($thread->staff_id ?? 0) === (int) auth()->id();
    }

    public function index(Request $request)
    {
        $q = LoanChatThread::query();
        if (! $this->isAdmin()) {
            $q->where('staff_id', auth()->id());
        }
        if ($request->filled('status')) $q->where('status', $request->input('status'));
        if ($request->filled('priority')) $q->where('priority', $request->input('priority'));
        if ($request->filled('customer_id')) $q->where('customer_id', $request->input('customer_id'));
        if ($request->filled('staff_id') && $this->isAdmin()) $q->where('staff_id', $request->input('staff_id'));
        $rows = $q->orderByDesc('last_message_at')->orderByDesc('id')->limit(200)->get();
        return $this->ok('Threads loaded', ChatThreadResource::collection($rows)->resolve());
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
        ]);
        $thread = $this->chatService->createThread(array_merge($data, [
            'created_by_type' => $this->isAdmin() ? 'admin' : 'staff',
            'created_by_id' => (int) auth()->id(),
            'participants' => [
                ['type' => $this->isAdmin() ? 'admin' : 'staff', 'id' => (int) auth()->id(), 'name' => trim((string) (auth()->user()->first_name ?? auth()->user()->username ?? ''))],
            ],
        ]));
        return $this->ok('Thread created', (new ChatThreadResource($thread))->resolve());
    }

    public function show(int $thread)
    {
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return $this->fail('Thread not found', 404, (object) []);
        $row = $this->chatService->showThread($row, true);
        return $this->ok('Thread loaded', (new ChatThreadResource($row))->resolve());
    }

    public function sendMessage(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.reply'), 403);
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return $this->fail('Thread not found', 404, (object) []);
        $data = $request->validate([
            'message_type' => 'required|in:text,image,file,audio,location',
            'message' => 'nullable|string',
            'metadata' => 'nullable|array',
            'file' => 'nullable|file|max:51200',
            'audio_duration_seconds' => 'nullable|integer|min:0|max:86400',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
        ]);
        $senderType = $this->isAdmin() ? 'admin' : 'staff';
        $senderName = trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? '')));
        $metadata = (array) ($data['metadata'] ?? []);

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
                $metadata
            );
        } elseif ($type === 'audio') {
            $request->validate([
                'file' => 'required|file|mimes:mp3,m4a,aac,wav,ogg,webm|max:51200',
            ]);
            $msg = $this->chatService->sendAudioMessage(
                $row,
                $senderType,
                (int) auth()->id(),
                $request->file('file'),
                $data['audio_duration_seconds'] ?? null,
                $data['message'] ?? null,
                $metadata
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
                $metadata
            );
        } else {
            $request->validate(['message' => 'required|string']);
            $msg = $this->chatService->sendMessage($row, $senderType, (int) auth()->id(), [
                'sender_name_snapshot' => $senderName,
                'message_type' => 'text',
                'message' => (string) $data['message'],
                'metadata' => $metadata,
            ]);
        }

        if (! empty($senderName) && empty($msg->sender_name_snapshot)) {
            $msg->sender_name_snapshot = $senderName;
            $msg->save();
        }

        return $this->ok('Message sent', (new ChatMessageResource($msg))->resolve());
    }

    public function assign(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.assign'), 403);
        $data = $request->validate(['staff_id' => 'required|integer']);
        $row = LoanChatThread::query()->findOrFail($thread);
        $this->chatService->assignStaff($row, (int) $data['staff_id']);
        return $this->ok('Thread assigned', (new ChatThreadResource($row))->resolve());
    }

    public function read(int $thread)
    {
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return $this->fail('Thread not found', 404, (object) []);
        $this->chatService->markAsRead($row, $this->isAdmin() ? 'admin' : 'staff', (int) auth()->id());
        return $this->ok('Marked as read', (object) []);
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
