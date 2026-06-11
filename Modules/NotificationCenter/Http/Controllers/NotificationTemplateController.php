<?php

namespace Modules\NotificationCenter\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\NotificationCenter\Entities\NotificationTemplate;

class NotificationTemplateController extends Controller
{
    public function index()
    {
        $templates = NotificationTemplate::latest()->paginate(25);

        return view('notificationcenter::templates.index', compact('templates'));
    }

    public function create()
    {
        return view('notificationcenter::templates.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'module_type' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'message_template' => 'required|string',
            'pdf_template_view' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);

        $data['active'] = $request->boolean('active', true);

        NotificationTemplate::create($data);

        return redirect()->route('notificationcenter.templates.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }

    public function edit($id)
    {
        $template = NotificationTemplate::findOrFail($id);

        return view('notificationcenter::templates.edit', compact('template'));
    }

    public function update(Request $request, $id)
    {
        $template = NotificationTemplate::findOrFail($id);

        $data = $request->validate([
            'module_type' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'message_template' => 'required|string',
            'pdf_template_view' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);

        $data['active'] = $request->boolean('active', true);

        $template->update($data);

        return redirect()->route('notificationcenter.templates.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }

    public function destroy($id)
    {
        NotificationTemplate::findOrFail($id)->delete();

        return redirect()->route('notificationcenter.templates.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.deleted_success')]);
    }
}
