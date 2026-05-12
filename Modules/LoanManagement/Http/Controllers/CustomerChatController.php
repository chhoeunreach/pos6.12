<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Entities\LoanChatThread;
use Modules\LoanManagement\Services\LoanChatService;

class CustomerChatController extends Controller
{
    public function __construct(protected LoanChatService $chatService)
    {
    }

    public function index()
    {
        $customer = auth('customer_loan_api')->user();
        $threads = $this->chatService->getCustomerThreads((int) $customer->id);
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $threads]);
    }

    public function store(Request $request)
    {
        $customer = auth('customer_loan_api')->user();
        $data = $request->validate([
            'loan_id' => 'nullable|integer',
            'subject' => 'nullable|string|max:255',
            'type' => 'nullable|in:customer_staff,customer_collector,customer_admin',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'staff_id' => 'nullable|integer',
        ]);
        $thread = $this->chatService->createThread([
            'customer_id' => $customer->id,
            'staff_id' => $data['staff_id'] ?? null,
            'loan_id' => $data['loan_id'] ?? null,
            'subject' => $data['subject'] ?? null,
            'type' => $data['type'] ?? 'customer_staff',
            'priority' => $data['priority'] ?? 'normal',
            'created_by_type' => 'customer',
            'created_by_id' => $customer->id,
            'participants' => [
                ['type' => 'customer', 'id' => $customer->id, 'name' => $customer->name],
            ],
        ]);
        return response()->json(['success' => true, 'message' => 'Thread created', 'data' => $thread]);
    }

    public function show(int $thread)
    {
        $customer = auth('customer_loan_api')->user();
        $row = LoanChatThread::query()->where('id', $thread)->where('customer_id', $customer->id)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Thread not found', 'data' => (object) []], 404);
        }
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $row->load(['messages', 'participants'])]);
    }

    public function sendMessage(Request $request, int $thread)
    {
        $customer = auth('customer_loan_api')->user();
        $row = LoanChatThread::query()->where('id', $thread)->where('customer_id', $customer->id)->first();
        if (! $row) return response()->json(['success' => false, 'message' => 'Thread not found', 'data' => (object) []], 404);

        $data = $request->validate([
            'message' => 'nullable|string',
            'message_type' => 'required|in:text,image,file,audio,location,system',
            'file_id' => 'nullable|integer',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        $msg = $this->chatService->sendMessage($row, 'customer', (int) $customer->id, array_merge($data, [
            'sender_name_snapshot' => $customer->name,
        ]));
        return response()->json(['success' => true, 'message' => 'Message sent', 'data' => $msg]);
    }

    public function read(int $thread)
    {
        $customer = auth('customer_loan_api')->user();
        $row = LoanChatThread::query()->where('id', $thread)->where('customer_id', $customer->id)->first();
        if (! $row) return response()->json(['success' => false, 'message' => 'Thread not found', 'data' => (object) []], 404);
        $this->chatService->markAsRead($row, 'customer', (int) $customer->id);
        return response()->json(['success' => true, 'message' => 'Marked as read', 'data' => (object) []]);
    }

    public function close(int $thread)
    {
        $customer = auth('customer_loan_api')->user();
        $row = LoanChatThread::query()->where('id', $thread)->where('customer_id', $customer->id)->first();
        if (! $row) return response()->json(['success' => false, 'message' => 'Thread not found', 'data' => (object) []], 404);
        $this->chatService->closeThread($row, (int) $customer->id);
        return response()->json(['success' => true, 'message' => 'Thread closed', 'data' => (object) []]);
    }
}

