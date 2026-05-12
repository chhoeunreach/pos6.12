<?php

namespace Modules\MismatchFixer\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Modules\MismatchFixer\Http\Controllers\MismatchFixerController;

class FixMismatchCommand extends Command
{
    protected $signature = 'mismatch:fix {--purchase_line_id=} {--business_id=} {--reason=}';
    protected $description = 'Fix one mismatch row safely by purchase_line_id';

    public function handle(): int
    {
        $id = (int) $this->option('purchase_line_id');
        $business_id = (int) $this->option('business_id');
        if ($id <= 0) {
            $this->error('Required: --purchase_line_id');
            return self::FAILURE;
        }
        if ($business_id <= 0) {
            $business_id = (int) \DB::table('purchase_lines as pl')
                ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                ->where('pl.id', $id)
                ->value('t.business_id');
        }
        if ($business_id <= 0) {
            $this->error('Unable to resolve business_id for this purchase_line_id.');
            return self::FAILURE;
        }

        $request = Request::create('/mismatch-fixer/fix/'.$id, 'POST', [
            'reason' => (string)($this->option('reason') ?: 'CLI fix'),
        ]);
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put('user.business_id', $business_id);
        $request->session()->put('user.id', 0);

        $controller = app(MismatchFixerController::class);
        $response = $controller->fix($id, $request)->getData(true);

        $this->line(($response['success'] ? 'SUCCESS: ' : 'FAILED: ').$response['msg']);
        return $response['success'] ? self::SUCCESS : self::FAILURE;
    }
}
