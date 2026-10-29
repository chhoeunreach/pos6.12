<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ABA PayWay & Bakong KHQR Checkout - {{ $transaction->merchant_ref_no }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body {
            font-family: 'Kantumruy Pro', -apple-system, sans-serif;
            background: #0f172a;
            color: #1e293b;
            margin: 0;
            padding: 20px 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .khqr-card {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            position: relative;
        }
        .khqr-header {
            background: #d61827; /* Bakong Red */
            color: #ffffff;
            padding: 20px 24px;
            text-align: center;
            position: relative;
        }
        .khqr-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .khqr-header p {
            margin: 4px 0 0 0;
            font-size: 12px;
            opacity: 0.9;
        }
        .khqr-body {
            padding: 28px 24px;
            text-align: center;
        }
        .merchant-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .txn-ref {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 16px;
        }
        .amount-display {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 20px;
        }
        .qr-frame {
            background: #ffffff;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 16px;
            display: inline-block;
            margin-bottom: 20px;
            position: relative;
        }
        .qr-frame img {
            width: 210px;
            height: 210px;
            display: block;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-approved { background: #dcfce7; color: #15803d; font-size: 14px; }
        .countdown {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .demo-bar {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 16px 24px;
            border-radius: 0 0 24px 24px;
            text-align: center;
        }
        .btn-simulate {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-simulate:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

<div class="khqr-card">
    <div class="khqr-header">
        <h2><i class="fa fa-qrcode"></i> KHQR PAYMENT</h2>
        <p>Bakong & ABA PayWay Digital Checkout</p>
    </div>

    <div class="khqr-body">
        <div class="merchant-name">{{ session('business.name', 'NTL LOAN MANAGEMENT') }}</div>
        <div class="txn-ref">REF: {{ $transaction->merchant_ref_no }}</div>

        <div class="amount-display">
            {{ $transaction->currency === 'KHR' ? '៛' : '$' }}{{ number_format($transaction->amount, 2) }}
        </div>

        @if($transaction->status === 'approved')
            <div class="status-badge status-approved">
                <i class="fa fa-check-circle"></i> PAYMENT VERIFIED & COMPLETED!
            </div>
            <p style="font-size:13px;color:#64748b;margin-bottom:20px;">
                Transaction has been successfully settled and credited to loan account.
            </p>
            @if($transaction->loan_id)
                <a href="{{ route('loan-management.loans.view', $transaction->loan_id) }}" style="display:inline-block;background:#15803d;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-weight:700;font-size:13px;">
                    <i class="fa fa-arrow-left"></i> Return to Loan Account
                </a>
            @else
                <a href="{{ route('loan-management.payments.index') }}" style="display:inline-block;background:#15803d;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-weight:700;font-size:13px;">
                    <i class="fa fa-list"></i> View Payments List
                </a>
            @endif
        @else
            <div class="status-badge status-pending" id="statusBadge">
                <i class="fa fa-spinner fa-spin"></i> Awaiting Payment...
            </div>

            <!-- DYNAMIC QR CODE -->
            <div class="qr-frame">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode($khqrPayload) }}" alt="Bakong KHQR">
            </div>

            <div class="countdown">
                <i class="fa fa-clock-o"></i> QR Code expires in: <strong id="timer">14:59</strong>
            </div>

            <p style="font-size:11px;color:#94a3b8;margin:0;">
                Scan with any mobile banking app in Cambodia:<br>
                <strong>ABA Mobile, Bakong, ACLEDA, Canadia, Wing, etc.</strong>
            </p>
        @endif
    </div>

    @if($transaction->status !== 'approved')
        <div class="demo-bar">
            <form method="POST" action="{{ route('loan-management.payway.simulate', $transaction->merchant_ref_no) }}">
                @csrf
                <button type="submit" class="btn-simulate">
                    <i class="fa fa-magic"></i> Simulate Payment Success (Demo Mode)
                </button>
            </form>
            <div style="margin-top:8px;font-size:11px;color:#94a3b8;">
                Click above to test instant settlement and loan balance deduction.
            </div>
        </div>
    @endif
</div>

<script>
@if($transaction->status !== 'approved')
// Auto-polling status check every 3 seconds
var checkTimer = setInterval(function () {
    fetch('{{ route('loan-management.payway.check-status') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ merchant_ref_no: '{{ $transaction->merchant_ref_no }}' })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success && data.status === 'approved') {
            clearInterval(checkTimer);
            window.location.reload();
        }
    })
    .catch(function(err) {});
}, 3000);

// 15-minute countdown display
var seconds = 899;
var timerEl = document.getElementById('timer');
setInterval(function () {
    if (seconds <= 0) return;
    seconds--;
    var m = Math.floor(seconds / 60);
    var s = seconds % 60;
    timerEl.innerText = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
}, 1000);
@endif
</script>

</body>
</html>
