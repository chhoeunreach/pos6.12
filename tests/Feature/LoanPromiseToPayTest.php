<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Http\Controllers\LoanInstallmentListController;
use Modules\LoanManagement\Services\LoanCollectionService;
use Tests\Support\LoanModuleFeatureTestCase;

class LoanPromiseToPayTest extends LoanModuleFeatureTestCase
{
    public function test_promise_is_saved_and_visible_in_the_collection_workflow(): void
    {
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('mysql_loan');
        Schema::connection('mysql_loan')->create('loans', function (Blueprint $t) {
            $t->increments('id'); $t->string('status'); $t->softDeletes();
        });
        $migration = require module_path('LoanManagement', 'Database/migrations/2026_10_29_000001_ensure_loan_ptp_fields.php');
        $migration->up();
        DB::connection('mysql_loan')->table('loans')->insert(['id' => 1, 'status' => 'active']);
        $controller = new class extends LoanInstallmentListController {
            public function resetSchemaCache(): void { self::$loanTableExistsCache = []; self::$loanColumnCache = []; }
        };
        $controller->resetSchemaCache();
        $date = now()->addDay()->toDateString();
        $response = $controller->logPtp(Request::create('/', 'POST', ['ptp_date' => $date, 'ptp_amount' => 25, 'note' => 'Customer called']), 1);
        $this->assertTrue($response->getData()->success);
        $row = DB::connection('mysql_loan')->table('loans')->find(1);
        $this->assertSame($date, $row->ptp_date);
        $this->assertEquals(25, $row->ptp_amount);
        $this->assertSame('Customer called', $row->ptp_note);
        $this->assertSame('ptp', $row->collection_status);
        $this->assertSame('active', $row->ptp_status);
        $service = new class extends LoanCollectionService {
            public function promisedIds(): array {
                $query = DB::connection('mysql_loan')->table('loans as l');
                $query->where(function ($q) { $this->orWherePromiseToPay($q, false); });
                return $query->pluck('l.id')->all();
            }
        };
        $this->assertSame([1], $service->promisedIds());
        DB::purge('mysql_loan');
    }
}
