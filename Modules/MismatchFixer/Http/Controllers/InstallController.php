<?php

namespace Modules\MismatchFixer\Http\Controllers;

use Illuminate\Routing\Controller;

class InstallController extends Controller
{
    public function index() { return ['success' => 1, 'msg' => 'MismatchFixer ready']; }
    public function install() { return ['success' => 1, 'msg' => 'MismatchFixer installed']; }
    public function uninstall() { return ['success' => 1, 'msg' => 'MismatchFixer uninstall skipped']; }
    public function update() { return ['success' => 1, 'msg' => 'MismatchFixer updated']; }
}
