@extends('layouts.app')

@section('title', 'Telegram Settings')
@section('pageName', 'Telegram Settings')

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif

    <div class="card card-flush shadow-sm">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold">Telegram Bot Settings</h3>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('telegram.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="form-label">Bot Token</label>
                        <textarea name="bot_token" rows="3" class="form-control form-control-solid">{{ old('bot_token', $settings->bot_token) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bot Username</label>
                        <input type="text" name="bot_username" class="form-control form-control-solid" value="{{ old('bot_username', $settings->bot_username) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Webhook Secret</label>
                        <input type="text" name="webhook_secret" class="form-control form-control-solid" value="{{ old('webhook_secret', $settings->webhook_secret) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Parse Mode</label>
                        <select name="parse_mode" class="form-select form-select-solid">
                            <option value="HTML" @selected(old('parse_mode', $settings->parse_mode) === 'HTML')>HTML</option>
                            <option value="Markdown" @selected(old('parse_mode', $settings->parse_mode) === 'Markdown')>Markdown</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" value="1" id="telegram_enabled" name="is_enabled" @checked(old('is_enabled', $settings->is_enabled))>
                            <label class="form-check-label" for="telegram_enabled">Enable Telegram integration</label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="alert alert-light-info mb-0">
                            Webhook URL:
                            <code>{{ route('telegram.webhook', ['secret' => $settings->webhook_secret ?: 'set-secret']) }}</code>
                        </div>
                    </div>
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
