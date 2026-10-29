<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Entities\LoanChatMessage;
use Modules\LoanManagement\Http\Controllers\LoanChatController;
use Modules\LoanManagement\Services\LoanChatService;
use Tests\Support\LoanModuleFeatureTestCase;

class LoanLiveChatTest extends LoanModuleFeatureTestCase
{
    private LoanChatService $service;
    private LoanChatController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'loanmanagement.chat.broadcasting_enabled' => false]);
        DB::purge('mysql_loan');
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('first_name'); $t->string('last_name'); $t->string('username'); $t->softDeletes();
        });
        DB::table('users')->insert(['id' => 1, 'first_name' => 'Support', 'last_name' => 'Staff', 'username' => 'support']);
        $schema = Schema::connection('mysql_loan');
        $schema->create('loan_customers', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('phone')->nullable(); $t->string('login_phone')->nullable();
            $t->integer('business_location_id')->nullable(); $t->string('status')->default('active'); $t->softDeletes();
        });
        $schema->create('loans', function (Blueprint $t) { $t->increments('id'); $t->softDeletes(); });
        DB::connection('mysql_loan')->table('loan_customers')->insert([
            ['id' => 10, 'name' => 'Alice', 'phone' => '012345678'], ['id' => 20, 'name' => 'Bob', 'phone' => '098765432'],
        ]);
        $migration = require module_path('LoanManagement', 'Database/migrations/2026_05_19_150000_complete_messenger_live_chat_workflow.php');
        $migration->up();
        auth()->setUser(new class(['id' => 1, 'username' => 'support', 'first_name' => 'Support', 'last_name' => 'Staff']) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function hasRole($roles, $guard = null): bool { return false; }
            public function can($ability, $arguments = []): bool { return in_array($ability, ['loan_management.chat.view', 'loan_management.chat.reply'], true); }
        });
        $this->service = new class extends LoanChatService {
            public function resetCaches(): void { self::$columnCache = []; self::$userCache = []; self::$fileCache = []; }
        };
        $this->service->resetCaches();
        $this->app->instance(LoanChatService::class, $this->service);
        $this->controller = new LoanChatController($this->service);
        Cache::put('loan_management.sidebar_badges', [], 600);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    }

    protected function tearDown(): void
    {
        DB::purge('mysql_loan');
        parent::tearDown();
    }

    private function request(string $path, array $data = []): Request
    {
        $request = Request::create($path, 'GET', $data);
        $request->setLaravelSession(session()->driver());
        $this->app->instance('request', $request);
        return $request;
    }

    private function thread(int $customer = 10)
    {
        return $this->service->createThread(['customer_id' => $customer, 'staff_id' => 1, 'created_by_type' => 'staff', 'created_by_id' => 1]);
    }

    public function test_live_channel_renders_with_registered_internal_chat_endpoints(): void
    {
        $request = $this->request('/loan-management/chat', ['channel' => 'live']);
        $view = $this->controller->webInbox($request);
        $this->assertSame('loanmanagement::chat.live', $view->getName());
        $html = $view->render();
        $this->assertStringContainsString('id="loanLiveChat"', $html);
        $this->assertStringContainsString('data-api="'.route('loan-management.chat-api.index').'"', $html);
        $this->assertStringContainsString('loan-live-chat.js', $html);
        $this->assertStringContainsString('id="lcSend"', $html);
        $this->assertSame('loanmanagement::chat.inbox', $this->controller->webInbox($this->request('/loan-management/chat'))->getName());
    }

    public function test_incremental_poll_returns_new_messages_and_scoped_read_receipts(): void
    {
        $thread = $this->thread();
        $sent = $this->service->sendTextMessage($thread, 'staff', 1, 'Hello Alice', [], 'staff-message-1');
        $reply = $this->service->sendTextMessage($thread->fresh(), 'customer', 10, 'Hello support', [], 'customer-message-1');
        $otherThread = $this->thread(20);
        $other = $this->service->sendTextMessage($otherThread, 'staff', 1, 'Private to Bob', [], 'other-message');
        LoanChatMessage::where('id', $sent->id)->update(['read_by_customer_at' => now()]);
        $request = $this->request('/loan-management/chat-api/chats/'.$thread->id, [
            'lightweight' => 1, 'after_message_id' => $sent->id, 'skip_read' => 1, 'receipt_ids' => [$sent->id, $other->id],
        ]);
        $data = $this->controller->show($request, $thread->id)->getData(true)['data'];
        $this->assertSame([$reply->id], array_column($data['messages'], 'id'));
        $this->assertSame([$sent->id], array_column($data['message_receipts'], 'id'));
        $this->assertNotEmpty($data['message_receipts'][0]['read_at']);
        $this->assertArrayNotHasKey('sidebar', $data);
        $this->assertNull($reply->fresh()->read_by_staff_at);
        $request = $this->request('/loan-management/chat-api/chats/'.$thread->id, ['lightweight' => 1]);
        $this->controller->show($request, $thread->id);
        $this->assertNotNull($reply->fresh()->read_by_staff_at);
        $full = $this->controller->show($this->request('/loan-management/chat-api/chats/'.$thread->id), $thread->id)->getData(true)['data'];
        $this->assertSame(2, $full['message_count']);
        $this->assertArrayHasKey('sidebar', $full);
    }

    public function test_retrying_the_same_local_message_id_does_not_duplicate_it(): void
    {
        $thread = $this->thread();
        $first = $this->service->sendTextMessage($thread, 'staff', 1, 'Retry me safely', [], 'safe-retry-uuid');
        $second = $this->service->sendTextMessage($thread->fresh(), 'staff', 1, 'Retry me safely', [], 'safe-retry-uuid');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $thread->messages()->count());
    }

    public function test_fast_inbox_polls_skip_prospective_customers_and_typing_is_visible(): void
    {
        $thread = $this->thread();
        $this->service->markTyping($thread, 'customer');
        $request = $this->request('/loan-management/chat-api/chats', ['threads_only' => 1]);
        $data = $this->controller->index($request)->getData(true)['data'];
        $this->assertCount(1, $data);
        $this->assertSame($thread->id, $data[0]['id']);
        $this->assertTrue($data[0]['typing']);
        $request = $this->request('/loan-management/chat-api/chats');
        $data = $this->controller->index($request)->getData(true)['data'];
        $this->assertCount(2, $data);
        $this->assertSame([10, 20], array_column($data, 'customer_id'));
    }

    public function test_a_retry_that_missed_the_first_lookup_rechecks_after_locking_the_thread(): void
    {
        $service = new class extends LoanChatService {
            public bool $missNextLookup = false;
            protected function findExistingLocalMessage(\Modules\LoanManagement\Entities\LoanChatThread $thread, string $senderType, int $senderId, string $localUuid): ?LoanChatMessage
            {
                if ($this->missNextLookup) { $this->missNextLookup = false; return null; }
                return parent::findExistingLocalMessage($thread, $senderType, $senderId, $localUuid);
            }
        };
        $thread = $this->thread();
        $first = $service->sendTextMessage($thread, 'staff', 1, 'Concurrent retry', [], 'concurrent-uuid');
        $service->missNextLookup = true;
        $retry = $service->sendTextMessage($thread->fresh(), 'staff', 1, 'Concurrent retry', [], 'concurrent-uuid');
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, $thread->messages()->count());
    }
}
