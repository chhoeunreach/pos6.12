<?php

namespace App\Jobs;

use App\Notifications\CustomerNotification;
use App\Notifications\SupplierNotification;
use App\Transaction;
use App\Utils\NotificationUtil;
use App\Utils\TransactionUtil;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Notification;

class SendNotificationWithPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    protected array $data;
    protected array $emails_array;
    protected string $template_for;
    protected ?int $transaction_id;
    protected int $business_id;
    protected array $notification_type;
    protected ?string $whatsapp_link;

    public function __construct(
        array $data,
        array $emails_array,
        string $template_for,
        ?int $transaction_id,
        int $business_id,
        array $notification_type
    ) {
        $this->data = $data;
        $this->emails_array = $emails_array;
        $this->template_for = $template_for;
        $this->transaction_id = $transaction_id;
        $this->business_id = $business_id;
        $this->notification_type = $notification_type;
        $this->whatsapp_link = null;
    }

    public function handle(TransactionUtil $transactionUtil, NotificationUtil $notificationUtil): void
    {
        $customer_notifications = \App\NotificationTemplate::customerNotifications();
        $supplier_notifications = \App\NotificationTemplate::supplierNotifications();

        $transaction = ! empty($this->transaction_id) ? Transaction::find($this->transaction_id) : null;

        if (array_key_exists($this->template_for, $customer_notifications)) {
            if (in_array('email', $this->notification_type)) {
                if (! empty($this->data['attach_pdf'])) {
                    $this->data['pdf_name'] = 'INVOICE-' . ($transaction->invoice_no ?? 'N/A') . '.pdf';
                    $this->data['pdf'] = $transactionUtil->getEmailAttachmentForGivenTransaction($this->business_id, $this->transaction_id, true);
                }

                Notification::route('mail', $this->emails_array)
                    ->notify(new CustomerNotification($this->data));

                if (! empty($transaction)) {
                    $notificationUtil->activityLog($transaction, 'email_notification_sent', null, [], false);
                }
            }
            if (in_array('sms', $this->notification_type)) {
                $notificationUtil->sendSms($this->data);
                if (! empty($transaction)) {
                    $notificationUtil->activityLog($transaction, 'sms_notification_sent', null, [], false);
                }
            }
            if (in_array('whatsapp', $this->notification_type)) {
                $notificationUtil->getWhatsappNotificationLink($this->data);
            }
        } elseif (array_key_exists($this->template_for, $supplier_notifications)) {
            if (in_array('email', $this->notification_type)) {
                if ($this->template_for == 'purchase_order') {
                    $this->data['pdf_name'] = 'PO-' . ($transaction->ref_no ?? 'N/A') . '.pdf';
                    $this->data['pdf'] = $transactionUtil->getPurchaseOrderPdf($this->business_id, $this->transaction_id, true);
                }
                Notification::route('mail', $this->emails_array)
                    ->notify(new SupplierNotification($this->data));

                if (! empty($transaction)) {
                    $notificationUtil->activityLog($transaction, 'email_notification_sent', null, [], false);
                }
            }
            if (in_array('sms', $this->notification_type)) {
                $notificationUtil->sendSms($this->data);
                if (! empty($transaction)) {
                    $notificationUtil->activityLog($transaction, 'sms_notification_sent', null, [], false);
                }
            }
            if (in_array('whatsapp', $this->notification_type)) {
                $notificationUtil->getWhatsappNotificationLink($this->data);
            }
        }
    }
}
