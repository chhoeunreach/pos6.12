@php
    $cssVer = config('loanmanagement.version', '1.0.0');
@endphp
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
<link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css"></noscript>
<link rel="preload" as="style" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap.min.css" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap.min.css"></noscript>
<style>
    body { font-family: Arial, "Kantumruy Pro", sans-serif; }
    .box { background: #fff; border: 1px solid #dbe3ef; border-radius: 8px; margin-bottom: 16px; }
    .box-header { padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
    .box-title { margin: 0; font-size: 18px; font-weight: 700; }
    .box-body { padding: 16px; }
    .text-muted { color: #64748b; }
</style>
