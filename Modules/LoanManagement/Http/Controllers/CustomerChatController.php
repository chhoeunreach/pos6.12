<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Entities\LoanChatThread;
use Modules\LoanManagement\Http\Resources\ChatMessageResource;
use Modules\LoanManagement\Http\Resources\ChatThreadResource;
use Modules\LoanManagement\Services\LoanChatService;

class CustomerChatController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected LoanChatService $chatService)
    {
    }

    public function index()
    {
        $customer = auth('customer_loan_api')->user();
        $threads = $this->chatService->listCustomerThreads((int) $customer->id);
        return $this->ok('Threads loaded', ChatThreadResource::collection($threads)->resolve());
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
        return $this->ok('Thread created', (new ChatThreadResource($thread))->resolve());
    }

    public function show(int $thread)
    {
        $customer = auth('customer_loan_api')->user();
        $row = LoanChatThread::query()->where('id', $thread)->where('customer_id', $customer->id)->first();
        if (! $row) {
            return $this->fail('Thread not found', 404, (object) []);
        }
        $row = $this->chatService->showThread($row, true);
        return $this->ok('Thread loaded', (new ChatThreadResource($row))->resolve());
    }

    public function sendMessage(Request $request, int $thread)
    {
        $customer = auth('customer_loan_api')->user();
        $row = LoanChatThread::query()->where('id', $thread)->where('customer_id', $customer->id)->first();
        if (! $row) return $this->fail('Thread not found', 404, (object) []);

        $data = $request->validate([
            'message_type' => 'required|in:text,image,file,audio,location',
            'message' => 'nullable|string',
            'metadata' => 'nullable|array',
            'file' => 'nullable|file|max:51200',
            'audio_duration_seconds' => 'nullable|integer|min:0|max:86400',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
            'local_uuid' => 'nullable|string|max:80',
        ]);

        $senderName = (string) ($customer->name ?? '');
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
                'customer',
                (int) $customer->id,
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
                'customer',
                (int) $customer->id,
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
                'customer',
                (int) $customer->id,
                (float) $data['latitude'],
                (float) $data['longitude'],
                $data['address'] ?? null,
                $metadata,
                $data['local_uuid'] ?? null
            );
        } else {
            $request->validate(['message' => 'required|string']);
            $msg = $this->chatService->sendTextMessage($row, 'customer', (int) $customer->id, (string) $data['message'], $metadata, $data['local_uuid'] ?? null);
        }

        if (! empty($senderName) && empty($msg->sender_name_snapshot)) {
            $msg->sender_name_snapshot = $senderName;
            $msg->save();
        }

        return $this->ok('Message sent', (new ChatMessageResource($msg))->resolve());
    }

    public function read(int $thread)
    {
        $customer = auth('customer_loan_api')->user();
        $row = LoanChatThread::query()->where('id', $thread)->where('customer_id', $customer->id)->first();
        if (! $row) return $this->fail('Thread not found', 404, (object) []);
        $this->chatService->markAsRead($row, 'customer', (int) $customer->id);
        return $this->ok('Marked as read', (object) []);
    }

    public function close(int $thread)
    {
        $customer = auth('customer_loan_api')->user();
        $row = LoanChatThread::query()->where('id', $thread)->where('customer_id', $customer->id)->first();
        if (! $row) return $this->fail('Thread not found', 404, (object) []);
        $this->chatService->closeThread($row, (int) $customer->id);
        return $this->ok('Thread closed', (object) []);
    }
}
