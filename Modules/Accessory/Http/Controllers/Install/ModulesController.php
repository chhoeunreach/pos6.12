<?php

namespace Modules\Accessory\Http\Controllers\Install;

use Modules\Accessory\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use App\System;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Module;
use ZipArchive;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ModulesController extends Controller
{
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ModuleUtil  $moduleUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $modules = $this->localModules();

        foreach ($modules as $module => $details) {
            $modules[$module]['install_link'] = $this->localModuleActionUrl($details['name'], 'index');
            $modules[$module]['update_link'] = $this->localModuleActionUrl($details['name'], 'update');
            $modules[$module]['uninstall_link'] = $this->localModuleActionUrl($details['name'], 'uninstall');
        }

        $is_demo = (config('app.env') == 'demo');
        $mods = serialize([]);
        
        return view('accessory::install.modules.index')
            ->with(compact('modules', 'is_demo', 'mods'));

        //Option to uninstall

        //Option to activate/deactivate

        //Upload module.
    }

    public function regenerate()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            Artisan::call('module:publish');
            Artisan::call('passport:install --force');
            // Artisan::call('scribe:generate');

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            $output = ['success' => 1,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Activate/Deaactivate the specified module.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $module_name)
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $this->setLocalModuleStatus($module_name, $request->action_type == 'activate');
            if ($request->action_type == 'activate') {
                $this->ensureLocalModulePermissions($module_name);
            }

            // Clear module assets cache when module is activated/deactivated
            Cache::forget('accessory_module_assets');

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    public function installModule($module_name)
    {
        return $this->runLocalModuleLifecycleAction($module_name, 'install');
    }

    public function uninstallModule($module_name)
    {
        return $this->runLocalModuleLifecycleAction($module_name, 'uninstall');
    }

    public function updateModule($module_name)
    {
        return $this->runLocalModuleLifecycleAction($module_name, 'update');
    }

    private function runLocalModuleLifecycleAction(string $module_name, string $action)
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        if (! is_dir($this->localModulePath($module_name))) {
            abort(404);
        }

        try {
            if ($action !== 'uninstall') {
                $this->setLocalModuleStatus($module_name, true);
            }

            $controller = $this->localModuleInstallController($module_name);
            if (class_exists($controller)) {
                $controller = app($controller);

                if ($action === 'install' && method_exists($controller, 'index') && empty($this->localModuleInstalledVersion($module_name))) {
                    $this->forgetLocalModuleCaches();

                    return redirect()->to($this->localModuleInstallerUrl($module_name, 'install'));
                }

                if (method_exists($controller, $action)) {
                    $response = $controller->{$action}(request());
                    $this->setLocalModuleStatus($module_name, $action !== 'uninstall');
                    $this->forgetLocalModuleCaches();

                    return $response;
                }
            }

            if ($action === 'uninstall') {
                System::removeProperty(strtolower($module_name).'_version');
                System::removeProperty(Str::snake($module_name).'_version');
                $this->setLocalModuleStatus($module_name, false);
            } else {
                $this->ensureLocalModulePermissions($module_name);
                $this->setLocalModuleStatus($module_name, true);
            }

            $this->forgetLocalModuleCaches();

            return redirect()->back()->with(['status' => [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ]]);
        } catch (\Throwable $e) {
            return redirect()->back()->with(['status' => [
                'success' => false,
                'msg' => $e->getMessage(),
            ]]);
        }
    }

    /**
     * Deletes the module.
     *
     * @param  string  $module_name
     * @return \Illuminate\Http\Response
     */
    public function destroy($module_name)
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $path = $this->localModulePath($module_name);

            // Clear module assets cache when module is deleted
            Cache::forget('accessory_module_assets');

            die("To delete the module delete this folder <br/>" . $path . '<br/> Go back after deleting');

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Upload the module.
     */
    public function uploadModule(Request $request)
    {
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $request->validate([
                'module' => 'required|file|mimes:zip|max:51200', // 50MB max
            ]);

            //get zipped file
            $module = $request->file('module');
            $module_name = Str::slug(str_replace('.zip', '', $module->getClientOriginalName()));

            $path = $this->localModulesPath();
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            $existing_modules = $this->localModuleDirectories();

            //extract the zipped file in given path
            $zip = new ZipArchive();
            if ($zip->open($module->getRealPath()) === true) {
                $zip->extractTo($path.'/');
                $zip->close();

                $module_dir = $this->detectUploadedModuleDirectory($existing_modules, $path, $module_name);
                if (!(file_exists($module_dir . '/composer.json')
                    && file_exists($module_dir . '/module.json')
                    && file_exists($module_dir . '/Config/config.php'))
                ) {
                    \File::deleteDirectory($module_dir);
                    $output = ['success' => false,
                        'msg' => __('lang_v1.pls_upload_valid_zip_file'),
                    ];
                    return redirect()->back()->with(['status' => $output]);
                }

                // Clear module assets cache when new module is uploaded
                Cache::forget('accessory_module_assets');
            }

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    private function __available_modules()
    {
        return 'a:15:{i:0;O:8:"stdClass":4:{s:1:"n";s:10:"Essentials";s:2:"dn";s:17:"Essentials Module";s:1:"u";s:53:"https://ultimatefosters.com/recommends/essential-app/";s:1:"d";s:49:"Essentials features for every growing businesses.";}i:1;O:8:"stdClass":4:{s:1:"n";s:10:"Superadmin";s:2:"dn";s:17:"Superadmin Module";s:1:"u";s:54:"https://ultimatefosters.com/recommends/superadmin-app/";s:1:"d";s:76:"Turn your POS to SaaS application and start earning by selling subscriptions";}i:2;O:8:"stdClass":4:{s:1:"n";s:11:"Woocommerce";s:2:"dn";s:18:"Woocommerce Module";s:1:"u";s:55:"https://ultimatefosters.com/recommends/woocommerce-app/";s:1:"d";s:36:"Sync your Woocommerce store with POS";}i:3;O:8:"stdClass":4:{s:1:"n";s:13:"Manufacturing";s:2:"dn";s:20:"Manufacturing Module";s:1:"u";s:57:"https://ultimatefosters.com/recommends/manufacturing-app/";s:1:"d";s:70:"Manufacture products from raw materials, organise recipe & ingredients";}i:4;O:8:"stdClass":4:{s:1:"n";s:7:"Project";s:2:"dn";s:14:"Project Module";s:1:"u";s:51:"https://ultimatefosters.com/recommends/project-app/";s:1:"d";s:66:"Manage Projects, tasks, tasks time logs, activities and much more.";}i:5;O:8:"stdClass":4:{s:1:"n";s:6:"Repair";s:2:"dn";s:13:"Repair Module";s:1:"u";s:50:"https://ultimatefosters.com/recommends/repair-app/";s:1:"d";s:248:"Repair module helps with complete repair service management of electronic goods like Cellphone, Computers, Desktops, Tablets, Television, Watch, Wireless devices, Printers, Electronic instruments and many more similar devices which you can imagine!";}i:6;O:8:"stdClass":4:{s:1:"n";s:3:"Crm";s:2:"dn";s:10:"CRM Module";s:1:"u";s:63:"https://ultimatefosters.com/product/crm-module-for-ultimatepos/";s:1:"d";s:39:"Customer relationship management module";}i:7;O:8:"stdClass":4:{s:1:"n";s:16:"ProductCatalogue";s:2:"dn";s:16:"ProductCatalogue";s:1:"u";s:90:"https://codecanyon.net/item/digital-product-catalogue-menu-module-for-ultimatepos/28825346";s:1:"d";s:32:"Digital Product catalogue Module";}i:8;O:8:"stdClass":4:{s:1:"n";s:10:"Accounting";s:2:"dn";s:17:"Accounting Module";s:1:"u";s:82:"https://ultimatefosters.com/product/accounting-bookkeeping-module-for-ultimatepos/";s:1:"d";s:48:"Accounting & Book keeping module for UltimatePOS";}i:9;O:8:"stdClass":4:{s:1:"n";s:12:"AiAssistance";s:2:"dn";s:19:"AiAssistance Module";s:1:"u";s:73:"https://ultimatefosters.com/product/ai-assistance-module-for-ultimatepos/";s:1:"d";s:104:"AI Assistant module for UltimatePOS. This module used openAI API to help with in copywriting & reporting";}i:10;O:8:"stdClass":4:{s:1:"n";s:15:"AssetManagement";s:2:"dn";s:22:"AssetManagement Module";s:1:"u";s:76:"https://ultimatefosters.com/product/asset-management-module-for-ultimatepos/";s:1:"d";s:40:"Useful for managing all kinds of assets.";}i:11;O:8:"stdClass":4:{s:1:"n";s:3:"Cms";s:2:"dn";s:10:"Cms Module";s:1:"u";s:59:"https://ultimatefosters.com/product/ultimatepos-cms-module/";s:1:"d";s:153:"Mini CMS (content management system) Module for UltimatePOS to help manage all frontend contents like Landing page, Blogs, Contact us & many other pages.";}i:12;O:8:"stdClass":4:{s:1:"n";s:9:"Connector";s:2:"dn";s:20:"Connector/API Module";s:1:"u";s:68:"https://ultimatefosters.com/product/rest-api-module-for-ultimatepos/";s:1:"d";s:24:"Provide the API for POS.";}i:13;O:8:"stdClass":4:{s:1:"n";s:3:"Gym";s:2:"dn";s:10:"Gym Module";s:1:"u";s:74:"https://ultimatefosters.com/product/gym-management-module-for-ultimatepos/";s:1:"d";s:37:"Gym Management module for UltimatePOS";}i:14;O:8:"stdClass":4:{s:1:"n";s:3:"Hms";s:2:"dn";s:23:"Hotel Management Module";s:1:"u";s:87:"https://ultimatefosters.com/product/hms-hotel-management-system-module-for-ultimatepos/";s:1:"d";s:119:"Hotel Management System module for UltimatePOS, provides features for room bookings, extras, coupons & related features";}}';
    }

    private function localModulesPath(): string
    {
        return base_path('Modules');
    }

    private function localModuleDirectories(): array
    {
        if (! is_dir($this->localModulesPath())) {
            return [];
        }

        return array_values(array_filter(glob($this->localModulesPath().'/*'), 'is_dir'));
    }

    private function localModules(): array
    {
        $modules = [];
        $statuses = $this->localModuleStatuses();

        foreach ($this->localModuleDirectories() as $module_path) {
            $module_json = $module_path.'/module.json';
            if (! file_exists($module_json)) {
                continue;
            }

            $details = json_decode(file_get_contents($module_json), true) ?: [];
            $name = $details['name'] ?? basename($module_path);
            $has_installer = $this->hasLocalModuleInstaller($name);
            $installed_version = $this->localModuleInstalledVersion($name);
            $is_installed = $has_installer
                ? ! empty($installed_version)
                : ($statuses[$name] ?? (bool) ($details['active'] ?? false));

            $modules[$name] = [
                'name' => $name,
                'description' => $details['description'] ?? '',
                'active' => $is_installed ? 1 : 0,
                'is_installed' => $is_installed,
                'path' => $module_path,
            ];

            if (! empty($installed_version)) {
                $modules[$name]['version'] = [
                    'installed_version' => $installed_version,
                    'is_update_available' => version_compare(
                        $installed_version,
                        $this->localModuleAvailableVersion($name, $installed_version),
                        '<'
                    ),
                ];
            }
        }

        return $modules;
    }

    private function localModuleStatusesPath(): string
    {
        return base_path('modules_statuses.json');
    }

    private function localModuleStatuses(): array
    {
        $path = $this->localModuleStatusesPath();

        return file_exists($path)
            ? (json_decode(file_get_contents($path), true) ?: [])
            : [];
    }

    private function setLocalModuleStatus(string $module_name, bool $active): void
    {
        $statuses = $this->localModuleStatuses();
        $statuses[$module_name] = $active;

        file_put_contents($this->localModuleStatusesPath(), json_encode($statuses, JSON_PRETTY_PRINT));
    }

    private function localModulePath(string $module_name): string
    {
        return $this->localModulesPath().'/'.$module_name;
    }

    private function localModuleActionUrl(string $module_name, string $action): string
    {
        $method = $action === 'uninstall'
            ? 'uninstallModule'
            : ($action === 'update' ? 'updateModule' : 'installModule');

        return action([self::class, $method], ['module_name' => $module_name]);
    }

    private function hasLocalModuleInstaller(string $module_name): bool
    {
        $controller = $this->localModuleInstallController($module_name);

        return class_exists($controller);
    }

    private function localModuleInstallController(string $module_name): string
    {
        return 'Modules\\'.$module_name.'\Http\Controllers\InstallController';
    }

    private function localModuleInstallerUrl(string $module_name, string $action): string
    {
        $prefixes = [
            'Accessory' => trim(config('accessory.route_prefix', 'accessory'), '/'),
            'LoanManagement' => 'loan-management',
            'LocalCashierReport' => 'local-cashier-report',
            'NotificationCenter' => 'notification-center',
            'Repair' => 'repair',
            'Service' => trim(config('service.route_prefix', 'service'), '/'),
            'SmartStockInventory' => 'smart-stock-inventory',
            'Superadmin' => 'superadmin',
            'WarrantyCardPrint' => 'warranty-card-print',
        ];

        $prefix = $prefixes[$module_name] ?? Str::kebab($module_name);
        $suffix = $action === 'install' ? 'install' : 'install/'.$action;

        return url(trim($prefix.'/'.$suffix, '/'));
    }

    private function localModuleInstalledVersion(string $module_name): ?string
    {
        $module_key = strtolower($module_name).'_version';
        $snake_key = Str::snake($module_name).'_version';

        return System::getProperty($module_key) ?: System::getProperty($snake_key);
    }

    private function localModuleAvailableVersion(string $module_name, string $default): string
    {
        return (string) (
            config(strtolower($module_name).'.module_version')
            ?: config(Str::snake($module_name).'.module_version')
            ?: $default
        );
    }

    private function ensureLocalModulePermissions(string $module_name): void
    {
        $class = 'Modules\\'.$module_name.'\Http\Controllers\DataController';

        if (! class_exists($class)) {
            return;
        }

        $controller = app($class);
        if (! method_exists($controller, 'user_permissions')) {
            return;
        }

        foreach ((array) $controller->user_permissions() as $permission) {
            $permission_name = is_array($permission)
                ? ($permission['value'] ?? $permission['name'] ?? null)
                : null;

            if (empty($permission_name)) {
                continue;
            }

            Permission::firstOrCreate([
                'name' => $permission_name,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function forgetLocalModuleCaches(): void
    {
        Cache::forget('module_assets');
        Cache::forget('accessory_module_assets');
    }

    private function detectUploadedModuleDirectory(array $existing_modules, string $path, string $fallback_name): string
    {
        $current_modules = $this->localModuleDirectories();
        $new_modules = array_values(array_diff($current_modules, $existing_modules));

        foreach ($new_modules as $module_path) {
            if (file_exists($module_path.'/module.json')) {
                return $module_path;
            }
        }

        return $path.'/'.$fallback_name;
    }
}
