@extends('social::layouts.master')
@section('page_title', 'Social Platform — API Control & Settings')
@section('module_content')
<div class="row">
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">API & Platform Controls (<code>social.social_settings</code>)</h3>
            </div>
            <div class="box-body">
                <form method="post" action="{{ route('social.admin.settings.update') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Platform Name</label>
                            <input type="text" name="platform_name" class="form-control" required value="{{ old('platform_name', $settings['platform_name']) }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Platform Mode</label>
                            <select name="mode" class="form-control">
                                <option value="sample" @selected(($settings['mode'] ?? '') === 'sample')>Sample Mode (POS Sample Social API)</option>
                                <option value="live" @selected(($settings['mode'] ?? '') === 'live')>Live Mode (Full Native Social DB)</option>
                                <option value="external" @selected(($settings['mode'] ?? '') === 'external')>External Standalone Social Platform</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label><input type="checkbox" name="api_enabled" value="1" @checked(!empty($settings['api_enabled']))> Enable Social Platform API</label>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><input type="checkbox" name="public_feed_enabled" value="1" @checked(!empty($settings['public_feed_enabled']))> Allow Public Guest Browsing</label>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><input type="checkbox" name="customer_posting_enabled" value="1" @checked(!empty($settings['customer_posting_enabled']))> Allow Customer Posts</label>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><input type="checkbox" name="allow_video_transcode" value="1" @checked(!empty($settings['allow_video_transcode']))> Enable HLS Video Transcoding</label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Posts Per Page</label>
                            <input type="number" name="per_page_limit" min="5" max="40" class="form-control" value="{{ old('per_page_limit', $settings['per_page_limit']) }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Cache TTL (sec)</label>
                            <input type="number" name="cache_ttl_seconds" min="0" max="3600" class="form-control" value="{{ old('cache_ttl_seconds', $settings['cache_ttl_seconds']) }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Default Lang</label>
                            <input type="text" name="default_language" class="form-control" value="{{ old('default_language', $settings['default_language']) }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Country</label>
                            <input type="text" name="default_country_code" class="form-control" value="{{ old('default_country_code', $settings['default_country_code']) }}">
                        </div>
                    </div>

                    <hr>
                    <h4>Future Standalone Social Platform Bridge</h4>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>External Social API Base URL</label>
                            <input type="url" name="external_api_base_url" class="form-control" placeholder="https://social.kneayerng.com/api" value="{{ old('external_api_base_url', $settings['external_api_base_url']) }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>External API Key / Secret (leave blank to keep current)</label>
                            <input type="password" name="external_api_key" class="form-control" placeholder="{{ !empty($settings['external_api_key']) ? '•••••••• (saved)' : 'Optional secret key' }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Admin Notes</label>
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $settings['notes']) }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save API Settings</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="box box-{{ $dbStatus['connected'] ? 'success' : 'danger' }}">
            <div class="box-header with-border">
                <h3 class="box-title">Database & Endpoints</h3>
            </div>
            <div class="box-body">
                <p>
                    <strong>Connection:</strong> <code>mysql_feed</code><br>
                    <strong>Database:</strong> <code>{{ $dbStatus['database'] ?: 'N/A' }}</code><br>
                    <strong>Status:</strong>
                    <span class="label label-{{ $dbStatus['connected'] ? 'success' : 'danger' }}">
                        {{ $dbStatus['connected'] ? 'CONNECTED' : 'DISCONNECTED' }}
                    </span><br>
                    <strong>Posts Stored:</strong> {{ $dbStatus['posts_count'] }}
                </p>
                <hr>
                <p><strong>Public API Endpoints (Mobile & Web):</strong></p>
                <ul style="padding-left:18px;">
                    <li><code>GET /api/social/health</code></li>
                    <li><code>GET /api/social/public/feed</code></li>
                    <li><code>GET /api/social/public/feed/{post}</code></li>
                    <li><code>GET /api/social/public/feed/{post}/comments</code></li>
                    <li><code>POST /api/social/public/feed/{post}/view</code></li>
                </ul>
                <p><strong>Customer Authed Endpoints:</strong></p>
                <ul style="padding-left:18px;">
                    <li><code>POST /api/social/customer/feed</code></li>
                    <li><code>POST /api/social/customer/feed/{post}/like</code></li>
                    <li><code>POST /api/social/customer/feed/{post}/comments</code></li>
                    <li><code>POST /api/social/customer/feed/{post}/share</code></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
