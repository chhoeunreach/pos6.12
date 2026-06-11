<?php

namespace Modules\NotificationCenter\Services;

use Illuminate\Support\Facades\Log;
use Modules\NotificationCenter\Entities\NotificationGroup;
use Modules\NotificationCenter\Entities\NotificationLog;
use Modules\NotificationCenter\Entities\NotificationTemplate;
use Modules\NotificationCenter\Jobs\SendNotificationJob;

class NotificationService
{
    public TelegramService $telegram;

    public PdfService $pdf;

    public function __construct()
    {
        $this->telegram = app(TelegramService::class);
        $this->pdf = app(PdfService::class);
    }

    /**
     * Send a notification for a given module type.
     *
     * @param  string  $moduleType  e.g. 'stock_transfer', 'loan_payment', 'loan_installment'
     * @param  array  $data  Payload, e.g. ['transfer' => $transfer] or ['transfer_id' => 123]
     * @param  array  $options  Optional overrides
     * @return array
     */
    public function send(string $moduleType, array $data, array $options = []): array
    {
        $data = $this->resolveEntities($moduleType, $data);

        $recipients = $this->resolveRecipients($moduleType, $data, $options);
        if (empty($recipients)) {
            return ['success' => false, 'msg' => 'No recipients resolved'];
        }

        $message = $this->buildMessage($moduleType, $data);
        $pdfPath = null;

        if (empty($options['skip_pdf'])) {
            $pdfPath = $this->generatePdf($moduleType, $data, $options);
        }

        if (config('notificationcenter.queue_enabled', true) && empty($options['sync'])) {
            $results = [];
            foreach ($recipients as $recipient) {
                SendNotificationJob::dispatch(
                    $recipient,
                    $message,
                    $pdfPath,
                    $moduleType,
                    $data,
                    $options
                );
                $results[] = [
                    'group_id' => $recipient['group_id'] ?? null,
                    'chat_id' => $recipient['chat_id'],
                    'queued' => true,
                ];
            }

            return [
                'success' => true,
                'queued' => true,
                'recipients' => $results,
            ];
        }

        return $this->sendSync($recipients, $message, $pdfPath, $moduleType, $data);
    }

    /**
     * Resolve entity IDs into full data before template interpolation.
     */
    protected function resolveEntities(string $moduleType, array $data): array
    {
        if ($moduleType === 'stock_transfer' && ! empty($data['transfer_id']) && empty($data['transfer'])) {
            $transfer = \App\Transaction::with(['location', 'transferParent.location', 'sell_lines', 'contact'])
                ->find($data['transfer_id']);
            if ($transfer) {
                $data['transfer'] = $transfer;
                $data['ref_no'] = $transfer->ref_no;
                $data['from_location'] = optional($transfer->location)->name ?? '';
                $data['from_location_id'] = $transfer->location_id;
                $data['to_location'] = optional($transfer->transferParent->location ?? null)->name ?? '';
                $data['to_location_id'] = optional($transfer->transferParent->location ?? null)->id;
                $data['date'] = optional($transfer->transaction_date)->format('Y-m-d H:i') ?? '';
                $data['status'] = $transfer->status === 'final' ? 'completed' : ($transfer->status ?? '');
                $data['total_qty'] = $transfer->sell_lines->sum('quantity') ?? 0;
                $data['user'] = trim((string) optional($transfer->createdByUser)->first_name . ' ' . optional($transfer->createdByUser)->last_name);
            }
        }

        return $data;
    }

    /**
     * Send synchronously (used by queue job or when queue is disabled).
     */
    public function sendSync(array $recipients, string $message, ?string $pdfPath, string $moduleType, array $data): array
    {
        $results = [];
        foreach ($recipients as $recipient) {
            $result = $this->sendToRecipient($recipient, $message, $pdfPath, $moduleType, $data);
            $results[] = $result;
        }

        return [
            'success' => true,
            'queued' => false,
            'results' => $results,
        ];
    }

