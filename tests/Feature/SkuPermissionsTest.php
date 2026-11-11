<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SmartStockInventory\Http\Middleware\SmartStockAccessMiddleware;
use Modules\SmartStockInventory\Support\SkuPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SkuPermissionsTest extends TestCase
{
    private $original;
    private $user;
    private $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original = config('database.default');
        config(['database.default' => 'sku_permissions', 'database.connections.sku_permissions' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        foreach (['permissions', 'roles'] as $name) {
            Schema::create($name, function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        }
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->integer('role_id'); $t->integer('permission_id'); });
        Schema::create('model_has_roles', function (Blueprint $t) { $t->integer('role_id'); $t->string('model_type'); $t->integer('model_id'); });
        Schema::create('model_has_permissions', function (Blueprint $t) { $t->integer('permission_id'); $t->string('model_type'); $t->integer('model_id'); });
        Schema::create('business_locations', function (Blueprint $t) { $t->integer('id'); $t->integer('business_id'); });
        DB::table('business_locations')->insert([['id' => 1, 'business_id' => 1], ['id' => 2, 'business_id' => 1], ['id' => 3, 'business_id' => 2]]);
        Schema::create('system', function (Blueprint $t) { $t->string('key'); $t->string('value'); });
        DB::table('system')->insert(['key' => 'smartstockinventory_version', 'value' => '1.0.0']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_unique(array_merge(...array_values(SkuPermissions::ACTIONS))) as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }
        $this->role = Role::create(['name' => 'SKU Staff#1', 'guard_name' => 'web']);
        $this->user = new User;
        $this->user->setRawAttributes(['id' => 7, 'business_id' => 1, 'username' => 'sku_permission_test']);
        $this->user->exists = true;
        $this->user->assignRole($this->role);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::purge('sku_permissions'); config(['database.default' => $this->original]);
        parent::tearDown();
    }

    private function grant(array $permissions): void
    {
        $this->role->syncPermissions($permissions);
        $this->user->unsetRelation('roles')->unsetRelation('permissions');
    }

    public function test_each_action_is_controlled_by_the_permissions_assigned_to_a_role(): void
    {
        foreach (SkuPermissions::ACTIONS as $action => $required) {
            $this->grant($required);
            $this->assertTrue(SkuPermissions::allows($this->user, $action), $action);
            foreach ($required as $missing) {
                $this->grant(array_values(array_diff($required, [$missing])));
                $this->assertFalse(SkuPermissions::allows($this->user, $action), $action . ' without ' . $missing);
            }
        }
    }

    public function test_each_sku_route_rejects_missing_role_permissions_even_when_called_directly(): void
    {
        $middleware = new SmartStockAccessMiddleware;
        foreach (SkuPermissions::ROUTES as $routeName => $action) {
            $request = Request::create('/smart-stock-inventory/sku');
            $request->setUserResolver(fn () => $this->user);
            $route = new Route(['GET'], '/smart-stock-inventory/sku', fn () => null); $route->name($routeName);
            $request->setRouteResolver(fn () => $route);
            $this->grant(SkuPermissions::ACTIONS[$action]);
            $this->assertSame('allowed', $middleware->handle($request, fn () => 'allowed'));
            $this->grant([]);
            try { $middleware->handle($request, fn () => 'allowed'); $this->fail('Expected route rejection: ' . $routeName); }
            catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        }
    }

    public function test_cost_visibility_and_price_edits_do_not_follow_export_permission(): void
    {
        $this->grant(['stock_inventory.view', 'stock_inventory.export']);
        $this->assertTrue(SkuPermissions::allows($this->user, 'export'));
        foreach (['view_cost', 'update_lot', 'update_cost', 'update_sale_price'] as $action) {
            $this->assertFalse(SkuPermissions::allows($this->user, $action));
        }
    }

    public function test_every_registered_sku_function_has_an_explicit_permission_rule(): void
    {
        $count = 0;
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (!str_starts_with($name, 'ssi.sku.')) continue;
            $this->assertArrayHasKey($name, SkuPermissions::ROUTES, 'Missing permission for ' . $name);
            $count++;
        }
        $this->assertSame(count(SkuPermissions::ROUTES), $count);
    }
    public function test_sku_location_access_follows_explicit_grants_not_role_name(): void
    {
        Permission::create(['name' => 'location.1', 'guard_name' => 'web']);
        Permission::create(['name' => 'access_all_locations', 'guard_name' => 'web']);
        $this->role->update(['name' => 'Warehouse Admin#1']);
        $this->user->givePermissionTo('location.1');
        $this->user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertSame([1], SkuPermissions::locationIds($this->user, 1));
        $this->assertSame([], SkuPermissions::locationIds($this->user, 2));
        $this->grant(['access_all_locations']);
        $this->assertSame([1, 2], SkuPermissions::locationIds($this->user, 1));
    }

}
