<?php

namespace Tests\Feature;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Currency;
use App\PaymentAccount;
use App\Product;
use App\ProductVariation;
use App\TaxRate;
use App\Transaction;
use App\User;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use WithFaker;

    protected $user;
    protected $business;
    protected $location;
    protected $customer;
    protected $supplier;
    protected $product;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Test Business',
                'currency_id' => Currency::first()->id ?? 1,
                'default_profit_percent' => 25,
                'owner_id' => 1,
                'time_format' => 'h:i A',
                'start_date' => now(),
                'accounting_method' => 'fifo',
                'created_by' => 1,
                'currency_precision' => 2,
                'quantity_precision' => 2,
            ]
        );

        $this->location = BusinessLocation::firstOrCreate(
            ['id' => 1],
            [
                'business_id' => $this->business->id,
                'name' => 'Test Location',
                'location_id' => 'LOC-001',
                'landmark' => 'Test Landmark',
                'country' => 'Test Country',
                'state' => 'Test State',
                'city' => 'Test City',
                'zip_code' => '12345',
                'mobile' => '1234567890',
                'email' => 'test@example.com',
                'invoice_scheme_id' => 1,
                'invoice_layout_id' => 1,
                'sale_invoice_scheme_id' => 1,
            ]
        );

        $this->user = User::firstOrCreate(
            ['username' => 'mobile_test_admin'],
            [
                'surname' => 'Test',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'mobile_test@example.com',
                'password' => bcrypt('password'),
                'business_id' => $this->business->id,
                'language' => 'en',
                'status' => 'active',
                'is_admin' => 1,
            ]
        );

        $this->customer = Contact::firstOrCreate(
            ['business_id' => $this->business->id, 'name' => 'Test Customer', 'type' => 'customer'],
            ['mobile' => '0987654321', 'created_by' => $this->user->id]
        );

        $this->supplier = Contact::firstOrCreate(
            ['business_id' => $this->business->id, 'name' => 'Test Supplier', 'type' => 'supplier'],
            ['mobile' => '1122334455', 'created_by' => $this->user->id]
        );

        $this->product = Product::firstOrCreate(
            ['business_id' => $this->business->id, 'name' => 'Test Product', 'type' => 'single'],
            [
                'sku' => 'TST-' . time(),
                'unit_id' => 1,
                'brand_id' => null,
                'category_id' => null,
                'enable_stock' => 1,
                'alert_quantity' => 5,
                'created_by' => $this->user->id,
            ]
        );

        $productVariation = ProductVariation::firstOrCreate(
            ['product_id' => $this->product->id, 'name' => 'Default'],
            ['product_id' => $this->product->id, 'name' => 'Default']
        );

        Variation::firstOrCreate(
            ['product_id' => $this->product->id, 'name' => 'Default'],
            [
                'product_variation_id' => $productVariation->id,
                'sub_sku' => $this->product->sku,
                'default_purchase_price' => 10,
                'dpp_inc_tax' => 10,
                'default_sell_price' => 15,
                'sell_price_inc_tax' => 15,
            ]
        );

        $variation = Variation::where('product_id', $this->product->id)->first();
        if ($variation) {
            VariationLocationDetails::firstOrCreate(
                ['variation_id' => $variation->id, 'location_id' => $this->location->id],
                [
                    'product_id' => $this->product->id,
                    'product_variation_id' => $productVariation->id,
                    'qty_available' => 100,
                ]
            );
        }

        TaxRate::firstOrCreate(
            ['business_id' => $this->business->id, 'name' => 'VAT 10%'],
            ['amount' => 10, 'created_by' => $this->user->id]
        );

        $permissions = [
            'access_all_locations', 'dashboard.data',
            'sell.view', 'sell.create', 'sell.update', 'sell.delete',
            'purchase.view', 'purchase.create', 'purchase.update', 'purchase.delete',
            'product.view', 'product.create', 'product.update', 'product.delete',
            'customer.view', 'customer.create', 'customer.update', 'customer.delete',
            'supplier.view', 'supplier.create', 'supplier.update', 'supplier.delete',
            'stock_report.view', 'stock_adjustment.create', 'stock_transfer.create',
            'purchase_n_sell_report.view',
            'expense.access',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo($permissions);

        if (!Schema::hasTable('payment_accounts')) {
            Schema::create('payment_accounts', function ($table) {
                $table->increments('id');
                $table->integer('business_id')->unsigned();
                $table->string('name');
                $table->enum('account_type', ['cash', 'card', 'cheque', 'bank_transfer', 'payment_gateway', 'other'])->nullable();
                $table->text('account_details')->nullable();
                $table->boolean('is_default')->default(0);
                $table->integer('created_by')->unsigned()->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
        if (!PaymentAccount::where('business_id', $this->business->id)->where('name', 'Cash')->exists()) {
            $pa = new PaymentAccount();
            $pa->business_id = $this->business->id;
            $pa->name = 'Cash';
            $pa->account_type = 'cash';
            $pa->created_by = $this->user->id;
            $pa->save();
        }

        $this->token = $this->getAccessToken();
    }

    protected function getAccessToken()
    {
        $response = $this->postJson('/api/mobile/login', [
            'username' => 'mobile_test_admin',
            'password' => 'password',
        ]);

        if ($response->json('success')) {
            return $response->json('data.token');
        }

        return null;
    }

    protected function authHeaders()
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_login_success()
    {
        $response = $this->postJson('/api/mobile/login', [
            'username' => 'mobile_test_admin',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message', 'data' => ['token', 'token_type', 'user'],
            ]);
    }

    public function test_login_invalid_credentials()
    {
        $response = $this->postJson('/api/mobile/login', [
            'username' => 'mobile_test_admin',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_me()
    {
        $response = $this->getJson('/api/mobile/me', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'username', 'email']]);
    }

    public function test_logout()
    {
        $response = $this->postJson('/api/mobile/logout', [], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_permissions()
    {
        $response = $this->getJson('/api/mobile/permissions', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['all_permissions', 'can_access_all_locations', 'role'],
            ]);
    }

    public function test_locations()
    {
        $response = $this->getJson('/api/mobile/locations', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_dashboard()
    {
        $response = $this->getJson('/api/mobile/dashboard?' . http_build_query([
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'location_id' => $this->location->id,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_sale', 'actual_income', 'customer_payment',
                    'collection_payment', 'expenses', 'due',
                    'low_stock_count', 'recent_sales', 'top_products',
                ],
            ]);
    }

    public function test_pos_settings()
    {
        $response = $this->getJson('/api/mobile/pos/settings', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['business', 'locations', 'walk_in_customer', 'tax_rates', 'payment_types', 'currencies'],
            ]);
    }

    public function test_pos_products()
    {
        $response = $this->getJson('/api/mobile/pos/products?' . http_build_query([
            'location_id' => $this->location->id,
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_validate_cart()
    {
        $variation = Variation::where('product_id', $this->product->id)->first();

        $response = $this->postJson('/api/mobile/pos/validate-cart', [
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'variation_id' => $variation->id,
                    'quantity' => 2,
                    'unit_price_inc_tax' => 15,
                    'item_tax' => 0,
                    'line_discount_type' => 'fixed',
                    'line_discount_amount' => 0,
                ],
            ],
            'location_id' => $this->location->id,
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['total_before_tax', 'tax', 'discount', 'final_total']]);
    }

    public function test_create_pos_sale()
    {
        $variation = Variation::where('product_id', $this->product->id)->first();

        $response = $this->postJson('/api/mobile/pos/sales', [
            'contact_id' => $this->customer->id,
            'location_id' => $this->location->id,
            'status' => 'final',
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'variation_id' => $variation->id,
                    'quantity' => 1,
                    'unit_price' => 15,
                    'unit_price_inc_tax' => 15,
                    'item_tax' => 0,
                    'line_discount_type' => 'fixed',
                    'line_discount_amount' => 0,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 15, 'paid_on' => now()->toDateTimeString()],
            ],
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'invoice_no', 'final_total', 'payment_status']]);
    }

    public function test_list_products()
    {
        $response = $this->getJson('/api/mobile/products?' . http_build_query([
            'per_page' => 10,
            'search' => 'Test',
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_product()
    {
        $response = $this->getJson("/api/mobile/products/{$this->product->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'sku']]);
    }

    public function test_list_customers()
    {
        $response = $this->getJson('/api/mobile/customers?' . http_build_query([
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_customer()
    {
        $response = $this->getJson("/api/mobile/customers/{$this->customer->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'mobile']]);
    }

    public function test_customer_ledger()
    {
        $response = $this->getJson("/api/mobile/customers/{$this->customer->id}/ledger?" . http_build_query([
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['contact', 'start_date', 'end_date', 'opening_balance', 'closing_balance', 'transactions'],
            ]);
    }

    public function test_list_suppliers()
    {
        $response = $this->getJson('/api/mobile/suppliers?' . http_build_query([
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_list_sales()
    {
        $response = $this->getJson('/api/mobile/sales?' . http_build_query([
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_list_purchases()
    {
        $response = $this->getJson('/api/mobile/purchases?' . http_build_query([
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_list_expenses()
    {
        $response = $this->getJson('/api/mobile/expenses?' . http_build_query([
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_expense_categories()
    {
        $response = $this->getJson('/api/mobile/expenses/categories', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_list_stock()
    {
        $response = $this->getJson('/api/mobile/stock?' . http_build_query([
            'location_id' => $this->location->id,
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_low_stock()
    {
        $response = $this->getJson('/api/mobile/stock/low?' . http_build_query([
            'location_id' => $this->location->id,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_list_stock_adjustments()
    {
        $response = $this->getJson('/api/mobile/stock/adjustments?' . http_build_query([
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_list_stock_transfers()
    {
        $response = $this->getJson('/api/mobile/stock/transfers?' . http_build_query([
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_list_payments()
    {
        $response = $this->getJson('/api/mobile/payments?' . http_build_query([
            'per_page' => 10,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_report_sales()
    {
        $response = $this->getJson('/api/mobile/reports/sales?' . http_build_query([
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['summary', 'sales']]);
    }

    public function test_report_products()
    {
        $response = $this->getJson('/api/mobile/reports/products?' . http_build_query([
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_report_customers_due()
    {
        $response = $this->getJson('/api/mobile/reports/customers-due', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['total_due', 'customers']]);
    }

    public function test_report_suppliers_due()
    {
        $response = $this->getJson('/api/mobile/reports/suppliers-due', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['total_due', 'suppliers']]);
    }

    public function test_report_stock()
    {
        $response = $this->getJson('/api/mobile/reports/stock?' . http_build_query([
            'location_id' => $this->location->id,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['total_stock_value', 'products']]);
    }

    public function test_report_payments()
    {
        $response = $this->getJson('/api/mobile/reports/payments?' . http_build_query([
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['summary', 'payments']]);
    }

    public function test_report_expenses()
    {
        $response = $this->getJson('/api/mobile/reports/expenses?' . http_build_query([
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['total_expenses', 'count', 'expenses']]);
    }

    public function test_report_purchases()
    {
        $response = $this->getJson('/api/mobile/reports/purchases?' . http_build_query([
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['total_purchase', 'total_paid', 'total_due', 'count', 'purchases']]);
    }

    public function test_report_profit_loss()
    {
        $response = $this->getJson('/api/mobile/reports/profit-loss?' . http_build_query([
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_report_local_cashier()
    {
        $response = $this->getJson('/api/mobile/reports/local-cashier?' . http_build_query([
            'location_id' => $this->location->id,
            'user_id' => $this->user->id,
            'start_date' => now()->startOfDay()->format('Y-m-d'),
            'end_date' => now()->endOfDay()->format('Y-m-d'),
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_sale', 'actual_income', 'customer_payment', 'collection_payment',
                    'expenses', 'due', 'user_summary', 'location_summary',
                    'customer_group_summary', 'brand_summary', 'payment_method_summary',
                    'sale_detail_rows', 'customer_payment_detail_rows',
                    'collection_payment_detail_rows', 'expense_detail_rows',
                ],
            ]);
    }

    public function test_settings_index()
    {
        $response = $this->getJson('/api/mobile/settings', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['business', 'locations', 'tax_rates', 'payment_accounts'],
            ]);
    }

    public function test_settings_payment_methods()
    {
        $response = $this->getJson('/api/mobile/payment-methods', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_settings_business()
    {
        $response = $this->getJson('/api/mobile/business', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'currency', 'locations']]);
    }

    public function test_unauthenticated_access()
    {
        $response = $this->getJson('/api/mobile/dashboard');

        $response->assertStatus(401);
    }
}
