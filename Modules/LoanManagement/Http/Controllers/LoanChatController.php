<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Entities\LoanChatThread;
use Modules\LoanManagement\Services\LoanChatService;

class LoanChatController extends Controller
{
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
        $rows = $q->orderByDesc('last_message_at')->orderByDesc('id')->paginate(30);
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $rows]);
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
        return response()->json(['success' => true, 'message' => 'Thread created', 'data' => $thread]);
    }

    public function show(int $thread)
    {
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return response()->json(['success' => false, 'message' => 'Thread not found', 'data' => (object) []], 404);
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $row->load(['messages', 'participants'])]);
    }

    public function sendMessage(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.reply'), 403);
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return response()->json(['success' => false, 'message' => 'Thread not found', 'data' => (object) []], 404);
        $data = $request->validate([
            'message' => 'nullable|string',
            'message_type' => 'required|in:text,image,file,audio,location,system',
            'file_id' => 'nullable|integer',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        $senderType = $this->isAdmin() ? 'admin' : 'staff';
        $msg = $this->chatService->sendMessage($row, $senderType, (int) auth()->id(), array_merge($data, [
            'sender_name_snapshot' => trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? ''))),
        ]));
        return response()->json(['success' => true, 'message' => 'Message sent', 'data' => $msg]);
    }

    public function assign(Request $request, int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.assign'), 403);
        $data = $request->validate(['staff_id' => 'required|integer']);
        $row = LoanChatThread::query()->findOrFail($thread);
        $this->chatService->assignStaff($row, (int) $data['staff_id']);
        return response()->json(['success' => true, 'message' => 'Thread assigned', 'data' => $row]);
    }

    public function read(int $thread)
    {
        $row = LoanChatThread::query()->find($thread);
        if (! $row || ! $this->canViewThread($row)) return response()->json(['success' => false, 'message' => 'Thread not found', 'data' => (object) []], 404);
        $this->chatService->markAsRead($row, $this->isAdmin() ? 'admin' : 'staff', (int) auth()->id());
        return response()->json(['success' => true, 'message' => 'Marked as read', 'data' => (object) []]);
    }

    public function close(int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.close'), 403);
        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->canViewThread($row) && ! $this->isAdmin()) abort(403);
        $this->chatService->closeThread($row, (int) auth()->id());
        return response()->json(['success' => true, 'message' => 'Thread closed', 'data' => (object) []]);
    }

    public function reopen(int $thread)
    {
        abort_unless(auth()->user()->can('loan_management.chat.close'), 403);
        $row = LoanChatThread::query()->findOrFail($thread);
        if (! $this->canViewThread($row) && ! $this->isAdmin()) abort(403);
        $this->chatService->reopenThread($row);
        return response()->json(['success' => true, 'message' => 'Thread reopened', 'data' => (object) []]);
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