    /**
     * Send to one recipient group.
     */
    public function sendToRecipient(array $recipient, string $message, ?string $pdfPath, string $moduleType, array $data): array
    {
        $chatId = $recipient['chat_id'];
        $sendText = $recipient['send_text'] ?? true;
        $sendPdf = $recipient['send_pdf'] ?? true;
        $groupId = $recipient['group_id'] ?? null;

        $refType = $data['reference_type'] ?? $moduleType;
        $refId = $data['reference_id'] ?? null;
        $refNo = $data['reference_no'] ?? null;

        $log = $this->logResult([
            'module_type' => $moduleType,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'reference_no' => $refNo,
            'group_id' => $groupId,
            'message' => $message,
            'status' => 'pending',
            'response' => null,
            'attempts' => 0,
        ]);

        try {
            $usePdf = $sendPdf && $pdfPath !== null && file_exists($pdfPath);

            if ($usePdf) {
                $caption = $sendText ? $message : null;
                $result = $this->telegram->sendDocument($chatId, $pdfPath, $caption);
            } elseif ($sendText) {
                $result = $this->telegram->sendText($chatId, $message);
            } else {
                $result = ['success' => false, 'status' => 'skipped', 'error' => 'No text or PDF configured for group'];
            }

            $log->update([
                'status' => $result['success'] ? 'sent' : 'failed',
                'response' => json_encode($result),
                'sent_at' => $result['success'] ? now() : null,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('NotificationCenter sendToRecipient failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'response' => $e->getMessage(),
            ]);

            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Resolve recipient groups for a module type and optional location.
     */
    public function resolveRecipients(string $moduleType, array $data, array $options = []): array
    {
        $groups = collect();

        if ($moduleType === 'stock_transfer' && (! empty($data['from_location']) || ! empty($data['to_location']))) {
            $fromQuery = NotificationGroup::active()->forModule($moduleType)->forDirection('from');

            if (! empty($data['from_location_id'])) {
                $fromQuery->forLocation($data['from_location_id']);
            } elseif (! empty($data['from_location'])) {
                $fromQuery->forLocationName($data['from_location']);
            }

            $toQuery = NotificationGroup::active()->forModule($moduleType)->forDirection('to');

            if (! empty($data['to_location_id'])) {
                $toQuery->forLocation($data['to_location_id']);
            } elseif (! empty($data['to_location'])) {
                $toQuery->forLocationName($data['to_location']);
            }

            $groups = $fromQuery->get()->concat($toQuery->get());
        } else {
            $query = NotificationGroup::active()->forModule($moduleType);

            if (! empty($options['location_id'])) {
                $query->forLocation($options['location_id']);
            }

            if (! empty($options['business_id'])) {
                $query->forBusiness($options['business_id']);
            }

            $groups = $query->get();
        }

        if ($groups->isEmpty()) {
            Log::warning('NotificationCenter: no active groups for module', [
                'module_type' => $moduleType,
                'from_location' => $data['from_location'] ?? null,
                'to_location' => $data['to_location'] ?? null,
            ]);
        }

        return $groups->map(function ($group) {
            return [
                'group_id' => $group->id,
                'chat_id' => $group->chat_id,
                'send_text' => $group->send_text,
                'send_pdf' => $group->send_pdf,
            ];
        })->toArray();
    }

    /**
     * Build message from template or fallback.
     */
    public function buildMessage(string $moduleType, array $data): string
    {
        $template = NotificationTemplate::active()->forModule($moduleType)->first();

        if ($template) {
            return $this->interpolate($template->message_template, $data);
        }

        return $this->fallbackMessage($moduleType, $data);
    }

    /**
     * Generate PDF if a template view exists.
     */
    public function generatePdf(string $moduleType, array $data, array $options = []): ?string
    {
        $template = NotificationTemplate::active()->forModule($moduleType)->first();
        if (! $template || empty($template->pdf_template_view)) {
            return null;
        }

        return $this->pdf->generate($template->pdf_template_view, $data, $options['pdf_prefix'] ?? null);
    }

    /**
     * Convenience: send a text notification directly to a chat ID without a group.
     * Used by LoanManagement module for backward compatibility.
     */
    public function sendToChat(string $moduleType, string $chatId, string $message, array $data = []): array
    {
        $recipient = [
            'group_id' => null,
            'chat_id' => $chatId,
            'send_text' => true,
            'send_pdf' => false,
        ];

        return $this->sendToRecipient($recipient, $message, null, $moduleType, $data);
    }

    /**
     * Log a notification result.
     */
    public function logResult(array $attributes): NotificationLog
    {
        return NotificationLog::create($attributes);
    }

    /**
     * Replace {{key}} placeholders in template with data values.
     */
    protected function interpolate(string $template, array $data): string
    {
        $message = $template;

        foreach ($data as $key => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $message = str_replace('{{'.$key.'}}', (string) $value, $message);
            }
        }

        return $message;
    }

    /**
     * Fallback plain message when no template is configured.
     */
    protected function fallbackMessage(string $moduleType, array $data): string
    {
        $map = [
            'stock_transfer' => 'Stock Transfer notification',
            'loan_payment' => 'Loan Payment notification',
            'loan_installment' => 'Loan Installment notification',
        ];

        return $map[$moduleType] ?? "Notification [$moduleType]";
    }
}
