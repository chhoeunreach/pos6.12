<?php

namespace Modules\Social\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [['name' => 'social_module', 'label' => 'Social Platform Module', 'default' => false]];
    }

    public function user_permissions(): array
    {
        return [
            ['value' => 'social.view', 'label' => 'Social Panel - View posts & stats', 'default' => false],
            ['value' => 'social.create', 'label' => 'Social Panel - Create & manage posts', 'default' => false],
            ['value' => 'social.moderate', 'label' => 'Social Panel - Moderate reports', 'default' => false],
            ['value' => 'social.settings', 'label' => 'Social Panel - Control Social API settings', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();
        $canOpen = $user->can('social.view')
            || $user->can('social.create')
            || $user->can('social.moderate')
            || $user->can('social.settings')
            || $user->can('superadmin')
            || $user->can('business_settings.access');

        if (!$canOpen) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) use ($user) {
            $menu->dropdown('Social Platform', function ($sub) use ($user) {
                if ($user->can('social.view') || $user->can('social.create') || $user->can('superadmin') || $user->can('business_settings.access')) {
                    $sub->url(route('social.admin.posts.index'), 'Feed & Sample Posts', [
                        'icon' => 'fa fa-newspaper-o',
                        'active' => request()->is('social/admin') || request()->is('social/admin/posts*'),
                    ]);
                }

                if ($user->can('social.moderate') || $user->can('superadmin') || $user->can('business_settings.access')) {
                    $sub->url(route('social.admin.reports.index'), 'Moderation Reports', [
                        'icon' => 'fa fa-flag',
                        'active' => request()->is('social/admin/reports*'),
                    ]);
                }

                if ($user->can('social.settings') || $user->can('superadmin') || $user->can('business_settings.access')) {
                    $sub->url(route('social.admin.settings.index'), 'API Control & Settings', [
                        'icon' => 'fa fa-cogs',
                        'active' => request()->is('social/admin/settings*'),
                    ]);
                }
            }, ['icon' => 'fa fa-globe', 'active' => request()->is('social/admin*')])->order(39);
        });
    }
}
