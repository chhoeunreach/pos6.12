<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\LoanManagement\Entities\LoanFile;
use Modules\LoanManagement\Entities\LoanTelegramChatMessage;
use Modules\LoanManagement\Entities\LoanTelegramChatThread;
use Modules\LoanManagement\Services\TelegramChatService;

class CustomerTelegramChatController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected TelegramChatService $chatService)
    {
    }

    protected function customer()
    {
        return auth('customer_loan_api')->user();
    }

    protected function threadForCustomer(int $threadId): ?LoanTelegramChatThread
    {
        $customer = $this->customer();

        return LoanTelegramChatThread::query()
            ->where('id', $threadId)
            ->where('customer_id', $customer->id)
            ->first();
    }

    public function index()
    {
        $customer = $this->customer();
        $threads = LoanTelegramChatThread::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'open')
            ->with(['customer', 'messages' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        return $this->ok('Telegram chats loaded', $threads
            ->map(fn ($thread) => $this->chatService->formatThread($thread, 'customer', (int) $customer->id))
            ->values()
            ->all());
    }

    public function store()
    {
        $customer = $this->customer();
        $thread = $this->chatService->findOrCreateThread((int) $customer->id);

        return $this->ok(
            'Telegram chat loaded',
            $this->chatService->formatThread($thread, 'customer', (int) $customer->id)
        );
    }

    public function show(int $thread)
    {
        $customer = $this->customer();
        $row = $this->threadForCustomer($thread);
        if (! $row) {
            return $this->fail('Telegram chat not found', 404, (object) []);
        }

        $this->chatService->markRead($row, 'customer');

        return $this->ok(
            'Telegram chat loaded',
            $this->chatService->formatThread($row->fresh(), 'customer', (int) $customer->id)
        );
    }

    public function sendMessage(Request $request, int $thread)
    {
        $customer = $this->customer();
        $row = $this->threadForCustomer($thread);
        if (! $row) {
            return $this->fail('Telegram chat not found', 404, (object) []);
        }

        $data = $request->validate([
            'message_type' => 'required|in:text,image,file,audio,location',
            'message' => 'nullable|string|max:5000',
            'file' => 'nullable|file|max:51200',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:1000',
            'duration_seconds' => 'nullable|integer|min:0|max:3600',
        ]);

        $type = $data['message_type'];
        if ($type === 'text' && trim((string) ($data['message'] ?? '')) === '') {
            return $this->fail('Message is required.', 422, (object) []);
        }
        if (in_array($type, ['image', 'file', 'audio'], true) && ! $request->hasFile('file')) {
            return $this->fail('Please attach a file.', 422, (object) []);
        }
        if ($type === 'location' && (! isset($data['latitude']) || ! isset($data['longitude']))) {
            return $this->fail('Location coordinates are required.', 422, (object) []);
        }

        $senderId = (int) $customer->id;
        $caption = trim((string) ($data['message'] ?? '')) ?: null;
        $message = match ($type) {
            'image' => $this->chatService->sendImageMessage($row, 'customer', $senderId, $request->file('file'), $caption),
            'file' => $this->chatService->sendFileMessage($row, 'customer', $senderId, $request->file('file'), 'file', $caption),
            'audio' => $this->chatService->sendAudioMessage($row, 'customer', $senderId, $request->file('file'), (int) ($data['duration_seconds'] ?? 0), $caption),
            'location' => $this->chatService->sendLocationMessage($row, 'customer', $senderId, (float) $data['latitude'], (float) $data['longitude'], $data['address'] ?? null),
            default => $this->chatService->sendTextMessage($row, 'customer', $senderId, trim((string) $data['message'])),
        };

        return $this->ok(
            'Message sent',
            $this->chatService->formatMessage($message, 'customer', $senderId)
        );
    }

    public function read(int $thread)
    {
        $row = $this->threadForCustomer($thread);
        if (! $row) {
            return $this->fail('Telegram chat not found', 404, (object) []);
        }

        $this->chatService->markRead($row, 'customer');

        return $this->ok('Marked as read', (object) []);
    }

    public function typing(int $thread)
    {
        if (! $this->threadForCustomer($thread)) {
            return $this->fail('Telegram chat not found', 404, (object) []);
        }

        return $this->ok('Typing updated', (object) []);
    }

    public function file(Request $request, int $file)
    {
        $loanFile = LoanFile::query()->find($file);
        abort_if(! $loanFile || empty($loanFile->path), 404);

        $customer = $this->customer();
        $isProfilePhoto = (int) ($customer->customer_photo_file_id ?? 0) === (int) $loanFile->id;
        $ownsFile = $isProfilePhoto || LoanTelegramChatMessage::query()
            ->where('file_id', $loanFile->id)
            ->whereHas('thread', fn ($query) => $query->where('customer_id', $customer->id))
            ->exists();
        abort_unless($ownsFile, 404);

        $disk = $loanFile->disk ?: 'public';
        abort_if(! Storage::disk($disk)->exists($loanFile->path), 404);
        $path = Storage::disk($disk)->path($loanFile->path);
        $name = basename((string) ($loanFile->original_name ?: $loanFile->path));

        if ($request->boolean('download')) {
            return response()->download($path, $name, [
                'Content-Type' => $loanFile->mime_type ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        return response()->file($path, [
            'Content-Type' => $loanFile->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function publicFile(Request $request, int $file)
    {
        $loanFile = LoanFile::query()->find($file);
        abort_if(! $loanFile || empty($loanFile->path), 404);

        $disk = $loanFile->disk ?: 'public';
        abort_if(! Storage::disk($disk)->exists($loanFile->path), 404);

        $path = Storage::disk($disk)->path($loanFile->path);
        $name = basename((string) ($loanFile->original_name ?: $loanFile->path));

        if ($request->boolean('download')) {
            return response()->download($path, $name, [
                'Content-Type' => $loanFile->mime_type ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }

        return response()->file($path, [
            'Content-Type' => $loanFile->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
