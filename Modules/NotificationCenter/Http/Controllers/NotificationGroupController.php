<?php

namespace Modules\NotificationCenter\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\NotificationCenter\Entities\NotificationGroup;
use Modules\NotificationCenter\Services\TelegramService;

class NotificationGroupController extends Controller
{
    public function index()
    {
        $fromGroups = NotificationGroup::where('direction', 'from')
            ->orWhereNull('direction')
            ->latest()
            ->get();

        $toGroups = NotificationGroup::where('direction', 'to')
            ->latest()
            ->get();

        return view('notificationcenter::groups.index', compact('fromGroups', 'toGroups'));
    }

    public function create()
    {
        return view('notificationcenter::groups.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'chat_id' => 'required|string|max:255',
            'module_type' => 'required|string|max:100',
            'location_id' => 'nullable|integer',
            'send_text' => 'boolean',
            'send_pdf' => 'boolean',
            'active' => 'boolean',
        ]);

        $data['business_id'] = $request->session()->get('user.business_id');
        $data['created_by'] = $request->user()->id;
        $data['send_text'] = $request->boolean('send_text', true);
        $data['send_pdf'] = $request->boolean('send_pdf', true);
        $data['active'] = $request->boolean('active', true);

        NotificationGroup::create($data);

        return redirect()->route('notificationcenter.groups.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }

    public function edit($id)
    {
        $group = NotificationGroup::findOrFail($id);

        return view('notificationcenter::groups.edit', compact('group'));
    }

    public function update(Request $request, $id)
    {
        $group = NotificationGroup::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'chat_id' => 'required|string|max:255',
            'module_type' => 'required|string|max:100',
            'location_id' => 'nullable|integer',
            'send_text' => 'boolean',
            'send_pdf' => 'boolean',
            'active' => 'boolean',
        ]);

        $data['send_text'] = $request->boolean('send_text', true);
        $data['send_pdf'] = $request->boolean('send_pdf', true);
        $data['active'] = $request->boolean('active', true);

        $group->update($data);

        return redirect()->route('notificationcenter.groups.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }

    public function destroy($id)
    {
        NotificationGroup::findOrFail($id)->delete();

        return redirect()->route('notificationcenter.groups.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.deleted_success')]);
    }

    public function test($id, TelegramService $telegram)
    {
        $group = NotificationGroup::findOrFail($id);
        $result = $telegram->sendText($group->chat_id, 'Test message from Notification Center');

        if ($result['success']) {
            return redirect()->back()
                ->with('status', ['success' => true, 'msg' => 'Test message sent']);
        }

        return redirect()->back()
            ->with('status', ['success' => false, 'msg' => 'Test failed: '.($result['error'] ?? 'Unknown error')]);
    }
}
