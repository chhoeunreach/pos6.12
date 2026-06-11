<?php

namespace Modules\NotificationCenter\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\NotificationCenter\Entities\NotificationLog;
use Modules\NotificationCenter\Services\NotificationService;

class NotificationLogController extends Controller
{
    public function index()
    {
        $logs = NotificationLog::with('group')->latest()->paginate(50);

        return view('notificationcenter::logs.index', compact('logs'));
    }

    public function retry($id, NotificationService $service)
    {
        $log = NotificationLog::findOrFail($id);

        $recipient = [];
        if ($log->group_id) {
            $group = $log->group;
            if ($group) {
                $recipient = [
                    'group_id' => $group->id,
                    'chat_id' => $group->chat_id,
                    'send_text' => $group->send_text,
                    'send_pdf' => $group->send_pdf,
                ];
            }
        }

        if (empty($recipient)) {
            return redirect()->back()
                ->with('status', ['success' => false, 'msg' => 'Cannot retry: no recipient found']);
        }

        $result = $service->sendToRecipient(
            $recipient,
            $log->message ?? '',
            $log->pdf_path,
            $log->module_type,
            [
                'reference_type' => $log->reference_type,
                'reference_id' => $log->reference_id,
                'reference_no' => $log->reference_no,
            ]
        );

        return redirect()->back()
            ->with('status', ['success' => $result['success'], 'msg' => $result['success'] ? 'Retry sent' : 'Retry failed']);
    }
}
