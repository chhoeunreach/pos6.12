@php
    $cards = [
        ['key' => 'active_loans', 'label' => 'Active Loans', 'icon' => 'fa fa-credit-card', 'class' => 'bg-aqua'],
        ['key' => 'today_collection', 'label' => 'Today Collection', 'icon' => 'fa fa-dollar', 'class' => 'bg-green'],
        ['key' => 'overdue_amount', 'label' => 'Overdue Amount', 'icon' => 'fa fa-exclamation-triangle', 'class' => 'bg-red'],
        ['key' => 'late_customers', 'label' => 'Late Customers', 'icon' => 'fa fa-user-times', 'class' => 'bg-yellow'],
        ['key' => 'monthly_income', 'label' => 'Monthly Income', 'icon' => 'fa fa-line-chart', 'class' => 'bg-purple'],
        ['key' => 'pending_visits', 'label' => 'Pending Visits', 'icon' => 'fa fa-street-view', 'class' => 'bg-navy'],
        ['key' => 'unread_chats', 'label' => 'Unread Chats', 'icon' => 'fa fa-comments', 'class' => 'bg-orange'],
        ['key' => 'active_collectors', 'label' => 'Active Collectors', 'icon' => 'fa fa-map-marker', 'class' => 'bg-teal'],
    ];
@endphp

<section class="content-header">
    <h1>Installment / Loan Dashboard</h1>
</section>

<section class="content">
    <div class="row">
        @foreach($cards as $card)
            @php $val = $quickCards[$card['key']] ?? 0; @endphp
            <div class="col-md-3 col-sm-6 col-xs-12">
                <div class="info-box">
                    <span class="info-box-icon {{ $card['class'] }}"><i class="{{ $card['icon'] }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $card['label'] }}</span>
                        <span class="info-box-number">
                            {{ in_array($card['key'], ['today_collection', 'overdue_amount', 'monthly_income']) ? number_format((float) $val, 2) : (int) $val }}
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">Mobile App Navigation Mapping</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-6">
                    <h4>Staff App</h4>
                    <ul>
                        <li>Dashboard</li>
                        <li>Customers</li>
                        <li>Overdue</li>
                        <li>Payments</li>
                        <li>Visits</li>
                        <li>Chat</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h4>Customer App</h4>
                    <ul>
                        <li>Home</li>
                        <li>My Loans</li>
                        <li>Pay</li>
                        <li>Chat</li>
                        <li>Profile</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

