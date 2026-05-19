<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\LoanCollectionService;
use Modules\LoanManagement\Support\LoanCollectionConstants;

class LoanCollectionController extends Controller
{
    public function __construct(protected LoanCollectionService $service)
    {
    }

    public function index(Request $request, string $page)
    {
        $filters = $this->service->filters($request);
        $definition = $this->service->pageDefinition($page);
        $loans = $this->service->loansForPage($page, $filters);
        $options = $this->service->options();
        $badges = LoanCollectionConstants::class;

        return view('loanmanagement::collections.index', compact('page', 'definition', 'filters', 'loans', 'options', 'badges'));
    }

    public function reports(Request $request)
    {
        $filters = $this->service->filters($request);
        $options = $this->service->options();
        $cards = $this->service->dashboardCards($filters);

        return view('loanmanagement::collections.reports', compact('filters', 'options', 'cards'));
    }

    public function report(Request $request, string $report)
    {
        $filters = $this->service->filters($request);
        $options = $this->service->options();
        $title = LoanCollectionConstants::REPORTS[$report] ?? 'Collection Report';
        $loans = $this->service->reportRows($report, $filters);
        $badges = LoanCollectionConstants::class;

        return view('loanmanagement::collections.report', compact('report', 'title', 'filters', 'options', 'loans', 'badges'));
    }
}
