@if (!empty($stockReview['sale_line_issues']) || !empty($stockReview['stock_issues']) || !empty($stockReview['transfer_issues']))
    @php $allow_overselling = \App\Utils\BusinessUtil::allowsOverselling((int) $sell_transfer->business_id); @endphp
    <section class="alert alert-warning" role="alert">
        <h4><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ $allow_overselling ? 'Stock Records Review' : 'Stock Reconciliation Required' }}</h4>
        @if($allow_overselling)<p>Overselling is enabled. Negative source balances are allowed; serial and allocation mismatches still need correction.</p>@endif
        <p>Transfer {{ $sell_transfer->ref_no }}: {{ $allow_overselling ? 'review the source balances, selected serials and allocations below.' : 'verify the physical serial and transfer dates before changing inconsistent stock records.' }}</p>
        <button type="button" class="btn btn-primary" id="auto_correct_stock"
            data-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'autoCorrectStock'], [$sell_transfer->id]) }}"
            title="Recalculate stock and align allocations with selected serials for administrators">
            <i class="fa fa-check-circle" aria-hidden="true"></i> Auto Correct
        </button>
        <button type="button" class="btn btn-default" id="generate_stock_correction_plan"
            data-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'generateCorrectionPlan'], [$sell_transfer->id]) }}"
            data-options-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'manualCorrectionOptions'], [$sell_transfer->id]) }}"
            data-preview-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'previewManualCorrection'], [$sell_transfer->id]) }}"
            data-manual-apply-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'applyManualCorrection'], [$sell_transfer->id]) }}"
            data-apply-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'applyCorrectionPlan'], [$sell_transfer->id]) }}">
            <i class="fa fa-refresh" aria-hidden="true"></i> Generate Correction Plan
        </button>
        <button type="button" class="btn btn-default" id="edit_verified_stock_correction"><i class="fa fa-edit" aria-hidden="true"></i> Correct Serials</button>
        <div id="auto_stock_correction_result" role="status" aria-live="polite" hidden style="margin-top:12px"></div>
        @if (!empty($stockReview['sale_line_issues']))
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>Line</th><th>Selected Serial</th><th>Allocated Serial</th><th>Receipt Date</th><th>Stock Issue</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach ($stockReview['sale_line_issues'] as $issue)
                            <tr>
                                <td>{{ $issue['sell_line_id'] }}</td>
                                <td>
                                    @can('stock_inventory.view')
                                        @if (!empty($issue['selected_lot']))
                                            <a href="{{ ssi_route('ssi.lot.history', $issue['selected_lot']) }}" target="_blank" rel="noopener noreferrer">{{ $issue['selected_lot'] }}</a>
                                        @else
                                            --
                                        @endif
                                    @else
                                        {{ $issue['selected_lot'] ?: '--' }}
                                    @endcan
                                </td>
                                <td>
                                    @forelse ($issue['allocations'] as $allocation)
                                        <div>
                                            @can('stock_inventory.view')
                                                @if (!empty($allocation->lot_number))
                                                    <a href="{{ ssi_route('ssi.lot.history', $allocation->lot_number) }}" target="_blank" rel="noopener noreferrer">{{ $allocation->lot_number }}</a>
                                                @else
                                                    Unallocated
                                                @endif
                                            @else
                                                {{ $allocation->lot_number ?: 'Unallocated' }}
                                            @endcan
                                            ({{ $allocation->quantity - $allocation->qty_returned }})
                                        </div>
                                    @empty
                                        No allocations
                                    @endforelse
                                </td>
                                <td>
                                    @foreach ($issue['allocations'] as $allocation)
                                        <div>{{ $allocation->receipt_date ?: '--' }}</div>
                                    @endforeach
                                </td>
                                <td><ul>@foreach ($issue['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul></td>
                                <td>
                                    <button type="button" class="btn btn-default btn-sm correct-stock-line" data-line-id="{{ $issue['sell_line_id'] }}" aria-label="Correct transfer line {{ $issue['sell_line_id'] }}">
                                        <i class="fa fa-edit" aria-hidden="true"></i> Correct This Line
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @foreach ($stockReview['transfer_issues'] as $issue)
            <p>Selected serial {{ $issue['selected_serial'] ?: '--' }}: source quantity {{ $issue['source_quantity'] }}, matching received quantity {{ $issue['matching_received_quantity'] }}.</p>
        @endforeach
        @foreach ($stockReview['stock_issues'] as $issue)
            <p>Batch #{{ $issue->id }} ({{ $issue->lot_number }}): recorded availability {{ $issue->cached_available }}, history availability {{ $issue->expected_available }}.</p>
        @endforeach
    </section>
@endif
