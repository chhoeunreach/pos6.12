<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Stock Transfer #{{ $ref_no ?? $transfer_id }}</title></head>
<body style="font-family: Arial, sans-serif; font-size: 12px;">
    <h2>Stock Transfer</h2>
    <table style="width:100%; border-collapse: collapse;">
        <tr><td style="padding:4px; font-weight:bold;">Reference No:</td><td style="padding:4px;">{{ $ref_no ?? 'N/A' }}</td></tr>
        <tr><td style="padding:4px; font-weight:bold;">From:</td><td style="padding:4px;">{{ $from_location ?? 'N/A' }}</td></tr>
        <tr><td style="padding:4px; font-weight:bold;">To:</td><td style="padding:4px;">{{ $to_location ?? 'N/A' }}</td></tr>
        <tr><td style="padding:4px; font-weight:bold;">Date:</td><td style="padding:4px;">{{ $date ?? date('Y-m-d') }}</td></tr>
        <tr><td style="padding:4px; font-weight:bold;">Status:</td><td style="padding:4px;">{{ $status ?? 'N/A' }}</td></tr>
        <tr><td style="padding:4px; font-weight:bold;">Total Quantity:</td><td style="padding:4px;">{{ $total_qty ?? 'N/A' }}</td></tr>
        <tr><td style="padding:4px; font-weight:bold;">Created By:</td><td style="padding:4px;">{{ $user ?? 'N/A' }}</td></tr>
    </table>
</body>
</html>
