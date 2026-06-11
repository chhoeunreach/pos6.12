<?php

namespace Modules\NotificationCenter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\NotificationCenter\Services\NotificationService;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $recipient;

    public string $message;

    public ?string $pdfPath;

    public string $moduleType;

    public array $data;

    public array $options;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(array $recipient, string $message, ?string $pdfPath, string $moduleType, array $data, array $options = [])
    {
        $this->recipient = $recipient;
        $this->message = $message;
        $this->pdfPath = $pdfPath;
        $this->moduleType = $moduleType;
        $this->data = $data;
        $this->options = $options;
    }

    public function handle(NotificationService $service): void
    {
        Log::info('NotificationCenter Job processing', [
            'module_type' => $this->moduleType,
            'chat_id' => $this->recipient['chat_id'] ?? null,
        ]);

        $service->sendToRecipient(
            $this->recipient,
            $this->message,
            $this->pdfPath,
            $this->moduleType,
            $this->data
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('NotificationCenter Job failed permanently', [
            'module_type' => $this->moduleType,
            'chat_id' => $this->recipient['chat_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
