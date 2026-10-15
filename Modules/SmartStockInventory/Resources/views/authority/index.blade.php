@extends('smartstockinventory::layouts.master')
@section('page_title', 'Authority')
@section('hide_action_bar', '1')
@section('module_content')
<div class="box box-warning" style="border-top-color:#f39c12">
    <div class="box-header with-border">
        <h3 class="box-title" style="color:#d35400;font-weight:bold"><i class="fa fa-wrench"></i> Stock Inventory Authority — Auto Mismatch Fix & Data Regeneration</h3>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#modal_authority_mismatch_fix">
                <i class="fa fa-refresh"></i> Run Full Mismatch Fix & Regenerate Data
            </button>
        </div>
    </div>
    <div class="box-body" style="background:#fffcf5">
        <div class="row">
            <div class="col-md-2 col-sm-4 col-xs-6" style="width:20%">
                <a class="info-box bg-maroon" style="margin-bottom:10px;color:#fff;display:block;cursor:pointer" href="{{ ssi_route('ssi.authority.index', ['mismatch_status' => 'all_mismatches']) }}" title="Show All Transfers with Mismatches">
                    <span class="info-box-icon" style="background:rgba(0,0,0,0.15)"><i class="fa fa-exclamation-triangle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Mismatch Transfers</span>
                        <span class="info-box-number">{{ $mismatchSummary['total_mismatch_transfers'] ?? count($mismatchMap) }}</span>
                        <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                        <span class="progress-description">Default filtered view</span>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6" style="width:20%">
                <a class="info-box bg-red authority-detail-link" style="margin-bottom:10px;color:#fff;display:block" href="{{ ssi_route('ssi.authority.details', ['category' => 'over_allocated']) }}" title="View details">
                    <span class="info-box-icon" style="background:rgba(0,0,0,0.15)"><i class="fa fa-clone"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Over-Allocated</span>
                        <span class="info-box-number">{{ $mismatchSummary['over_allocated_count'] ?? 0 }}</span>
                        <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                        <span class="progress-description">Exceeds sold quantity</span>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6" style="width:20%">
                <a class="info-box bg-orange authority-detail-link" style="margin-bottom:10px;color:#fff;display:block" href="{{ ssi_route('ssi.authority.details', ['category' => 'missing_selected']) }}" title="View details">
                    <span class="info-box-icon" style="background:rgba(0,0,0,0.15)"><i class="fa fa-tag"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Missing Lots</span>
                        <span class="info-box-number">{{ $mismatchSummary['missing_selected_count'] ?? 0 }}</span>
                        <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                        <span class="progress-description">Missing purchase lot</span>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6" style="width:20%">
                <a class="info-box bg-yellow authority-detail-link" style="margin-bottom:10px;color:#fff;display:block" href="{{ ssi_route('ssi.authority.details', ['category' => 'differing_allocations']) }}" title="View details">
                    <span class="info-box-icon" style="background:rgba(0,0,0,0.15)"><i class="fa fa-exchange"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Diff Allocations</span>
                        <span class="info-box-number">{{ $mismatchSummary['diff_allocation_count'] ?? 0 }}</span>
                        <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                        <span class="progress-description">Differs from invoice lot</span>
                    </div>
                </a>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6" style="width:20%">
                <a class="info-box bg-aqua authority-detail-link" style="margin-bottom:10px;color:#fff;display:block" href="{{ ssi_route('ssi.authority.details', ['category' => 'all_line_issues']) }}" title="View details">
                    <span class="info-box-icon" style="background:rgba(0,0,0,0.15)"><i class="fa fa-check-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Sale Line Issues</span>
                        <span class="info-box-number">{{ $mismatchSummary['total_sale_line_issues'] ?? 0 }}</span>
                        <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                        <span class="progress-description">Lot Stock Audit</span>
                    </div>
                </a>
            </div>
        </div>
        <p style="margin:5px 0 0;font-size:13px;color:#7f8c8d">
            <i class="fa fa-info-circle text-info"></i> Running <strong>Full Mismatch Fix & Regenerate Data</strong> automatically removes duplicate/ghost allocations (including Line #2328), realigns differing allocations to verified selected invoice lots, repairs missing selected batch IDs, and recalculates <code>purchase_lines.quantity_sold</code> and <code>variation_location_details.qty_available</code>.
        </p>
    </div>
</div>

<div class="modal fade" id="modal_authority_mismatch_fix" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" action="{{ ssi_route('ssi.authority.fix_mismatches') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-shield"></i> Confirm Full Authority Mismatch Fix & Data Regeneration</h4>
                </div>
                <div class="modal-body">
                    <p>This action will perform a complete audit and automated correction across business inventory records:</p>
                    <ul>
                        <li><strong>Remove duplicate/ghost allocations:</strong> Cleans extraneous mapping rows (such as Line #2328 in ST2026/0261) where allocated quantity exceeds sold quantity.</li>
                        <li><strong>Repair missing selected lots:</strong> Updates invoice lines whose selected purchase line was missing or points to old IDs to match their physical allocation.</li>
                        <li><strong>Realign stock allocations:</strong> Aligns FIFO allocations to match the user's selected invoice lots for both sales and transfers.</li>
                        <li><strong>Recalculate stock balances:</strong> Recomputes <code>purchase_lines.quantity_sold</code> and synchronizes location available stock details.</li>
                    </ul>
                    <div class="form-group">
                        <label for="authority_fix_reason">Reason / Audit Reference (Optional):</label>
                        <input type="text" id="authority_fix_reason" name="reason" class="form-control" placeholder="Authority Mismatch Fix & Data Regeneration" value="Stock Inventory Authority Full Mismatch Fix & Data Regeneration">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fa fa-wrench"></i> Start Full Fix & Regenerate</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="box box-default" style="border-top:3px solid #3c8dbc;border-radius:3px;box-shadow:0 1px 3px rgba(0,0,0,0.08)">
    <div class="box-header with-border" style="background:#f9fafc">
        <h3 class="box-title" style="font-weight:600;font-size:16px"><i class="fa fa-filter text-primary"></i> Filter Stock Transfers</h3>
        <div class="box-tools pull-right">
            @if(($mismatchStatus ?? 'all_mismatches') === 'all_mismatches')
                <span class="label label-danger" style="font-size:12px;padding:5px 10px"><i class="fa fa-exclamation-triangle"></i> Default: Showing All Transfers with Mismatches ({{ $transfers->total() }})</span>
            @elseif(($mismatchStatus ?? '') === 'receipt_mismatch')
                <span class="label label-warning" style="font-size:12px;padding:5px 10px"><i class="fa fa-cube"></i> Filter: Receipt Discrepancies ({{ $transfers->total() }})</span>
            @elseif(($mismatchStatus ?? '') === 'allocation_mismatch')
                <span class="label label-warning" style="font-size:12px;padding:5px 10px"><i class="fa fa-exchange"></i> Filter: Allocation Mismatches ({{ $transfers->total() }})</span>
            @elseif(($mismatchStatus ?? '') === 'consistent')
                <span class="label label-success" style="font-size:12px;padding:5px 10px"><i class="fa fa-check"></i> Filter: Consistent Only ({{ $transfers->total() }})</span>
            @else
                <span class="label label-primary" style="font-size:12px;padding:5px 10px"><i class="fa fa-list"></i> Filter: All Transfers ({{ $transfers->total() }})</span>
            @endif
        </div>
    </div>
    <div class="box-body" style="background:#fff">
        <form method="get" action="{{ ssi_route('ssi.authority.index') }}" id="authority_filter_form">
            <div class="row">
                <div class="col-md-3 col-sm-6 form-group">
                    <label for="authority_search"><i class="fa fa-search"></i> Search Reference / Lot / SKU</label>
                    <input id="authority_search" class="form-control" name="search" value="{{ $filters['search'] ?? $reference }}" placeholder="e.g. ST2026/0261, SKU, Lot..." maxlength="100">
                </div>
                <div class="col-md-3 col-sm-6 form-group">
                    <label for="authority_mismatch_status"><i class="fa fa-exclamation-circle text-danger"></i> Mismatch Status</label>
                    <select id="authority_mismatch_status" name="mismatch_status" class="form-control">
                        <option value="all_mismatches" {{ ($mismatchStatus ?? 'all_mismatches') === 'all_mismatches' ? 'selected' : '' }}>
                            ⚠️ All Transfers with Mismatches (Default: {{ $mismatchSummary['total_mismatch_transfers'] ?? count($mismatchMap) }})
                        </option>
                        <option value="receipt_mismatch" {{ ($mismatchStatus ?? '') === 'receipt_mismatch' ? 'selected' : '' }}>
                            📦 Receipt Discrepancies ({{ count($receiptMismatchIds) }})
                        </option>
                        <option value="allocation_mismatch" {{ ($mismatchStatus ?? '') === 'allocation_mismatch' ? 'selected' : '' }}>
                            🔄 Allocation / Lot Mismatches ({{ count($saleLineMismatchIds) }})
                        </option>
                        <option value="all" {{ ($mismatchStatus ?? '') === 'all' ? 'selected' : '' }}>
                            📋 All Transfers (Any Status)
                        </option>
                        <option value="consistent" {{ ($mismatchStatus ?? '') === 'consistent' ? 'selected' : '' }}>
                            ✅ Consistent Only (No Mismatches)
                        </option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 form-group">
                    <label for="authority_from_location"><i class="fa fa-upload text-muted"></i> Source Location (From)</label>
                    <select id="authority_from_location" name="from_location_id" class="form-control">
                        <option value="">All Source Locations</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ ($filters['from_location_id'] ?? null) == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 form-group">
                    <label for="authority_to_location"><i class="fa fa-download text-muted"></i> Destination Location (To)</label>
                    <select id="authority_to_location" name="to_location_id" class="form-control">
                        <option value="">All Destination Locations</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ ($filters['to_location_id'] ?? null) == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 col-sm-6 form-group">
                    <label for="authority_start_date"><i class="fa fa-calendar"></i> Date From</label>
                    <input type="date" id="authority_start_date" name="start_date" class="form-control" value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-md-3 col-sm-6 form-group">
                    <label for="authority_end_date"><i class="fa fa-calendar"></i> Date To</label>
                    <input type="date" id="authority_end_date" name="end_date" class="form-control" value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div class="col-md-2 col-sm-6 form-group">
                    <label for="authority_per_page"><i class="fa fa-bars"></i> Per Page</label>
                    <select id="authority_per_page" name="per_page" class="form-control">
                        <option value="20" {{ ($filters['per_page'] ?? 20) == 20 ? 'selected' : '' }}>20 per page</option>
                        <option value="50" {{ ($filters['per_page'] ?? 20) == 50 ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ ($filters['per_page'] ?? 20) == 100 ? 'selected' : '' }}>100 per page</option>
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 form-group" style="padding-top:25px">
                    <button type="submit" class="btn btn-primary" style="margin-right:5px">
                        <i class="fa fa-filter"></i> Apply Filter
                    </button>
                    <a href="{{ ssi_route('ssi.authority.index') }}" class="btn btn-default" style="margin-right:5px">
                        <i class="fa fa-undo"></i> Reset
                    </a>
                    <a class="btn btn-info pull-right" href="{{ ssi_route('ssi.mismatch.index', ['view' => 'deducted_serial']) }}">
                        <i class="fa fa-list"></i> Serial Mismatches
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="box box-primary" style="border-radius:3px">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-shield text-primary"></i> Full authority corrections</h3>
        <div class="box-tools pull-right">
            @if($transfers->total() > 0)
                <span class="label label-primary" style="font-size:12px;padding:4px 8px">
                    Showing {{ $transfers->firstItem() }} - {{ $transfers->lastItem() }} of {{ $transfers->total() }} transfers
                </span>
            @endif
        </div>
    </div>
    <div class="box-body">
        <p class="text-muted" style="margin-bottom:15px">
            <i class="fa fa-info-circle"></i> Correct selected serials, deducted source batches, receiving serials and delivery dates. Each applied correction records who changed it and why.
        </p>

        @if($transfers->isEmpty())
            <div class="alert alert-info text-center" style="margin:20px 0;background:#e8f4f8;border-color:#bce8f1;color:#31708f">
                <h4><i class="fa fa-check-circle"></i> No Stock Transfers Found</h4>
                <p>No transfers matched your current filter criteria.</p>
                <a href="{{ ssi_route('ssi.authority.index') }}" class="btn btn-sm btn-info" style="margin-top:10px">
                    <i class="fa fa-refresh"></i> Reset to Default (Show All Mismatches)
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="authority_transfers_table">
                    <thead style="background:#f4f6f9">
                        <tr>
                            <th style="width:150px"><i class="fa fa-file-text-o"></i> Transfer Ref</th>
                            <th><i class="fa fa-random"></i> Route (From &rarr; To)</th>
                            <th style="width:160px"><i class="fa fa-calendar"></i> Date</th>
                            <th style="width:70px;text-align:center"><i class="fa fa-list-ol"></i> Lines</th>
                            <th><i class="fa fa-exclamation-triangle"></i> Mismatch Status & Issues</th>
                            <th style="width:160px;text-align:center"><i class="fa fa-cogs"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transfers as $item)
                            @php
                                $reasons = $mismatchMap[$item->id] ?? [];
                                $hasMismatch = !empty($reasons);
                            @endphp
                            <tr class="{{ $hasMismatch ? 'warning' : '' }}">
                                <td>
                                    <strong>{{ $item->ref_no ?: '#'.$item->id }}</strong>
                                    <br><small class="text-muted">ID: #{{ $item->id }}</small>
                                </td>
                                <td>
                                    <span class="label label-default" style="font-size:11px;background:#e9ecef;color:#495057;border:1px solid #ced4da">
                                        <i class="fa fa-map-marker text-danger"></i> {{ $item->from_location_name ?: 'Location #'.$item->from_location_id }}
                                    </span>
                                    <i class="fa fa-long-arrow-right text-muted" style="margin:0 5px"></i>
                                    <span class="label label-default" style="font-size:11px;background:#e9ecef;color:#495057;border:1px solid #ced4da">
                                        <i class="fa fa-map-marker text-success"></i> {{ $item->to_location_name ?: 'Location #'.$item->to_location_id }}
                                    </span>
                                </td>
                                <td>
                                    {{ $item->transaction_date }}
                                </td>
                                <td style="text-align:center">
                                    <span class="badge bg-purple">{{ $item->line_count ?? 0 }}</span>
                                </td>
                                <td>
                                    @if(!$hasMismatch)
                                        <span class="label label-success" style="font-size:11px;padding:3px 8px">
                                            <i class="fa fa-check"></i> Consistent
                                        </span>
                                    @else
                                        @foreach($reasons as $reason)
                                            @php
                                                $badgeClass = 'label-danger';
                                                $icon = 'fa-exclamation-circle';
                                                if (strpos($reason, 'missing') !== false) {
                                                    $badgeClass = 'label-warning';
                                                    $icon = 'fa-tag';
                                                } elseif (strpos($reason, 'dated') !== false) {
                                                    $badgeClass = 'label-danger';
                                                    $icon = 'fa-clock-o';
                                                } elseif (strpos($reason, 'receipt') !== false) {
                                                    $badgeClass = 'label-warning';
                                                    $icon = 'fa-cube';
                                                } elseif (strpos($reason, 'differs') !== false) {
                                                    $badgeClass = 'label-danger';
                                                    $icon = 'fa-exchange';
                                                }
                                            @endphp
                                            <div style="margin-bottom:3px">
                                                <span class="label {{ $badgeClass }}" style="display:inline-block;white-space:normal;text-align:left;font-size:11px;padding:3px 6px">
                                                    <i class="fa {{ $icon }}"></i> {{ $reason }}
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                </td>
                                <td style="text-align:center;vertical-align:middle">
                                    <a class="btn btn-primary btn-sm open-authority-correction" href="{{ ssi_route('ssi.authority.index', ['transfer_id' => $item->id]) }}" title="Open Authority Correction">
                                        <i class="fa fa-wrench"></i> Correct Transfer
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <p class="text-muted" style="margin-top:20px">
                        Showing {{ $transfers->firstItem() }} to {{ $transfers->lastItem() }} of {{ $transfers->total() }} entries
                    </p>
                </div>
                <div class="col-sm-6 text-right">
                    {{ $transfers->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="modal fade" id="authority_details_modal" tabindex="-1" role="dialog" aria-label="Authority issue details">
    <div class="modal-dialog modal-lg" role="document" style="width:95%;max-width:1400px"><div class="modal-content">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button><h4 class="modal-title">Authority issue details</h4></div>
        <div class="modal-body"></div>
    </div></div>
</div>
<div class="modal fade" id="authority_correction_modal" tabindex="-1" role="dialog" aria-label="Authority correction">
    <div class="modal-dialog modal-lg" role="document" style="width:95%;max-width:1400px"><div class="modal-content">
@if($transfer)
@include('smartstockinventory::authority.correction_form')
@endif
    </div></div>
</div>
@endsection
@section('module_js')
<script src="{{ asset('js/stock_authority.js?v=' . filemtime(public_path('js/stock_authority.js'))) }}"></script>
@endsection
