<?php

namespace Modules\KneayerngLinks\Http\Controllers;

use App\Http\Controllers\Install\ModulesController;

class InstallController extends ModulesController
{
    public function install()
    {
        return parent::installModule('KneayerngLinks');
    }

    public function uninstall()
    {
        return parent::uninstallModule('KneayerngLinks');
    }
}
