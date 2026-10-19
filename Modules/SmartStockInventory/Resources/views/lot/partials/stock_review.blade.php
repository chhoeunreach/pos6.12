@if ($stockReview['balances']->isNotEmpty() || $stockReview['allocations']->isNotEmpty())
    <section class="alert alert-warning" role="alert">
        <h4><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Stock Reconciliation Required</h4>
        <p>History quantity is not confirmed available stock. Stock counters or serial allocations disagree; verify the related records before selling or transferring this lot.</p>
        @foreach ($stockReview['balances'] as $balance)
            <p>Batch #{{ $balance->id }}: recorded availability {{ $balance->recorded_available }}, history availability {{ $balance->history_available }}.</p>
        @endforeach
        @if ($stockReview['allocations']->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead style="color:#333"><tr><th>Reference</th><th>Movement Date</th><th>Selected Serial</th><th>Deducted Serial</th><th>Receipt Date</th><th>Quantity</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach ($stockReview['allocations'] as $row)
                            <tr>
                                <td>
                                    @if ($row->review_url)
                                        <a href="{{ $row->review_url }}" target="_blank" rel="noopener noreferrer">{{ $row->reference }}</a>
                                    @else
                                        {{ $row->reference }}
                                    @endif
                                </td>
                                <td>{{ $row->transaction_date }}</td>
                                <td>{{ $row->selected_serial }}</td>
                                <td>{{ $row->allocated_serial }}</td>
                                <td>{{ $row->receipt_date }}</td>
                                <td>{{ $row->quantity - $row->qty_returned }}</td>
                                <td>
                                    @if (!empty($row->full_repair_url))
                                        <a class="btn btn-primary btn-sm" href="{{ $row->full_repair_url }}">Full repair</a>
                                    @elseif (!empty($row->deducted_serial_fix_url))
                                        <form method="post" action="{{ $row->deducted_serial_fix_url }}" style="display:inline-block">
                                            @csrf
                                            <input type="hidden" name="sell_line_id" value="{{ $row->sell_line_id }}">
                                            <button type="submit" class="btn btn-primary btn-sm">Fix Deducted Serial</button>
                                        </form>
                                    @endif
                                    @if ($row->correction_url)
                                        <a class="btn btn-default btn-sm" href="{{ $row->correction_url }}"><i class="fa fa-edit" aria-hidden="true"></i> Correct This Line</a>
                                    @else
                                        --
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endif
