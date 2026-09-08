@extends('loanmanagement::layouts.app')
@section('title', 'Telegram Bot Settings')

@section('content_body')
<section class="content-header">
    <h1>Telegram Bot <small>Customer chat integration</small></h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title"><i class="fa fa-telegram"></i> Bot Credentials</h3>
        </div>
        <div class="box-body">
            <p class="text-muted">
                Create a bot with <a href="https://t.me/BotFather" target="_blank">@BotFather</a> on Telegram, then paste its token here.
                This bot is used only for the customer chat feature - it's separate from any other Telegram bot your business uses.
            </p>

            <form method="POST" action="{{ route('loan-management.settings.telegram.update') }}" id="telegramSettingsForm">
                @csrf
                <div class="form-group">
                    <label>Bot Token</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="bot_token" id="botTokenInput"
                               placeholder="{{ !empty($settings['bot_token']) ? 'Saved - leave blank to keep current token' : 'e.g. 123456789:AAE...' }}"
                               autocomplete="off">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="toggleTokenVisibility" title="Show/Hide"><i class="fa fa-eye"></i></button>
                        </span>
                    </div>
                    @if(!empty($settings['bot_token']))
                        <p class="help-block"><i class="fa fa-check-circle text-green"></i> A bot token is currently saved.</p>
                    @endif
                </div>

                <div class="form-group">
                    <label>Bot Username</label>
                    <div class="input-group">
                        <span class="input-group-addon">@</span>
                        <input type="text" class="form-control" name="bot_username" value="{{ $settings['bot_username'] }}" placeholder="e.g. kypaymentbot">
                    </div>
                    <p class="help-block">Used to build the connect link customers tap to link their Telegram account (t.me/&lt;username&gt;).</p>
                </div>

                <div class="form-group">
                    <label>Webhook Secret</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="webhook_secret" id="webhookSecretInput" value="{{ $settings['webhook_secret'] }}" placeholder="Random secret string">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="btnGenerateSecret">Generate</button>
                        </span>
                    </div>
                    <p class="help-block">Verifies that incoming webhook calls really come from Telegram. Re-register the webhook after changing this.</p>
                </div>

                <div class="form-group">
                    <label>Connect Link Validity (minutes)</label>
                    <input type="number" class="form-control" style="max-width:160px" name="link_ttl_minutes" value="{{ $settings['link_ttl_minutes'] }}" min="1" max="1440">
                    <p class="help-block">How long a "Connect Telegram" link generated on a customer page stays valid before it expires.</p>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Settings</button>
            </form>
        </div>
    </div>

    <div class="box box-default">
        <div class="box-header">
            <h3 class="box-title"><i class="fa fa-plug"></i> Connection &amp; Webhook</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Test the saved bot token:</strong></p>
                    <button type="button" class="btn btn-default" id="btnTestConnection"><i class="fa fa-refresh"></i> Test Connection</button>
                    <div id="testConnectionResult" style="margin-top:10px"></div>
                </div>
                <div class="col-md-6">
                    <p><strong>Webhook URL:</strong></p>
                    <input type="text" class="form-control" value="{{ $webhookUrl }}" readonly style="margin-bottom:10px">
                    <button type="button" class="btn btn-default" id="btnRegisterWebhook"><i class="fa fa-link"></i> Register Webhook Now</button>
                    <div id="registerWebhookResult" style="margin-top:10px"></div>
                    @if(!empty($settings['webhook_registered_at']))
                        <p class="help-block" style="margin-top:8px">Last registered: {{ $settings['webhook_registered_at'] }} @if(!empty($settings['webhook_url']))({{ $settings['webhook_url'] }})@endif</p>
                    @endif
                    <p class="help-block">
                        Telegram must be able to reach this URL over public HTTPS. If you're testing locally, expose your app first
                        (e.g. with a tunnel like ngrok/cloudflared) before registering.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('loan_js')
<script>
(function($){
    var csrf = '{{ csrf_token() }}';

    function apiPost(url, data){
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify(data || {})
        }).then(function(r){ return r.json().then(function(body){ return {status: r.status, body: body}; }); });
    }

    $('#toggleTokenVisibility').on('click', function(){
        var input = document.getElementById('botTokenInput');
        input.type = input.type === 'password' ? 'text' : 'password';
    });

    $('#btnGenerateSecret').on('click', function(){
        apiPost('{{ route("loan-management.settings.telegram.secret") }}', {}).then(function(r){
            if (r.body && r.body.secret) {
                $('#webhookSecretInput').val(r.body.secret);
            }
        });
    });

    $('#btnTestConnection').on('click', function(){
        var $btn = $(this).prop('disabled', true);
        var $result = $('#testConnectionResult').html('<span class="text-muted">Testing...</span>');
        apiPost('{{ route("loan-management.settings.telegram.test") }}', {bot_token: $('#botTokenInput').val()}).then(function(r){
            if (r.body && r.body.success) {
                $result.html('<span class="text-green"><i class="fa fa-check-circle"></i> Connected as <strong>' + (r.body.bot_name || '') + '</strong> (@' + (r.body.bot_username || '') + ')</span>');
            } else {
                $result.html('<span class="text-red"><i class="fa fa-times-circle"></i> ' + ((r.body && r.body.message) || 'Connection failed.') + '</span>');
            }
        }).finally(function(){ $btn.prop('disabled', false); });
    });

    $('#btnRegisterWebhook').on('click', function(){
        var $btn = $(this).prop('disabled', true);
        var $result = $('#registerWebhookResult').html('<span class="text-muted">Registering...</span>');
        apiPost('{{ route("loan-management.settings.telegram.webhook") }}', {}).then(function(r){
            if (r.body && r.body.success) {
                $result.html('<span class="text-green"><i class="fa fa-check-circle"></i> ' + r.body.message + '</span>');
            } else {
                $result.html('<span class="text-red"><i class="fa fa-times-circle"></i> ' + ((r.body && r.body.message) || 'Registration failed.') + '</span>');
            }
        }).finally(function(){ $btn.prop('disabled', false); });
    });
})(jQuery);
</script>
@endsection
