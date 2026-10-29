<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class LoanModuleFeatureTestCase extends TestCase
{
    private string $featureStorage;
    private $previousCompiledPath;
    private array $previousCompiledGlobals;
    private string $previousConnection;
    protected function setUp(): void
    {
        $this->previousCompiledPath = getenv('VIEW_COMPILED_PATH');
        $this->previousCompiledGlobals = [$_ENV['VIEW_COMPILED_PATH'] ?? null, $_SERVER['VIEW_COMPILED_PATH'] ?? null];
        $_ENV['VIEW_COMPILED_PATH'] = sys_get_temp_dir();
        $_SERVER['VIEW_COMPILED_PATH'] = sys_get_temp_dir();
        putenv('VIEW_COMPILED_PATH='.sys_get_temp_dir());
        parent::setUp();
        $this->featureStorage = sys_get_temp_dir().'/loan-feature-test-'.bin2hex(random_bytes(5));
        mkdir($this->featureStorage.'/app', 0777, true);
        $this->app->useStoragePath($this->featureStorage);
        config(['database.connections.loan_feature_core' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $this->previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('loan_feature_core');
        Schema::create('system', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->text('value')->nullable();
        });
        Cache::store('array')->forever('module_assets', ['js' => [], 'css' => []]);
    }

    protected function tearDown(): void
    {
        DB::purge('loan_feature_core');
        DB::setDefaultConnection($this->previousConnection);
        putenv($this->previousCompiledPath === false ? 'VIEW_COMPILED_PATH' : 'VIEW_COMPILED_PATH='.$this->previousCompiledPath);
        if ($this->previousCompiledGlobals[0] === null) unset($_ENV['VIEW_COMPILED_PATH']); else $_ENV['VIEW_COMPILED_PATH'] = $this->previousCompiledGlobals[0];
        if ($this->previousCompiledGlobals[1] === null) unset($_SERVER['VIEW_COMPILED_PATH']); else $_SERVER['VIEW_COMPILED_PATH'] = $this->previousCompiledGlobals[1];
        $this->app['files']->deleteDirectory($this->featureStorage);
        parent::tearDown();
    }
}
