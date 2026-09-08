@extends('loanmanagement::layouts.app')
@section('title', 'Server & Database Requirements Check')

@section('loan_css')
<style>
    .sys-health-wrapper {
        padding: 24px;
        max-width: 1380px;
        margin: 0 auto;
        font-family: inherit;
        color: #1e293b;
    }
    .sys-health-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e2e8f0;
    }
    .sys-health-title {
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .sys-badge {
        font-size: 13px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 9999px;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .sys-badge-healthy { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .sys-badge-warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
    .sys-badge-critical { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    .sys-stat-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .sys-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 20px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, .03);
    }
    .sys-card-label {
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 6px;
    }
    .sys-card-val {
        font-size: 28px;
        font-weight: 800;
        color: #0f172a;
    }
    .sys-card-val.healthy { color: #16a34a; }
    .sys-card-val.warning { color: #d97706; }
    .sys-card-val.critical { color: #dc2626; }

    .sys-alert-banner {
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }
    .sys-alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .sys-alert-warning { background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; }
    .sys-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }

    .sys-section {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, .03);
        overflow: hidden;
    }
    .sys-section-header {
        background: #f8fafc;
        padding: 14px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .sys-section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .sys-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .sys-table th {
        background: #ffffff;
        text-align: left;
        padding: 12px 18px;
        font-weight: 700;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #e2e8f0;
    }
    .sys-table td {
        padding: 12px 18px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .sys-table tr:last-child td { border-bottom: none; }
    .sys-table tr:hover td { background: #f8fafc; }

    .sys-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 700;
    }
    .sys-status-pass { background: #dcfce7; color: #166534; }
    .sys-status-fail { background: #fee2e2; color: #991b1b; }
    .sys-status-warn { background: #fef9c3; color: #854d0e; }

    .sys-remedy-box {
        background: #0f172a;
        color: #38bdf8;
        padding: 8px 12px;
        border-radius: 8px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 13px;
        display: inline-block;
        margin-top: 4px;
    }
</style>
@endsection

@section('content')
<div class="sys-health-wrapper">
    <div class="sys-health-header">
        <div>
            <h1 class="sys-health-title">
                <span>🛡️</span>
                <span>Server & Database Requirements Check</span>
            </h1>
            <p style="margin: 4px 0 0; font-size: 14px; color: #64748b;">
                ការត្រួតពិនិត្យប្រព័ន្ធ និង មូលដ្ឋានទិន្នន័យពេញលេញ | Checked at: {{ $report['checked_at'] }}
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span class="sys-badge sys-badge-{{ $report['status'] }}">
                {{ strtoupper($report['status']) }} ({{ $report['score'] }}%)
            </span>
            <button onclick="window.location.reload();" class="btn btn-primary btn-sm" style="border-radius: 8px; font-weight: 600;">
                <i class="fa fa-refresh"></i> Re-check / ពិនិត្យឡើងវិញ
            </button>
        </div>
    </div>

    @if ($report['has_critical_errors'])
        <div class="sys-alert-banner sys-alert-danger">
            <div style="font-size: 24px;">⚠️</div>
            <div>
                <h4 style="margin: 0 0 6px; font-weight: 800;">Critical Requirements Missing / ខ្វះតម្រូវការចាំបាច់</h4>
                <p style="margin: 0 0 10px; font-size: 14px;">
                    Some essential system components, database tables, or PHP extensions are not ready. Please review the errors below.
                </p>
                @foreach ($report['alerts'] as $alert)
                    <div style="margin-top: 8px; font-size: 13px;">
                        <strong>• {{ $alert['title_en'] }} / {{ $alert['title_km'] }}:</strong> {{ $alert['message_en'] }}
                        @if (!empty($alert['remedy']))
                            <div><span class="sys-remedy-box">{{ $alert['remedy'] }}</span></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($report['warning_count'] > 0)
        <div class="sys-alert-banner sys-alert-warning">
            <div style="font-size: 24px;">🔔</div>
            <div>
                <h4 style="margin: 0 0 6px; font-weight: 800;">System Warnings / ការព្រមានប្រព័ន្ធ</h4>
                <p style="margin: 0; font-size: 14px;">
                    All core requirements are met, but some optional configurations or reference data need attention.
                </p>
            </div>
        </div>
    @else
        <div class="sys-alert-banner sys-alert-success">
            <div style="font-size: 24px;">✅</div>
            <div>
                <h4 style="margin: 0 0 4px; font-weight: 800;">All Requirements Satisfied / ប្រព័ន្ធដំណើរការបានល្អឥតខ្ចោះ 100%</h4>
                <p style="margin: 0; font-size: 14px;">
                    PHP extensions, storage permissions, encryption keys, database connections, schemas, and seeds are fully verified.
                </p>
            </div>
        </div>
    @endif

    <div class="sys-stat-cards">
        <div class="sys-card">
            <div class="sys-card-label">Health Score / ពិន្ទុសុខភាព</div>
            <div class="sys-card-val {{ $report['status'] }}">{{ $report['score'] }}%</div>
        </div>
        <div class="sys-card">
            <div class="sys-card-label">Passed Checks / ជោគជ័យ</div>
            <div class="sys-card-val healthy">{{ $report['passed_count'] }}</div>
        </div>
        <div class="sys-card">
            <div class="sys-card-label">Warnings / ការព្រមាន</div>
            <div class="sys-card-val warning">{{ $report['warning_count'] }}</div>
        </div>
        <div class="sys-card">
            <div class="sys-card-label">Errors / កំហុស</div>
            <div class="sys-card-val critical">{{ $report['error_count'] }}</div>
        </div>
    </div>

    <!-- Server Runtime Specs -->
    <div class="sys-section">
        <div class="sys-section-header">
            <h3 class="sys-section-title">🖥️ Server Runtime & PHP Environment</h3>
        </div>
        <table class="sys-table">
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Value</th>
                    <th>Recommended</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>PHP Version</strong></td>
                    <td><span class="sys-status-pill sys-status-pass">{{ $report['server_info']['php_version'] }}</span></td>
                    <td>>= 8.1.0</td>
                </tr>
                <tr>
                    <td><strong>Laravel Version</strong></td>
                    <td>{{ $report['server_info']['laravel_version'] }}</td>
                    <td>10.x</td>
                </tr>
                <tr>
                    <td><strong>Operating System</strong></td>
                    <td>{{ $report['server_info']['os'] }}</td>
                    <td>Linux / Windows</td>
                </tr>
                <tr>
                    <td><strong>Memory Limit</strong></td>
                    <td>{{ $report['server_info']['memory_limit'] }}</td>
                    <td>>= 256M</td>
                </tr>
                <tr>
                    <td><strong>Max Execution Time</strong></td>
                    <td>{{ $report['server_info']['max_execution_time'] }}</td>
                    <td>>= 60s</td>
                </tr>
                <tr>
                    <td><strong>Upload Max Filesize</strong></td>
                    <td>{{ $report['server_info']['upload_max_filesize'] }}</td>
                    <td>>= 10M</td>
                </tr>
                <tr>
                    <td><strong>Timezone</strong></td>
                    <td>{{ $report['server_info']['timezone'] }}</td>
                    <td>Asia/Phnom_Penh / Asia/Bangkok</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Categorized Diagnostic Tables -->
    @foreach ($report['categories'] as $key => $category)
        <div class="sys-section">
            <div class="sys-section-header">
                <h3 class="sys-section-title">
                    @if ($key === 'php') 🔌 @elseif ($key === 'storage') 📁 @elseif ($key === 'environment') ⚙️ @elseif ($key === 'database') 🗄️ @elseif ($key === 'schema') 📋 @else 📦 @endif
                    {{ $category['name'] }}
                </h3>
                <span class="sys-status-pill {{ $category['status'] === 'pass' ? 'sys-status-pass' : ($category['status'] === 'warning' ? 'sys-status-warn' : 'sys-status-fail') }}">
                    {{ strtoupper($category['status']) }}
                </span>
            </div>
            <table class="sys-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Check Item</th>
                        <th style="width: 25%;">Requirement</th>
                        <th style="width: 30%;">Current State</th>
                        <th style="width: 15%; text-align: right;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($category['items'] as $item)
                        <tr>
                            <td>
                                <strong>{{ $item['name'] }}</strong>
                                @if (!empty($item['desc']))
                                    <div style="font-size: 12px; color: #64748b;">{{ $item['desc'] }}</div>
                                @endif
                            </td>
                            <td>{{ $item['required'] ?? '-' }}</td>
                            <td>
                                <span>{{ $item['current'] ?? '-' }}</span>
                                @if ($item['status'] === 'fail' && !empty($item['remedy']))
                                    <div><span class="sys-remedy-box">{{ $item['remedy'] }}</span></div>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <span class="sys-status-pill {{ $item['status'] === 'pass' ? 'sys-status-pass' : ($item['status'] === 'warning' ? 'sys-status-warn' : 'sys-status-fail') }}">
                                    {{ strtoupper($item['status']) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@endsection
