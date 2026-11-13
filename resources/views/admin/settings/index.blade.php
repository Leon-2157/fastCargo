@extends('layouts.dashboard')

@section('title', 'Platform Settings & System Integrations')

@push('styles')
<style>
    .json-viewer-box {
        background: #0f172a;
        color: #38bdf8;
        font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, monospace;
        font-size: 11.5px;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #1e293b;
        max-height: 240px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }
    .scenario-btn-group input:checked + label {
        font-weight: 700;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
    }
</style>
@endpush

@section('content')
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-dark mb-0 fw-bold">Platform Settings &amp; System Integrations</h3>
            <p class="text-muted small mb-0">Manage AI Intelligence Engines, WhatsApp Go Gateway pairing, and Resend SMTP
                email delivery.</p>
        </div>
        <div>
            <span class="badge bg-light text-secondary border px-3 py-2 small">
                <i class="fas fa-network-wired me-1"></i> Operator: <strong>{{ config('app.domains.operator') }}</strong>
            </span>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Validation failed:</strong> {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- AI Intelligence Engine Configuration -->
        <div class="col-lg-6 mb-4 align-self-start">
            <div class="card shadow border-start-primary">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-robot me-1"></i> AI Intelligence
                    </h6>
                    @php
                        $currentAi = $aiConfig['active_provider'] ?? 'custom';
                        $isGemini = in_array($currentAi, ['gemini'], true);
                    @endphp
                    <span id="activeAiBadge" class="badge {{ $isGemini ? 'bg-info text-dark' : 'bg-primary' }} px-3 py-2">
                        Active: {{ $aiConfig['active_label'] ?? strtoupper($currentAi) }}
                    </span>
                </div>
                <div class="card-body">
                    <form id="aiSwitchForm" method="POST" action="{{ route('admin.settings.ai.switch', [], false) }}"
                        class="mb-4">
                        @csrf
                        <label class="form-label small fw-bold text-muted" for="ai_provider_select">Select Active AI
                            Provider Engine</label>
                        <div class="input-group">
                            <select id="ai_provider_select" name="ai_provider" class="form-select">
                                <option value="gemini" {{ $isGemini ? 'selected' : '' }}>
                                    Google Gemini API
                                </option>
                                <option value="custom" {{ !$isGemini ? 'selected' : '' }}>
                                    OpenAI Compatible
                                </option>
                            </select>
                            <button id="btnSwitchEngine" type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-1"></i> Switch Engine
                            </button>
                        </div>
                    </form>

                    <form id="formAiSettings" method="POST" action="{{ route('admin.settings.update', [], false) }}">
                        @csrf
                        @method('PUT')

                        <!-- SECTION GOOGLE GEMINI (Gambar 3) -->
                        <div id="aiSectionGemini" class="{{ $isGemini ? '' : 'd-none' }}">
                            <h6 class="small fw-bold text-uppercase text-info mb-2"><i class="fab fa-google me-1"></i>
                                Google Gemini API</h6>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1" for="gemini_api_key">Gemini API
                                    Key</label>
                                <input id="gemini_api_key" type="password" name="gemini_api_key"
                                    value="{{ old('gemini_api_key', $aiConfig['gemini_api_key']) }}"
                                    class="form-control ai-dirty-tracker" autocomplete="new-password"
                                    placeholder="Enter Google Gemini API key">
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted" for="gemini_model">Gemini Model
                                    Identifier</label>
                                <input id="gemini_model" type="text" name="gemini_model"
                                    value="{{ old('gemini_model', $aiConfig['gemini_model']) }}"
                                    class="form-control ai-dirty-tracker" placeholder="gemini-2.0-flash">
                            </div>
                        </div>

                        <!-- SECTION CUSTOM / OPENAI GATEWAY (Gambar 2 & Upload Image) -->
                        <div id="aiSectionCustom" class="{{ $isGemini ? 'd-none' : '' }}">
                            <h6 class="small fw-bold text-uppercase text-primary mb-2"><i
                                    class="fas fa-server me-1"></i>OpenAI Compatible</h6>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted" for="custom_ai_provider_name">Provider
                                    Name</label>
                                <input id="custom_ai_provider_name" type="text" name="custom_ai_provider_name"
                                    value="{{ old('custom_ai_provider_name', $aiConfig['custom_ai_provider_name']) }}"
                                    class="form-control ai-dirty-tracker"
                                    placeholder="e.g. OpenAI Official, Local Ollama, Custom Gateway">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted" for="custom_ai_base_url">Base URL</label>
                                <input id="custom_ai_base_url" type="url" name="custom_ai_base_url"
                                    value="{{ old('custom_ai_base_url', $aiConfig['custom_ai_base_url']) }}"
                                    class="form-control ai-dirty-tracker"
                                    placeholder="http://localhost:20128/v1 or https://api.openai.com/v1">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1" for="custom_ai_api_key">API
                                    Key</label>
                                <input id="custom_ai_api_key" type="password" name="custom_ai_api_key"
                                    value="{{ old('custom_ai_api_key', $aiConfig['custom_ai_api_key']) }}"
                                    class="form-control ai-dirty-tracker" autocomplete="new-password"
                                    placeholder="Enter API Key">
                            </div>

                            <hr class="my-3">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted" for="custom_ai_model">Model
                                    Name</label>
                                <input id="custom_ai_model" type="text" name="custom_ai_model"
                                    value="{{ old('custom_ai_model', $aiConfig['custom_ai_model']) }}"
                                    class="form-control ai-dirty-tracker"
                                    placeholder="e.g. ag/gemini-3.8-flash-high, gpt-4o-mini">
                            </div>

                            <!-- 3 Kolom Token Samping-Sampingan Sesuai Permintaan User -->
                            <div class="row g-2 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted" style="font-size: 0.72rem;"
                                        for="custom_ai_max_completion_tokens">Max Completion Token</label>
                                    <input id="custom_ai_max_completion_tokens" type="number"
                                        name="custom_ai_max_completion_tokens"
                                        value="{{ old('custom_ai_max_completion_tokens', $aiConfig['custom_ai_max_completion_tokens']) }}"
                                        class="form-control ai-dirty-tracker" placeholder="4096">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted" style="font-size: 0.72rem;"
                                        for="custom_ai_max_output_tokens">Max Output Token</label>
                                    <input id="custom_ai_max_output_tokens" type="number"
                                        name="custom_ai_max_output_tokens"
                                        value="{{ old('custom_ai_max_output_tokens', $aiConfig['custom_ai_max_output_tokens']) }}"
                                        class="form-control ai-dirty-tracker" placeholder="2048">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted" style="font-size: 0.72rem;"
                                        for="custom_ai_max_tokens">Max Token</label>
                                    <input id="custom_ai_max_tokens" type="number" name="custom_ai_max_tokens"
                                        value="{{ old('custom_ai_max_tokens', $aiConfig['custom_ai_max_tokens']) }}"
                                        class="form-control ai-dirty-tracker" placeholder="8192">
                                </div>
                            </div>
                        </div>

                        <!-- Test Connection Feedback Message -->
                        <div id="aiTestFeedback" class="d-none alert py-2 px-3 small mb-3"></div>

                        <!-- Footer Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <button id="btnTestAiConnection" type="button" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-satellite-dish me-1 text-primary"></i> Test Connection
                            </button>
                            <button id="btnSaveAiSettings" type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- WhatsApp Gateway Station Card -->
        <div class="col-lg-6 mb-4 align-self-start">
            <div class="card shadow border-start-success">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-success"><i class="fab fa-whatsapp me-1"></i> WhatsApp Gateway
                        </h6>
                    <div id="wag-status-badge">
                        @if (!($waConfig['gatewayStatus']['reachable'] ?? false))
                            <span class="badge bg-danger px-2 py-1 small"><i class="fas fa-plug me-1"></i> Offline</span>
                        @elseif($waConfig['isConnected'])
                            <span class="badge bg-success px-2 py-1 small"><i class="fas fa-check-circle me-1"></i>
                                Connected</span>
                        @else
                            <span class="badge bg-warning text-dark px-2 py-1 small"><i class="fas fa-qrcode me-1"></i>
                                Pairing Needed</span>
                        @endif
                    </div>
                </div>

                <div class="card-body p-3">
                    <!-- BARIS ATAS: 2 KOTAK BERSEBELAHAN -->
                    <div class="row g-2 mb-3">
                        <!-- Kotak Kiri: QR Code / Status Perangkat -->
                        <div class="col-sm-6">
                            <div id="wag-left-box"
                                class="border rounded p-2 bg-light d-flex flex-column align-items-center justify-content-center text-center h-100 shadow-sm"
                                style="min-height: 210px;">
                                @if ($waConfig['qrSvg'])
                                    <div class="p-1 bg-white rounded border mb-1 d-flex align-items-center justify-content-center"
                                        style="width: 122px; height: 122px;">
                                        {!! $waConfig['qrSvg'] !!}
                                    </div>
                                    <div class="fw-bold text-dark" style="font-size: 11px;">
                                        <i class="fas fa-qrcode me-1 text-primary"></i> Scan QR WhatsApp
                                    </div>
                                    <div class="text-muted" style="font-size: 9.5px;">Buka WA &gt; Perangkat Tertaut</div>
                                @elseif($waConfig['isConnected'])
                                    <div class="text-success mb-2">
                                        <i class="fab fa-whatsapp fa-3x"></i>
                                    </div>
                                    <span class="badge bg-success px-2 py-1 mb-1 small">
                                        <i class="fas fa-check-circle me-1"></i> Sesi Terhubung
                                    </span>
                                    <div class="small fw-bold text-dark mb-0" style="font-size: 11px;">
                                        {{ !empty($waConfig['gatewayStatus']['user']['name']) ? $waConfig['gatewayStatus']['user']['name'] : (!empty($waConfig['gatewayStatus']['user']['phone']) ? 'WhatsApp (+' . $waConfig['gatewayStatus']['user']['phone'] . ')' : 'Device Connected') }}
                                    </div>
                                    <code class="text-muted"
                                        style="font-size: 10px;">+{{ $waConfig['gatewayStatus']['user']['phone'] ?? '' }}</code>
                                @elseif(!($waConfig['gatewayStatus']['reachable'] ?? false))
                                    <div class="text-danger mb-2">
                                        <i class="fas fa-plug fa-3x"></i>
                                    </div>
                                    <span class="badge bg-danger px-2 py-1 mb-1 small">waG Offline</span>
                                    <div class="text-muted" style="font-size: 10px;">Layanan tidak dapat dijangkau</div>
                                @else
                                    <div class="spinner-border text-warning mb-2" role="status"
                                        style="width: 2rem; height: 2rem;"></div>
                                    <div class="fw-bold text-dark" style="font-size: 11px;">Menunggu Kode QR...</div>
                                @endif
                            </div>
                        </div>

                        <!-- Kotak Kanan: Info Detail Layanan & Sesi + AI Integration -->
                        <div class="col-sm-6 d-flex flex-column justify-content-between">
                            <div id="wag-right-box"
                                class="border rounded p-3 bg-white d-flex flex-column justify-content-between shadow-sm mb-2 flex-grow-1"
                                style="min-height: 140px;">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 10px;">
                                            <i class="fas fa-info-circle me-1 text-info"></i> Informasi Detail
                                        </span>

                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted" style="font-size: 10px;">Status Layanan:</div>
                                        <div id="wag-detail-status"
                                            class="fw-bold small {{ $waConfig['isConnected'] ? 'text-success' : ($waConfig['gatewayStatus']['reachable'] ?? false ? 'text-warning' : 'text-danger') }}">
                                            <i
                                                class="fas {{ $waConfig['isConnected'] ? 'fa-check-circle' : ($waConfig['gatewayStatus']['reachable'] ?? false ? 'fa-hourglass-half' : 'fa-times-circle') }} me-1"></i>
                                            {{ $waConfig['isConnected'] ? 'Online' : ($waConfig['gatewayStatus']['reachable'] ?? false ? 'Menunggu Pairing' : 'Offline / Terputus') }}
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted" style="font-size: 10px;">Nomor Aktif:</div>
                                        <div id="wag-detail-phone" class="fw-bold small text-dark">
                                            @if (!empty($waConfig['gatewayStatus']['user']['phone']))
                                                <code>+{{ $waConfig['gatewayStatus']['user']['phone'] }}</code>
                                            @else
                                                <span class="text-muted fst-italic">Belum Terhubung</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-muted" style="font-size: 10px;">Waktu Pairing:</div>
                                        <div id="wag-detail-paired-at" class="small text-secondary fw-semibold">
                                            <i class="far fa-clock me-1"></i>
                                            {{ $waConfig['pairedAt'] ?? 'Menunggu scan' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-1 mt-1 border-top d-flex justify-content-between align-items-center">
                                    <span class="text-muted" style="font-size: 9px;">Gateway:</span>
                                    <code style="font-size: 9.5px;">{{ $waConfig['gatewayBaseUrl'] }}</code>
                                </div>
                            </div>

                            <!-- Compact AI Integration Box -->
                            <div class="border rounded p-2 bg-white shadow-sm">
                                <form id="formWaAiSettings" method="POST" action="{{ route('admin.settings.update', [], false) }}" class="m-0">
                                    @csrf
                                    @method('PUT')
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0 text-muted fw-bold" style="font-size: 10.5px;" for="wa_ai_provider">
                                            <i class="fas fa-robot me-1 text-primary"></i> AI Integration
                                        </label>
                                        <button type="submit" class="btn btn-primary btn-sm py-0 px-2 fw-semibold" style="font-size: 9.5px; height: 21px; line-height: 20px;">
                                            <i class="fas fa-save me-1"></i> Simpan
                                        </button>
                                    </div>
                                    <select id="wa_ai_provider" name="wa_ai_provider" class="form-select form-select-sm" style="font-size: 11px;">
                                        <option value="default" {{ old('wa_ai_provider', $waAiProvider ?? 'default') === 'default' ? 'selected' : '' }}>
                                            Ikuti AI Global (Active: {{ $aiConfig['active_label'] }})
                                        </option>
                                        <option value="gemini" {{ old('wa_ai_provider', $waAiProvider ?? 'default') === 'gemini' ? 'selected' : '' }}>
                                            Gemini 2.0 Flash
                                        </option>
                                        <option value="custom" {{ old('wa_ai_provider', $waAiProvider ?? 'default') === 'custom' ? 'selected' : '' }}>
                                            {{ $aiConfig['custom_ai_provider_name'] ?? 'Custom AI' }}
                                        </option>
                                        <option value="disabled" {{ old('wa_ai_provider', $waAiProvider ?? 'default') === 'disabled' ? 'selected' : '' }}>
                                            Nonaktifkan AI
                                        </option>
                                    </select>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- BARIS BAWAH: KOTAK PERSEGI PANJANG (TEST DISPATCH & RESET) -->
                    <div class="border rounded p-3 bg-light shadow-sm">
                        <form id="formWaTestDirect" method="POST" action="{{ route('admin.wa.test', [], false) }}">
                            @csrf
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-uppercase text-muted" style="font-size: 11px;">
                                    <i class="fas fa-paper-plane me-1 text-success"></i> Test Send Message
                                </span>
                            </div>

                            <div id="waTestAlertBox" class="alert py-1 px-2 small mb-2 d-none" role="alert"></div>

                            <div class="row g-2 mb-2">
                                <div class="col-sm-5">
                                    <label class="form-label mb-1 text-muted" style="font-size: 10px;"
                                        for="wa_direct_phone">Nomor Tujuan</label>
                                    <input id="wa_direct_phone" type="text" name="phone"
                                        value="{{ old('phone', '') }}" class="form-control form-control-sm"
                                        placeholder="08123456789 atau +628123456789" required>
                                </div>
                                <div class="col-sm-7">
                                    <label class="form-label mb-1 text-muted" style="font-size: 10px;"
                                        for="wa_direct_message">Pesan Notifikasi</label>
                                    <textarea id="wa_direct_message" name="message" rows="2" class="form-control form-control-sm"
                                        placeholder="Tulis pesan uji coba notifikasi WhatsApp di sini..." required>{{ old('message', '') }}</textarea>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <button id="btnWaTestSubmit" type="submit" class="btn btn-success btn-sm px-3">
                                    <i class="fas fa-paper-plane me-1"></i> Send Test Message
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                    onclick="document.getElementById('formWaResetDirect').submit();">
                                    <i class="fas fa-redo me-1"></i> Reset Session
                                </button>
                            </div>
                        </form>
                        <form id="formWaResetDirect" method="POST" action="{{ route('admin.wa.reset', [], false) }}"
                            class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Delivery Engine (Resend / SMTP) & Live Test -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow border-start-success">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-envelope me-1"></i> Email Delivery
                        </h6>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span id="badge-mail-driver" class="badge bg-secondary">Driver:
                            {{ strtoupper($mailConfig['mail_mailer']) }}</span>
                        @if ($mailConfig['mail_mailer'] === 'log')
                            <span id="badge-mail-status" class="badge bg-info text-dark">
                                <i class="fas fa-file-alt me-1"></i> Local Log Active
                            </span>
                        @else
                            <span id="badge-mail-status"
                                class="badge {{ $mailConfig['is_password_configured'] ? 'bg-success' : 'bg-danger' }}">
                                <i
                                    class="fas {{ $mailConfig['is_password_configured'] ? 'fa-check-circle' : 'fa-exclamation-triangle' }} me-1"></i>
                                {{ $mailConfig['is_password_configured'] ? 'API Key Configured' : 'API Key Not Set' }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <!-- Configuration Form (Left Column) -->
                        <div class="col-lg-7 border-end pe-lg-4">
                            <form method="POST" action="{{ route('admin.settings.update', [], false) }}">
                                @csrf
                                @method('PUT')

                                <!-- Mail Driver & AI Integration Selection Row -->
                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1" for="mail_mailer">
                                            <i class="fas fa-paper-plane me-1 text-primary"></i> Mail Delivery
                                        </label>
                                        <select id="mail_mailer" name="mail_mailer" class="form-select">
                                            <option value="smtp"
                                                {{ old('mail_mailer', $mailConfig['mail_mailer']) === 'smtp' ? 'selected' : '' }}>
                                                SMTP
                                            </option>
                                            <option value="log"
                                                {{ old('mail_mailer', $mailConfig['mail_mailer']) === 'log' ? 'selected' : '' }}>
                                                Local Log
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1" for="mail_ai_provider">
                                            <i class="fas fa-robot me-1 text-primary"></i> AI Integration
                                        </label>
                                        <select id="mail_ai_provider" name="mail_ai_provider" class="form-select">
                                            <option value="default" {{ old('mail_ai_provider', $mailAiProvider ?? 'default') === 'default' ? 'selected' : '' }}>
                                                Ikuti AI Global (Active: {{ $aiConfig['active_label'] }})
                                            </option>
                                            <option value="gemini" {{ old('mail_ai_provider', $mailAiProvider ?? 'default') === 'gemini' ? 'selected' : '' }}>
                                                Gemini 2.0 Flash
                                            </option>
                                            <option value="custom" {{ old('mail_ai_provider', $mailAiProvider ?? 'default') === 'custom' ? 'selected' : '' }}>
                                                {{ $aiConfig['custom_ai_provider_name'] ?? 'Custom AI' }}
                                            </option>
                                            <option value="disabled" {{ old('mail_ai_provider', $mailAiProvider ?? 'default') === 'disabled' ? 'selected' : '' }}>
                                                Nonaktifkan AI
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Group 1: Server Connection (Hidden when Local Log) -->
                                <div id="smtpServerGroup"
                                    class="border rounded p-3 mb-3 bg-light shadow-sm {{ $mailConfig['mail_mailer'] === 'log' ? 'd-none' : '' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="small fw-bold text-uppercase text-dark" style="font-size: 11px;">
                                            <i class="fas fa-server me-1 text-primary"></i> 1. Server Connection
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2"
                                            id="btn-preset-resend" style="font-size: 12px;">
                                            <i class="fas fa-magic me-1"></i> Auto fill
                                        </button>
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-md-5">
                                            <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                                for="mail_host">SMTP Host</label>
                                            <input id="mail_host" type="text" name="mail_host"
                                                value="{{ old('mail_host', $mailConfig['mail_host']) }}"
                                                class="form-control form-control-sm" placeholder="smtp.resend.com">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                                for="mail_port">Port</label>
                                            <input id="mail_port" type="number" name="mail_port"
                                                value="{{ old('mail_port', $mailConfig['mail_port']) }}"
                                                class="form-control form-control-sm" placeholder="465">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                                for="mail_encryption">Encryption</label>
                                            <select id="mail_encryption" name="mail_encryption"
                                                class="form-select form-select-sm">
                                                <option value="tls"
                                                    {{ $mailConfig['mail_encryption'] === 'tls' ? 'selected' : '' }}>TLS /
                                                    STARTTLS (587)</option>
                                                <option value="ssl"
                                                    {{ $mailConfig['mail_encryption'] === 'ssl' ? 'selected' : '' }}>SSL /
                                                    SMTPS (465)</option>
                                                <option value="none"
                                                    {{ $mailConfig['mail_encryption'] === 'none' ? 'selected' : '' }}>None
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Group 2: Authentication & Sender Identity -->
                                <div class="border rounded p-3 mb-3 bg-white shadow-sm">
                                    <div class="mb-2 pb-1 border-bottom">
                                        <span class="small fw-bold text-uppercase text-dark" style="font-size: 11px;">
                                            <i class="fas fa-shield-alt me-1 text-primary"></i>
                                            Sender Identity
                                        </span>
                                    </div>

                                    <!-- Credentials Row (Hidden when Local Log) -->
                                    <div id="smtpCredentialsRow"
                                        class="row g-2 mb-2 {{ $mailConfig['mail_mailer'] === 'log' ? 'd-none' : '' }}">
                                        <div class="col-md-6">
                                            <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                                for="mail_username">SMTP Username</label>
                                            <input id="mail_username" type="text" name="mail_username"
                                                value="{{ old('mail_username', $mailConfig['mail_username']) }}"
                                                class="form-control form-control-sm" placeholder="resend">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                                for="mail_password">Resend API Key (Password)</label>
                                            <input id="mail_password" type="password" name="mail_password"
                                                value="{{ old('mail_password', $mailConfig['mail_password'] ?? '') }}"
                                                class="form-control form-control-sm" autocomplete="new-password"
                                                placeholder="re_xxxxxxxxxxxx">
                                        </div>
                                    </div>

                                    <!-- Sender Identity Row (Always Visible) -->
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                                for="mail_from_address">From Address</label>
                                            <input id="mail_from_address" type="email" name="mail_from_address"
                                                value="{{ old('mail_from_address', $mailConfig['mail_from_address']) }}"
                                                class="form-control form-control-sm"
                                                placeholder="noreply@leonproject.my.id">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                                for="mail_from_name">From Sender Name</label>
                                            <input id="mail_from_name" type="text" name="mail_from_name"
                                                value="{{ old('mail_from_name', $mailConfig['mail_from_name']) }}"
                                                class="form-control form-control-sm" placeholder="FastCargo">
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success btn-sm px-4 fw-bold shadow-sm">
                                    <i class="fas fa-save me-1"></i> Save Settings
                                </button>
                            </form>
                        </div>

                        <!-- Diagnostic & Live Delivery Verification (Right Column) -->
                        <div class="col-lg-5 ps-lg-4">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                <span class="small fw-bold text-uppercase text-primary" style="font-size: 11.5px;">
                                    <i class="fas fa-satellite-dish me-1"></i> Live Delivery Verification
                                </span>
                            </div>

                            <!-- Direct Dispatch Form -->
                            <div class="border rounded p-3 mb-3 bg-light shadow-sm">
                                <form method="POST" action="{{ route('admin.settings.mail.test', [], false) }}">
                                    @csrf
                                    <div class="mb-2">
                                        <label class="form-label mb-1 text-muted fw-bold" style="font-size: 10.5px;"
                                            for="test_email">
                                            Recipient Email Address
                                        </label>
                                        <input id="test_email" type="email" name="test_email"
                                            value="{{ Auth::guard('admin')->user()?->email ?? 'mtaufiq2157@gmail.com' }}"
                                            class="form-control form-control-sm" placeholder="nama@gmail.com" required>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm w-100 fw-bold py-2 shadow-sm">
                                        <i class="fas fa-paper-plane me-1"></i> Dispatch Test Email
                                    </button>
                                </form>
                            </div>

                            <!-- Hasil Pengujian Terakhir (Last Test Diagnostic) -->
                            <div class="border rounded p-3 mb-3 bg-white shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                    <span class="small fw-bold text-muted text-uppercase" style="font-size: 10px;">
                                        <i class="fas fa-history me-1 text-info"></i> Riwayat Uji Terakhir
                                    </span>
                                    @if (!empty($mailConfig['last_test']['status']) && $mailConfig['last_test']['status'] === 'SUCCESS')
                                        <span class="badge bg-success px-2 py-1 small" style="font-size: 9px;">
                                            <i class="fas fa-check-circle me-1"></i> Berhasil
                                        </span>
                                    @elseif (!empty($mailConfig['last_test']['status']) && $mailConfig['last_test']['status'] === 'FAILED')
                                        <span class="badge bg-danger px-2 py-1 small" style="font-size: 9px;">
                                            <i class="fas fa-times-circle me-1"></i> Gagal
                                        </span>
                                    @else
                                        <span class="badge bg-secondary px-2 py-1 small" style="font-size: 9px;">Belum
                                            Diuji</span>
                                    @endif
                                </div>

                                @if (!empty($mailConfig['last_test']))
                                    <div class="row g-1 small" style="font-size: 10.5px;">
                                        <div class="col-4 text-muted">Waktu:</div>
                                        <div class="col-8 text-dark fw-bold">
                                            {{ $mailConfig['last_test']['formatted_time'] ?? '-' }}</div>

                                        <div class="col-4 text-muted">Penerima:</div>
                                        <div class="col-8 text-truncate text-dark fw-bold">
                                            {{ $mailConfig['last_test']['recipient'] ?? '-' }}</div>

                                        <div class="col-4 text-muted">Driver:</div>
                                        <div class="col-8">
                                            <span
                                                class="badge bg-light text-dark border">{{ strtoupper($mailConfig['last_test']['mailer'] ?? '-') }}</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="text-muted small fst-italic" style="font-size: 10.5px;">
                                        Belum ada pengujian yang dijalankan pada sesi ini. Masukkan email di atas lalu klik
                                        Dispatch Test Email.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PayG Multi-Channel Payment Station -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow border-start-primary">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-satellite-dish me-1"></i> PayG Multi-Channel Payment Station
                        </h6>
                        <small class="text-muted">Pusat integrasi pembayaran multi-channel: SGpay (Shopee/GoPay direct QRIS), SoBpay (SociaBuzz ASEAN Aggregator), dan Smart Simulator.</small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        @if ($paygConfig['status']['reachable'] ?? false)
                            <span class="badge bg-success">
                                <i class="fas fa-server me-1"></i> PayG Daemon (Port 8085)
                            </span>
                        @else
                            <span class="badge bg-info text-dark">
                                <i class="fas fa-flask me-1"></i> Standalone Sandbox Mode
                            </span>
                        @endif
                        <span id="badge-payg-mode" class="badge bg-primary text-uppercase">
                            {{ $paygConfig['active_mode'] ?? 'auto' }} Engine
                        </span>
                        <span id="badge-dana-mode" class="d-none"></span>
                        <span id="badge-dana-status" class="d-none"></span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <!-- Configuration Form (Left Column) -->
                        <div class="col-lg-7 border-end pe-lg-4">
                            <form method="POST" action="{{ route('admin.settings.update', [], false) }}" id="formDanaSettings">
                                @csrf
                                @method('PUT')

                                <!-- Legacy hidden fields to preserve backwards compatibility -->
                                <input type="hidden" name="dana_env" id="dana_env" value="{{ $danaConfig['dana_env'] ?? 'sandbox' }}">
                                <input type="hidden" name="dana_merchant_id" id="dana_merchant_id" value="{{ $danaConfig['dana_merchant_id'] ?? '' }}">
                                <input type="hidden" name="dana_client_id" id="dana_client_id" value="{{ $danaConfig['dana_client_id'] ?? '' }}">
                                <input type="hidden" name="dana_client_secret" id="dana_client_secret" value="{{ $danaConfig['dana_client_secret'] ?? '' }}">

                                <!-- Global Gateway Mode & Secret -->
                                <div class="border rounded p-3 mb-3 bg-light shadow-sm">
                                    <div class="row g-2">
                                        <div class="col-md-7">
                                            <label class="form-label mb-1 text-muted small fw-bold" for="payg_active_mode">Default Gateway Routing Mode</label>
                                            <select id="payg_active_mode" name="payg_active_mode" class="form-select form-select-sm">
                                                <option value="auto" {{ ($paygConfig['active_mode'] ?? 'auto') === 'auto' ? 'selected' : '' }}>Auto Dispatcher (SGpay &gt; SoBpay &gt; Simulator)</option>
                                                <option value="sgpay" {{ ($paygConfig['active_mode'] ?? '') === 'sgpay' ? 'selected' : '' }}>SGpay (Direct Merchant Shopee/GoPay QRIS)</option>
                                                <option value="sobpay" {{ ($paygConfig['active_mode'] ?? '') === 'sobpay' ? 'selected' : '' }}>SoBpay (SociaBuzz Multi-Channel &amp; ASEAN)</option>
                                                <option value="simulator" {{ ($paygConfig['active_mode'] ?? '') === 'simulator' ? 'selected' : '' }}>Simulator (Sandbox Instant Testbed)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label mb-1 text-muted small fw-bold" for="payg_secret">PayG HMAC Secret</label>
                                            <div class="input-group input-group-sm">
                                                <input type="password" id="payg_secret" name="payg_secret" class="form-control form-control-sm font-monospace"
                                                    value="{{ old('payg_secret', $paygConfig['secret'] ? '••••••••••••••••' : '') }}" placeholder="fastcargo-payg-secret-2026">
                                                <button class="btn btn-outline-secondary" type="button" id="btn-toggle-payg-secret" title="Toggle visibility">
                                                    <i class="fas fa-eye" id="icon-toggle-payg-secret"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Interactive 3 Tabs Navigation -->
                                <ul class="nav nav-tabs mb-3" id="paygTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active py-2 px-3 small fw-bold" id="tab-sgpay-btn" data-bs-toggle="tab" data-bs-target="#tab-sgpay" type="button" role="tab">
                                            <i class="fas fa-bolt text-warning me-1"></i> 1. SGpay (Direct Merchant)
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link py-2 px-3 small fw-bold" id="tab-sobpay-btn" data-bs-toggle="tab" data-bs-target="#tab-sobpay" type="button" role="tab">
                                            <i class="fas fa-globe-asia text-info me-1"></i> 2. SoBpay (Aggregator &amp; ASEAN)
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link py-2 px-3 small fw-bold" id="tab-simulator-btn" data-bs-toggle="tab" data-bs-target="#tab-simulator" type="button" role="tab">
                                            <i class="fas fa-flask text-success me-1"></i> 3. Smart Simulator
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content" id="paygTabsContent">
                                    <!-- TAB 1: SGPAY -->
                                    <div class="tab-pane fade show active" id="tab-sgpay" role="tabpanel">
                                        <div class="alert alert-light border small mb-3">
                                            <i class="fas fa-info-circle text-warning me-1"></i>
                                            <strong>SGpay Architecture:</strong> Uang langsung masuk detik itu juga ke akun ShopeePay/GoBiz pribadi Anda tanpa potongan perantara, menggunakan injeksi EMVCo Tag 54 dan kode unik unik (+Rp 1..999).
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label mb-1 text-muted small fw-bold" for="payg_sgpay_static_qr">Static QRIS String (EMVCo ASPI)</label>
                                            <textarea id="payg_sgpay_static_qr" name="payg_sgpay_static_qr" class="form-control form-control-sm font-monospace" rows="2"
                                                placeholder="00020101021126580014ID.DANA.WWW...6304ABCD">{{ old('payg_sgpay_static_qr', $paygConfig['sgpay_static_qr']) }}</textarea>
                                            <div class="form-text text-muted" style="font-size: 11px;">
                                                Salin string QRIS statis dari aplikasi ShopeePay / GoBiz merchant Anda. Sistem otomatis menginjeksi Tag 54 &amp; kode unik transaksi.
                                            </div>
                                        </div>

                                        <!-- OTP Pairing Control -->
                                        <div class="border rounded p-3 bg-white shadow-sm mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                                <span class="small fw-bold text-uppercase text-dark" style="font-size: 11px;">
                                                    <i class="fas fa-mobile-alt me-1 text-primary"></i> ShopeePay / GoBiz Pairing (OTP)
                                                </span>
                                                <span class="badge {{ ($paygConfig['status']['engines']['sgpay']['connected'] ?? false) ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ $paygConfig['status']['engines']['sgpay']['details'] ?? 'Not Paired' }}
                                                </span>
                                            </div>
                                            <div class="row g-2 mb-2">
                                                <div class="col-md-4">
                                                    <select id="sgpay_provider" class="form-select form-select-sm">
                                                        <option value="shopee">ShopeePay</option>
                                                        <option value="gopay">GoBiz (GoPay)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <input type="text" id="sgpay_phone" class="form-control form-control-sm" placeholder="081234567890">
                                                </div>
                                                <div class="col-md-3">
                                                    <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btn-sgpay-request-otp">
                                                        <i class="fas fa-paper-plane me-1"></i> Send OTP
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row g-2" id="row-sgpay-otp-verify" style="display: none;">
                                                <div class="col-md-8">
                                                    <input type="text" id="sgpay_otp_code" class="form-control form-control-sm font-monospace" placeholder="Masukkan 6-digit kode OTP">
                                                    <input type="hidden" id="sgpay_challenge">
                                                </div>
                                                <div class="col-md-4">
                                                    <button type="button" class="btn btn-success btn-sm w-100" id="btn-sgpay-verify-otp">
                                                        <i class="fas fa-check me-1"></i> Verify &amp; Pair
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="sgpay-otp-feedback" class="mt-2 small" style="display: none;"></div>
                                        </div>
                                    </div>

                                    <!-- TAB 2: SOBPAY -->
                                    <div class="tab-pane fade" id="tab-sobpay" role="tabpanel">
                                        <div class="alert alert-light border small mb-3">
                                            <i class="fas fa-info-circle text-info me-1"></i>
                                            <strong>SoBpay Architecture:</strong> Menggunakan platform SociaBuzz untuk menyediakan dynamic QRIS resmi Midtrans/Xendit (nominal pas tanpa kode ganjil), Virtual Account (BCA, Mandiri, BNI, BRI), serta metode ASEAN (Malaysia FPX, Singapore PayNow). Dana terkumpul di saldo SociaBuzz Anda.
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label mb-1 text-muted small fw-bold" for="payg_sobpay_username">SociaBuzz Creator Username</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light text-muted">@</span>
                                                <input type="text" id="payg_sobpay_username" name="payg_sobpay_username" class="form-control form-control-sm font-monospace"
                                                    value="{{ old('payg_sobpay_username', $paygConfig['sobpay_username']) }}" placeholder="username_kreator">
                                            </div>
                                            <div class="form-text text-muted" style="font-size: 11px;">
                                                Cukup masukkan username profil SociaBuzz Anda. Tidak memerlukan login OTP atau kredensial perbankan rahasia.
                                            </div>
                                        </div>

                                        <!-- Live Fee Tester -->
                                        <div class="border rounded p-3 bg-white shadow-sm mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                                <span class="small fw-bold text-uppercase text-dark" style="font-size: 11px;">
                                                    <i class="fas fa-calculator me-1 text-info"></i> Kalkulator Fee Multi-Channel &amp; ASEAN
                                                </span>
                                                <span class="badge bg-light text-dark border">Real-Time Estimator</span>
                                            </div>
                                            <div class="row g-2 mb-2">
                                                <div class="col-md-5">
                                                    <select id="fee_country" class="form-select form-select-sm">
                                                        <option value="Indonesia">Indonesia (IDR)</option>
                                                        <option value="Malaysia">Malaysia (MYR)</option>
                                                        <option value="Singapore">Singapore (SGD)</option>
                                                        <option value="Philippines">Philippines (PHP)</option>
                                                        <option value="Thailand">Thailand (THB)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="number" id="fee_amount" class="form-control form-control-sm" value="5000000" placeholder="Nominal">
                                                </div>
                                                <div class="col-md-3">
                                                    <button type="button" class="btn btn-outline-info btn-sm w-100" id="btn-calc-fees">
                                                        <i class="fas fa-search-dollar me-1"></i> Cek Fee
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="fee-calc-results" class="mt-2 small bg-light p-2 rounded border" style="display: none;"></div>
                                        </div>
                                    </div>

                                    <!-- TAB 3: SIMULATOR -->
                                    <div class="tab-pane fade" id="tab-simulator" role="tabpanel">
                                        <div class="alert alert-light border small mb-3">
                                            <i class="fas fa-flask text-success me-1"></i>
                                            <strong>Smart Simulator Engine:</strong> Sandbox bawaan tanpa uang riil untuk evaluasi kelulusan skripsi, presentasi dosen, dan continuous integration (CI) tests.
                                        </div>

                                        <!-- Quick 1-Click Sandbox Generator -->
                                        <div class="p-3 bg-light rounded-3 mb-3 border">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small fw-bold text-dark">Quick 1-Click Sandbox Generator</span>
                                                <span class="badge bg-primary">Instant Isolated</span>
                                            </div>
                                            <p class="text-muted small mb-2" style="font-size: 11px;">
                                                Generate an isolated sandbox invoice and pending transaction without manual booking:
                                            </p>
                                            <button type="button" id="btnGenSandboxDirect" class="btn btn-outline-primary btn-sm w-100 fw-bold">
                                                <i class="fas fa-magic me-1"></i> Generate Sandbox Test Invoice
                                            </button>
                                        </div>

                                        <!-- Callback Scenario Controls -->
                                        <div class="border rounded p-3 bg-white shadow-sm mb-3">
                                            <div class="mb-3">
                                                <label class="form-label small text-muted fw-bold mb-1" for="selectTransactionDirect">Select Pending Transaction</label>
                                                <select class="form-select form-select-sm" id="selectTransactionDirect">
                                                    <option value="">-- Choose Existing Transaction or Enter Manually --</option>
                                                    @foreach(($simulatorTransactions ?? $transactions ?? []) as $simTx)
                                                        <option value="{{ $simTx->transaction_ref }}"
                                                            data-amount="{{ (float) $simTx->amount }}"
                                                            data-invoice="{{ $simTx->invoice?->invoice_number }}"
                                                            data-tx-status="{{ $simTx->payment_status }}"
                                                            data-invoice-status="{{ $simTx->invoice?->payment_status ?? 'PENDING' }}"
                                                            data-escrow-status="{{ $simTx->invoice?->booking?->escrowLedger?->escrow_status ?? 'NOT_FUNDED' }}"
                                                            data-escrow-amount="{{ (float) ($simTx->invoice?->booking?->escrowLedger?->total_paid_amount ?? 0) }}">
                                                            {{ $simTx->transaction_ref }} ({{ $simTx->invoice?->invoice_number ?? 'No INV' }}) — IDR {{ number_format((float) $simTx->amount) }} [{{ $simTx->payment_status }}]
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small text-muted fw-bold mb-1" for="partnerRefDirect">Partner Reference No (partnerReferenceNo)</label>
                                                <input type="text" class="form-control form-control-sm font-monospace" id="partnerRefDirect" placeholder="SANDBOX-DANA-XXXX">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small text-muted fw-bold mb-1">Callback Status Scenario</label>
                                                <div class="scenario-btn-group d-flex flex-wrap gap-2">
                                                    <input type="radio" class="btn-check" name="direct_scenario" id="scenSettlementDirect" value="SETTLEMENT" checked>
                                                    <label class="btn btn-sm btn-outline-success" for="scenSettlementDirect">
                                                        <i class="fas fa-check-circle me-1"></i> SETTLEMENT (00)
                                                    </label>

                                                    <input type="radio" class="btn-check" name="direct_scenario" id="scenExpiredDirect" value="EXPIRED">
                                                    <label class="btn btn-sm btn-outline-secondary" for="scenExpiredDirect">
                                                        <i class="fas fa-clock me-1"></i> EXPIRED (01)
                                                    </label>

                                                    <input type="radio" class="btn-check" name="direct_scenario" id="scenFailedDirect" value="FAILED">
                                                    <label class="btn btn-sm btn-outline-danger" for="scenFailedDirect">
                                                        <i class="fas fa-times-circle me-1"></i> FAILED (02)
                                                    </label>

                                                    <input type="radio" class="btn-check" name="direct_scenario" id="scenCancelledDirect" value="CANCELLED">
                                                    <label class="btn btn-sm btn-outline-dark" for="scenCancelledDirect">
                                                        <i class="fas fa-ban me-1"></i> CANCELLED (03)
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small text-muted fw-bold mb-1" for="amountDirect">Transaction Amount (IDR)</label>
                                                <input type="number" class="form-control form-control-sm" id="amountDirect" value="6500000" step="1000">
                                            </div>

                                            <button type="button" id="btnDispatchDirect" class="btn btn-primary btn-sm d-block w-100 py-2 fw-bold shadow-sm">
                                                <i class="fas fa-paper-plane me-1"></i> Dispatch Simulated Webhook
                                            </button>
                                        </div>

                                        <!-- Live Ledger State Indicators -->
                                        <div class="border rounded p-3 bg-light shadow-sm mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                                <span class="small fw-bold text-uppercase text-dark" style="font-size: 11px;">
                                                    <i class="fas fa-search-dollar me-1 text-primary"></i> Live Ledger State Indicators
                                                </span>
                                                <span id="responseStatusBadgeDirect" class="badge bg-secondary" style="font-size: 10px;">Ready</span>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-4">
                                                    <div class="p-2 border rounded text-center bg-white">
                                                        <div class="text-muted small" style="font-size: 10px;">TX Status</div>
                                                        <div class="fw-bold mt-1" id="stateTxDirect" style="font-size: 11px;">—</div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 border rounded text-center bg-white">
                                                        <div class="text-muted small" style="font-size: 10px;">Invoice Status</div>
                                                        <div class="fw-bold mt-1" id="stateInvoiceDirect" style="font-size: 11px;">—</div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 border rounded text-center bg-white">
                                                        <div class="text-muted small" style="font-size: 10px;">Escrow Ledger</div>
                                                        <div class="fw-bold mt-1" id="stateEscrowDirect" style="font-size: 11px;">—</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Foldable/collapsible JSON viewer box -->
                                        <div class="accordion mb-3" id="directJsonAccordion">
                                            <div class="accordion-item border rounded">
                                                <h2 class="accordion-header" id="headingDirectPayload">
                                                    <button class="accordion-button collapsed py-2 px-3 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDirectPayload" aria-expanded="false" aria-controls="collapseDirectPayload">
                                                        <i class="fas fa-code me-2 text-primary"></i> Raw Dispatch Payload (Dana Snap Standard)
                                                    </button>
                                                </h2>
                                                <div id="collapseDirectPayload" class="accordion-collapse collapse" aria-labelledby="headingDirectPayload" data-bs-parent="#directJsonAccordion">
                                                    <div class="accordion-body p-2 bg-dark">
                                                        <div class="d-flex justify-content-end mb-1">
                                                            <button type="button" class="btn btn-link btn-sm text-info text-decoration-none p-0" id="btnCopyDirectReq" style="font-size: 11px;">
                                                                <i class="fas fa-copy me-1"></i> Copy JSON
                                                            </button>
                                                        </div>
                                                        <pre class="json-viewer-box mb-0" id="jsonDirectRequest">// Webhook payload will appear here upon dispatch...</pre>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="accordion-item border rounded mt-2">
                                                <h2 class="accordion-header" id="headingDirectResponse">
                                                    <button class="accordion-button collapsed py-2 px-3 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDirectResponse" aria-expanded="false" aria-controls="collapseDirectResponse">
                                                        <i class="fas fa-terminal me-2 text-success"></i> Server Response Body
                                                    </button>
                                                </h2>
                                                <div id="collapseDirectResponse" class="accordion-collapse collapse" aria-labelledby="headingDirectResponse" data-bs-parent="#directJsonAccordion">
                                                    <div class="accordion-body p-2 bg-dark">
                                                        <div class="d-flex justify-content-end mb-1">
                                                            <button type="button" class="btn btn-link btn-sm text-info text-decoration-none p-0" id="btnCopyDirectRes" style="font-size: 11px;">
                                                                <i class="fas fa-copy me-1"></i> Copy JSON
                                                            </button>
                                                        </div>
                                                        <pre class="json-viewer-box mb-0" id="jsonDirectResponse" style="color: #4ade80;">// Server response will appear here...</pre>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Simulator Audit History Collapsible -->
                                        <div class="border rounded p-3 bg-light shadow-sm mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="small fw-bold text-uppercase text-dark" style="font-size: 11px;">
                                                    <i class="fas fa-history me-1 text-secondary"></i> Simulator Audit History
                                                </span>
                                                <button class="btn btn-sm btn-link text-decoration-none p-0 small" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAuditHistory" aria-expanded="false" style="font-size: 11px;">
                                                    <i class="fas fa-chevron-down me-1"></i> View History
                                                </button>
                                            </div>
                                            <div class="collapse mt-2" id="collapseAuditHistory">
                                                <div class="table-responsive bg-white border rounded">
                                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 10.5px;">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Waktu</th>
                                                                <th>Action</th>
                                                                <th>Target Ref</th>
                                                                <th>IP</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="simulatorAuditTableBody">
                                                            @forelse(($simulatorRecentLogs ?? $recentLogs ?? []) as $log)
                                                                <tr>
                                                                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }}</td>
                                                                    <td><span class="badge bg-light text-dark border">{{ $log->action }}</span></td>
                                                                    <td class="font-monospace text-primary text-truncate" style="max-width: 100px;">{{ $log->target_id ?? 'N/A' }}</td>
                                                                    <td>{{ $log->ip_address }}</td>
                                                                </tr>
                                                            @empty
                                                                <tr class="audit-empty-row">
                                                                    <td colspan="4" class="text-center text-muted py-2">Belum ada riwayat simulasi.</td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Group 4: Webhook Callback URL -->
                                <div class="border rounded p-3 mb-3 bg-light shadow-sm">
                                    <div class="mb-2 pb-1 border-bottom d-flex justify-content-between align-items-center">
                                        <span class="small fw-bold text-uppercase text-dark" style="font-size: 11px;">
                                            <i class="fas fa-satellite-dish me-1 text-primary"></i> Internal Notification Webhook Endpoint
                                        </span>
                                        <span class="badge bg-secondary" style="font-size: 10px;">HMAC-SHA256 Protected</span>
                                    </div>
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text bg-white text-muted"><i class="fas fa-link"></i></span>
                                        <input type="text" id="payg_webhook_url" class="form-control font-monospace text-dark bg-white"
                                            value="{{ $paygConfig['webhook_url'] }}" readonly>
                                        <button class="btn btn-primary" type="button" id="btn-copy-payg-webhook">
                                            <i class="fas fa-copy me-1"></i> Copy URL
                                        </button>
                                    </div>
                                    <div class="form-text text-muted" style="font-size: 11px;">
                                        <i class="fas fa-shield-alt text-success me-1"></i> Daemon <code>services/payG</code> mengirimkan callback bertanda tangan digital ke endpoint ini untuk melunasi invoice dan mendanai escrow.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
                                    <i class="fas fa-save me-1"></i> Save PayG Settings
                                </button>
                            </form>
                        </div>

                        <!-- Diagnostic & Recent Transactions (Right Column) -->
                        <div class="col-lg-5 ps-lg-4">
                            <!-- Top Box: Live Dynamic QRIS Preview -->
                            <div class="border rounded p-3 mb-3 bg-white shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                    <span class="small fw-bold text-uppercase text-primary" style="font-size: 11.5px;">
                                        <i class="fas fa-qrcode me-1"></i> Live Dynamic QRIS Preview
                                    </span>
                                    <span class="badge bg-light text-dark border" style="font-size: 10px;">EMVCo Tag 54</span>
                                </div>
                                <div class="d-flex flex-column flex-sm-row align-items-center gap-3">
                                    <div class="p-2 border rounded bg-white shadow-sm text-center flex-shrink-0" style="width: 140px; height: 140px; display: flex; align-items: center; justify-content: center;">
                                        <div style="max-width: 100%; max-height: 100%;">
                                            {!! $paygConfig['sample_qr_svg'] !!}
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 small">
                                        <div class="fw-bold text-dark mb-1">FASTCARGO ASEAN LOGISTICS</div>
                                        <div class="text-muted mb-1" style="font-size: 11px;">
                                            <span class="badge bg-primary me-1">ENGINE</span> {{ strtoupper($paygConfig['active_mode'] ?? 'AUTO') }}
                                        </div>
                                        <div class="text-muted mb-2" style="font-size: 11px;">
                                            Mendukung ShopeePay, GoPay, DANA, OVO, Virtual Account BCA/Mandiri, dan ASEAN PayNow/FPX.
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2" id="btnSwitchToSimulatorTab" style="font-size: 11px;">
                                            <i class="fas fa-flask me-1"></i> Buka Webhook Testbed
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Box: Riwayat Transaksi Terakhir (Feed) -->
                            <div class="border rounded p-3 bg-light shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                    <span class="small fw-bold text-uppercase text-dark" style="font-size: 11px;">
                                        <i class="fas fa-receipt me-1 text-primary"></i> Riwayat Transaksi Masuk
                                    </span>
                                    <span class="badge bg-secondary" style="font-size: 10px;">5 Terakhir</span>
                                </div>

                                <div id="recentTxEmpty" class="text-center py-4 {{ $paygConfig['recent_transactions']->isEmpty() ? '' : 'd-none' }}">
                                    <i class="fas fa-inbox text-muted fa-2x mb-2 opacity-50"></i>
                                    <div class="small fw-bold text-muted">Belum ada transaksi pembayaran</div>
                                    <div class="text-muted" style="font-size: 11px;">
                                        Transaksi QRIS dari invoice customer akan otomatis tercatat di sini dan memicu escrow ledger.
                                    </div>
                                </div>

                                <div class="table-responsive {{ $paygConfig['recent_transactions']->isEmpty() ? 'd-none' : '' }}" id="recentTxTableWrapper">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 11.5px;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Ref / Gateway</th>
                                                <th>Nominal</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-end">Waktu</th>
                                            </tr>
                                        </thead>
                                        <tbody id="recentTransactionsTableBody">
                                            @foreach ($paygConfig['recent_transactions'] as $tx)
                                                <tr data-tx-ref="{{ $tx->transaction_ref }}">
                                                    <td>
                                                        <div class="fw-bold font-monospace text-truncate tx-ref" style="max-width: 120px;" title="{{ $tx->transaction_ref }}">
                                                            {{ $tx->transaction_ref }}
                                                        </div>
                                                        <div class="text-muted" style="font-size: 10px;">
                                                            <span class="badge bg-light text-dark border">{{ $tx->payment_gateway ?? 'PAYG' }}</span>
                                                            <span class="tx-invoice">{{ $tx->invoice?->invoice_number ?? '-' }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="fw-bold text-dark tx-amount">
                                                        Rp {{ number_format((float) $tx->amount, 0, ',', '.') }}
                                                    </td>
                                                    <td class="text-center tx-status-cell">
                                                        @if ($tx->payment_status === 'SETTLEMENT')
                                                            <span class="badge bg-success">SETTLED</span>
                                                        @elseif ($tx->payment_status === 'PENDING')
                                                            <span class="badge bg-warning text-dark">PENDING</span>
                                                        @elseif ($tx->payment_status === 'EXPIRED')
                                                            <span class="badge bg-secondary">EXPIRED</span>
                                                        @elseif ($tx->payment_status === 'CANCELLED')
                                                            <span class="badge bg-dark">CANCELLED</span>
                                                        @else
                                                            <span class="badge bg-danger">{{ $tx->payment_status }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end text-muted tx-time" style="font-size: 10.5px;">
                                                        {{ $tx->created_at->diffForHumans() }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Peringatan Perubahan Belum Disimpan Saat Switch Engine -->
    <div class="modal fade" id="unsavedAiChangesModal" tabindex="-1" aria-labelledby="unsavedAiChangesModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning bg-opacity-10 border-bottom border-warning border-opacity-25 py-3">
                    <h6 class="modal-title text-warning-emphasis fw-bold mb-0" id="unsavedAiChangesModalLabel">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i> Perubahan Konfigurasi Belum Disimpan
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2 text-dark fw-bold">Terdapat perubahan konfigurasi AI yang belum diuji coba dan disimpan.
                    </p>
                    <p class="mb-0 text-muted small">Jika Anda beralih engine sekarang, nilai yang baru Anda ketik akan
                        dibatalkan. Silakan jalankan <strong>"Test Connection"</strong> lalu klik <strong>"Save AI Engine
                            Settings"</strong> terlebih dahulu agar konfigurasi Anda tersimpan.</p>
                </div>
                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-primary btn-sm fw-bold" data-bs-dismiss="modal"
                        onclick="focusTestButton()">
                        <i class="fas fa-satellite-dish me-1"></i> Kembali untuk Test &amp; Save
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnConfirmSwitchAnyway">
                        Tetap Beralih Engine
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Email Driver Dynamic Toggler & Resend Preset
            const mailMailerSelect = document.getElementById('mail_mailer');
            const smtpServerGroup = document.getElementById('smtpServerGroup');
            const smtpCredentialsRow = document.getElementById('smtpCredentialsRow');
            const logModeNotice = document.getElementById('logModeNotice');
            const badgeMailDriver = document.getElementById('badge-mail-driver');
            const badgeMailStatus = document.getElementById('badge-mail-status');
            const presetBtn = document.getElementById('btn-preset-resend');

            function toggleMailDriverFields() {
                if (!mailMailerSelect) return;
                const isLog = mailMailerSelect.value === 'log';
                if (smtpServerGroup) {
                    smtpServerGroup.classList.toggle('d-none', isLog);
                }
                if (smtpCredentialsRow) {
                    smtpCredentialsRow.classList.toggle('d-none', isLog);
                }
                if (logModeNotice) {
                    logModeNotice.classList.toggle('d-none', !isLog);
                }
                if (presetBtn) {
                    presetBtn.classList.toggle('d-none', isLog);
                }
                if (badgeMailDriver) {
                    badgeMailDriver.textContent = 'Driver: ' + (isLog ? 'LOG' : 'SMTP');
                }
                if (badgeMailStatus) {
                    if (isLog) {
                        badgeMailStatus.className = 'badge bg-info text-dark';
                        badgeMailStatus.innerHTML = '<i class="fas fa-file-alt me-1"></i> Local Log Active';
                    }
                }
            }

            if (mailMailerSelect) {
                mailMailerSelect.addEventListener('change', toggleMailDriverFields);
                toggleMailDriverFields();
            }

            if (presetBtn) {
                presetBtn.addEventListener('click', function() {
                    if (mailMailerSelect) {
                        mailMailerSelect.value = 'smtp';
                        toggleMailDriverFields();
                    }
                    document.getElementById('mail_host').value = 'smtp.resend.com';
                    document.getElementById('mail_port').value = '465';
                    document.getElementById('mail_encryption').value = 'ssl';
                    document.getElementById('mail_username').value = 'resend';
                    document.getElementById('mail_from_address').value = 'noreply@leonproject.my.id';
                    document.getElementById('mail_from_name').value = 'FastCargo';
                    document.getElementById('mail_password').focus();
                });
            }

            // AI Provider Dynamic Switcher
            const providerSelect = document.getElementById('ai_provider_select');
            const geminiSection = document.getElementById('aiSectionGemini');
            const customSection = document.getElementById('aiSectionCustom');
            const btnSwitchEngine = document.getElementById('btnSwitchEngine');
            const btnSaveAi = document.getElementById('btnSaveAiSettings');
            const btnTestAi = document.getElementById('btnTestAiConnection');
            const testFeedback = document.getElementById('aiTestFeedback');
            const aiSwitchForm = document.getElementById('aiSwitchForm');
            const btnConfirmSwitch = document.getElementById('btnConfirmSwitchAnyway');
            const formAiSettings = document.getElementById('formAiSettings');

            let aiFormIsDirty = false;
            let aiConnectionVerified = true;

            // Restore last active view from localStorage if available
            try {
                const savedView = localStorage.getItem('fastcargo_ai_selected_view');
                if (savedView && providerSelect && (savedView === 'gemini' || savedView === 'custom')) {
                    providerSelect.value = savedView;
                    if (savedView === 'gemini') {
                        geminiSection.classList.remove('d-none');
                        customSection.classList.add('d-none');
                    } else {
                        geminiSection.classList.add('d-none');
                        customSection.classList.remove('d-none');
                    }
                }
            } catch (e) {}

            if (providerSelect) {
                providerSelect.addEventListener('change', function() {
                    try {
                        localStorage.setItem('fastcargo_ai_selected_view', this.value);
                    } catch (e) {}

                    if (this.value === 'gemini') {
                        geminiSection.classList.remove('d-none');
                        customSection.classList.add('d-none');
                    } else {
                        geminiSection.classList.add('d-none');
                        customSection.classList.remove('d-none');
                    }
                    testFeedback.classList.add('d-none');
                });
            }

            // Dirty State Tracker on AI Inputs
            document.querySelectorAll('.ai-dirty-tracker').forEach(function(el) {
                el.addEventListener('input', function() {
                    aiFormIsDirty = true;
                    aiConnectionVerified = false;
                    updateSaveButtonState();
                });
            });

            function updateSaveButtonState() {
                if (aiFormIsDirty && !aiConnectionVerified) {
                    btnSaveAi.classList.remove('btn-primary');
                    btnSaveAi.classList.add('btn-secondary');
                    btnSaveAi.setAttribute('disabled', 'disabled');
                    btnSaveAi.setAttribute('title',
                        'Jalankan Test Connection terlebih dahulu untuk memvalidasi perubahan');
                } else {
                    btnSaveAi.classList.remove('btn-secondary');
                    btnSaveAi.classList.add('btn-primary');
                    btnSaveAi.removeAttribute('disabled');
                    btnSaveAi.removeAttribute('title');
                }
            }

            // Intercept Switch Engine if Unsaved Changes Exist
            if (btnSwitchEngine) {
                btnSwitchEngine.addEventListener('click', function(e) {
                    if (aiFormIsDirty) {
                        e.preventDefault();
                        const modalEl = document.getElementById('unsavedAiChangesModal');
                        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    }
                });
            }

            if (btnConfirmSwitch) {
                btnConfirmSwitch.addEventListener('click', function() {
                    aiFormIsDirty = false;
                    aiSwitchForm.submit();
                });
            }

            window.focusTestButton = function() {
                if (btnTestAi) {
                    btnTestAi.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    btnTestAi.focus();
                }
            };

            // Real-time AJAX Save AI Settings (No Page Reload)
            if (formAiSettings) {
                formAiSettings.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const originalSaveHtml = btnSaveAi.innerHTML;
                    btnSaveAi.disabled = true;
                    btnSaveAi.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menyimpan...';

                    const formData = new FormData(formAiSettings);
                    const payload = {};
                    formData.forEach(function(value, key) {
                        payload[key] = value;
                    });

                    fetch('{{ route('admin.settings.update', [], false) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            btnSaveAi.disabled = false;
                            btnSaveAi.innerHTML = originalSaveHtml;

                            if (data.success) {
                                testFeedback.className = 'alert alert-success py-2 px-3 small mb-3';
                                testFeedback.innerHTML =
                                    '<i class="fas fa-check-circle me-2"></i> <strong>Tersimpan:</strong> ' +
                                    (data.message || 'Konfigurasi pengaturan AI berhasil disimpan.');
                                testFeedback.classList.remove('d-none');
                                aiFormIsDirty = false;
                                updateSaveButtonState();
                            } else {
                                testFeedback.className = 'alert alert-danger py-2 px-3 small mb-3';
                                testFeedback.innerHTML =
                                    '<i class="fas fa-times-circle me-2"></i> <strong>Gagal:</strong> ' +
                                    (data.message || 'Gagal menyimpan pengaturan.');
                                testFeedback.classList.remove('d-none');
                            }
                        })
                        .catch(function(err) {
                            btnSaveAi.disabled = false;
                            btnSaveAi.innerHTML = originalSaveHtml;
                            testFeedback.className = 'alert alert-danger py-2 px-3 small mb-3';
                            testFeedback.innerHTML =
                                '<i class="fas fa-exclamation-triangle me-2"></i> <strong>Error:</strong> ' +
                                (err.message || 'Gagal menghubungi server.');
                            testFeedback.classList.remove('d-none');
                        });
                });
            }

            // Real-time AJAX Test Connection
            if (btnTestAi) {
                btnTestAi.addEventListener('click', function() {
                    const originalHtml = btnTestAi.innerHTML;
                    btnTestAi.disabled = true;
                    btnTestAi.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Testing...';

                    testFeedback.className = 'alert alert-info py-2 px-3 small mb-3';
                    testFeedback.innerHTML =
                        '<i class="fas fa-spinner fa-spin me-2"></i> Menghubungi endpoint AI untuk verifikasi realtime...';
                    testFeedback.classList.remove('d-none');

                    const selectedProvider = providerSelect ? providerSelect.value : 'gemini';
                    const payload = {
                        provider: selectedProvider,
                        gemini_api_key: document.getElementById('gemini_api_key') ? document
                            .getElementById('gemini_api_key').value : '',
                        gemini_model: document.getElementById('gemini_model') ? document.getElementById(
                            'gemini_model').value : '',
                        custom_ai_provider_name: document.getElementById('custom_ai_provider_name') ?
                            document.getElementById('custom_ai_provider_name').value : '',
                        custom_ai_base_url: document.getElementById('custom_ai_base_url') ? document
                            .getElementById('custom_ai_base_url').value : '',
                        custom_ai_api_key: document.getElementById('custom_ai_api_key') ? document
                            .getElementById('custom_ai_api_key').value : '',
                        custom_ai_model: document.getElementById('custom_ai_model') ? document
                            .getElementById('custom_ai_model').value : ''
                    };

                    fetch('{{ route('admin.settings.ai.test', [], false) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            btnTestAi.disabled = false;
                            btnTestAi.innerHTML = originalHtml;

                            if (data.success) {
                                testFeedback.className = 'alert alert-success py-2 px-3 small mb-3';
                                testFeedback.innerHTML =
                                    '<i class="fas fa-check-circle me-2"></i> <strong>Koneksi Berhasil:</strong> ' +
                                    data.message;
                                aiConnectionVerified = true;
                                updateSaveButtonState();
                            } else {
                                testFeedback.className = 'alert alert-danger py-2 px-3 small mb-3';
                                testFeedback.innerHTML =
                                    '<i class="fas fa-times-circle me-2"></i> <strong>Koneksi Gagal:</strong> ' +
                                    data.message;
                                aiConnectionVerified = false;
                                updateSaveButtonState();
                            }
                        })
                        .catch(function(err) {
                            btnTestAi.disabled = false;
                            btnTestAi.innerHTML = originalHtml;
                            testFeedback.className = 'alert alert-danger py-2 px-3 small mb-3';
                            testFeedback.innerHTML =
                                '<i class="fas fa-exclamation-triangle me-2"></i> <strong>Error:</strong> ' +
                                (err.message || 'Gagal menghubungi server.');
                            aiConnectionVerified = false;
                            updateSaveButtonState();
                        });
                });
            }

            // Real-time WhatsApp Gateway Polling & Live Status Update
            const wagStatusUrl = '{{ route('admin.wa.status', [], false) }}';
            const wagBadgeEl = document.getElementById('wag-status-badge');
            const wagLeftBox = document.getElementById('wag-left-box');
            const wagDetailStatus = document.getElementById('wag-detail-status');
            const wagDetailPhone = document.getElementById('wag-detail-phone');
            const wagDetailPairedAt = document.getElementById('wag-detail-paired-at');

            if (wagBadgeEl && wagLeftBox) {
                function checkWagStatus() {
                    fetch(wagStatusUrl, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            if (!data.reachable) {
                                wagBadgeEl.innerHTML =
                                    '<span class="badge bg-danger px-2 py-1 small"><i class="fas fa-plug me-1"></i> Offline</span>';
                                if (wagDetailStatus) {
                                    wagDetailStatus.className = 'fw-bold small text-danger';
                                    wagDetailStatus.innerHTML =
                                        '<i class="fas fa-times-circle me-1"></i> Offline / Terputus';
                                }
                                if (wagDetailPhone) wagDetailPhone.innerHTML =
                                    '<span class="text-muted fst-italic">Belum Terhubung</span>';
                                if (wagDetailPairedAt) wagDetailPairedAt.innerHTML =
                                    '<i class="far fa-clock me-1"></i> Menunggu scan';
                                wagLeftBox.innerHTML = `
                                    <div class="text-danger mb-2"><i class="fas fa-plug fa-3x"></i></div>
                                    <span class="badge bg-danger px-2 py-1 mb-1 small">waG Offline</span>
                                    <div class="text-muted" style="font-size: 10px;">Layanan tidak dapat dijangkau</div>
                                `;
                            } else if (data.connected) {
                                wagBadgeEl.innerHTML =
                                    '<span class="badge bg-success px-2 py-1 small"><i class="fas fa-check-circle me-1"></i> Connected</span>';
                                const userName = (data.user && data.user.name) ? data.user.name : ((data.user &&
                                        data.user.phone) ? 'WhatsApp (+' + data.user.phone + ')' :
                                    'Device Connected');
                                const userPhone = (data.user && data.user.phone) ? '+' + data.user.phone : '';
                                if (wagDetailStatus) {
                                    wagDetailStatus.className = 'fw-bold small text-success';
                                    wagDetailStatus.innerHTML =
                                        '<i class="fas fa-check-circle me-1"></i> Online';
                                }
                                if (wagDetailPhone) {
                                    wagDetailPhone.innerHTML = userPhone ? `<code>${userPhone}</code>` :
                                        '<span class="text-muted fst-italic">Terhubung</span>';
                                }
                                if (wagDetailPairedAt && data.paired_at) {
                                    wagDetailPairedAt.innerHTML =
                                        `<i class="far fa-clock me-1"></i> ${data.paired_at}`;
                                }
                                wagLeftBox.innerHTML = `
                                    <div class="text-success mb-2"><i class="fab fa-whatsapp fa-3x"></i></div>
                                    <span class="badge bg-success px-2 py-1 mb-1 small"><i class="fas fa-check-circle me-1"></i> Sesi Terhubung</span>
                                    <div class="small fw-bold text-dark mb-0" style="font-size: 11px;">${userName}</div>
                                    <code class="text-muted" style="font-size: 10px;">${userPhone}</code>
                                `;
                            } else if (data.qr_svg) {
                                wagBadgeEl.innerHTML =
                                    '<span class="badge bg-warning text-dark px-2 py-1 small"><i class="fas fa-qrcode me-1"></i> Pairing Needed</span>';
                                if (wagDetailStatus) {
                                    wagDetailStatus.className = 'fw-bold small text-warning';
                                    wagDetailStatus.innerHTML =
                                        '<i class="fas fa-hourglass-half me-1"></i> Menunggu Pairing';
                                }
                                if (wagDetailPhone) wagDetailPhone.innerHTML =
                                    '<span class="text-muted fst-italic">Belum Terhubung</span>';
                                if (wagDetailPairedAt) wagDetailPairedAt.innerHTML =
                                    '<i class="far fa-clock me-1"></i> Menunggu scan';
                                wagLeftBox.innerHTML = `
                                    <div class="p-1 bg-white rounded border mb-1 d-flex align-items-center justify-content-center" style="width: 122px; height: 122px;">
                                        ${data.qr_svg}
                                    </div>
                                    <div class="fw-bold text-dark" style="font-size: 11px;"><i class="fas fa-qrcode me-1 text-primary"></i> Scan QR WhatsApp</div>
                                    <div class="text-muted" style="font-size: 9.5px;">Buka WA &gt; Perangkat Tertaut</div>
                                `;
                            }
                        })
                        .catch(function() {});
                }
                setInterval(checkWagStatus, 4000);
            }

            // AJAX Dispatch for Direct WhatsApp Test Form
            const formWaTest = document.getElementById('formWaTestDirect');
            const btnWaTestSubmit = document.getElementById('btnWaTestSubmit');
            const waTestAlertBox = document.getElementById('waTestAlertBox');

            if (formWaTest && btnWaTestSubmit) {
                formWaTest.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const originalHtml = btnWaTestSubmit.innerHTML;
                    btnWaTestSubmit.disabled = true;
                    btnWaTestSubmit.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...';

                    if (waTestAlertBox) {
                        waTestAlertBox.className = 'alert alert-info py-1 px-2 small mb-2';
                        waTestAlertBox.innerHTML =
                            '<i class="fas fa-spinner fa-spin me-1"></i> Mengirim notifikasi WhatsApp via gateway...';
                        waTestAlertBox.classList.remove('d-none');
                    }

                    const payload = {
                        phone: document.getElementById('wa_direct_phone').value,
                        message: document.getElementById('wa_direct_message').value
                    };

                    fetch(formWaTest.action, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        })
                        .then(function(res) {
                            return res.json().then(function(data) {
                                return {
                                    ok: res.ok,
                                    data: data
                                };
                            });
                        })
                        .then(function(result) {
                            btnWaTestSubmit.disabled = false;
                            btnWaTestSubmit.innerHTML = originalHtml;

                            if (result.ok && result.data.success) {
                                if (waTestAlertBox) {
                                    waTestAlertBox.className =
                                        'alert alert-success py-1 px-2 small mb-2';
                                    waTestAlertBox.innerHTML =
                                        '<i class="fas fa-check-circle me-1"></i> <strong>Berhasil:</strong> ' +
                                        result.data.message;
                                }
                            } else {
                                if (waTestAlertBox) {
                                    waTestAlertBox.className =
                                        'alert alert-danger py-1 px-2 small mb-2';
                                    waTestAlertBox.innerHTML =
                                        '<i class="fas fa-times-circle me-1"></i> <strong>Gagal:</strong> ' +
                                        (result.data.message || 'Gagal mengirim pesan.');
                                }
                            }
                        })
                        .catch(function(err) {
                            btnWaTestSubmit.disabled = false;
                            btnWaTestSubmit.innerHTML = originalHtml;
                            if (waTestAlertBox) {
                                waTestAlertBox.className = 'alert alert-danger py-1 px-2 small mb-2';
                                waTestAlertBox.innerHTML =
                                    '<i class="fas fa-exclamation-triangle me-1"></i> <strong>Error:</strong> ' +
                                    (err.message || 'Gagal menghubungi server.');
                            }
                        });
                });
            }

            // PayG Payment Station Handlers
            const paygModeSelect = document.getElementById('payg_active_mode');
            const badgePaygMode = document.getElementById('badge-payg-mode');
            const btnTogglePaygSecret = document.getElementById('btn-toggle-payg-secret');
            const paygSecretInput = document.getElementById('payg_secret');
            const iconTogglePaygSecret = document.getElementById('icon-toggle-payg-secret');
            const btnCopyPaygWebhook = document.getElementById('btn-copy-payg-webhook');
            const paygWebhookInput = document.getElementById('payg_webhook_url');

            if (paygModeSelect && badgePaygMode) {
                paygModeSelect.addEventListener('change', function() {
                    badgePaygMode.textContent = this.value.toUpperCase() + ' ENGINE';
                });
            }

            if (btnTogglePaygSecret && paygSecretInput && iconTogglePaygSecret) {
                btnTogglePaygSecret.addEventListener('click', function() {
                    if (paygSecretInput.type === 'password') {
                        paygSecretInput.type = 'text';
                        iconTogglePaygSecret.className = 'fas fa-eye-slash';
                    } else {
                        paygSecretInput.type = 'password';
                        iconTogglePaygSecret.className = 'fas fa-eye';
                    }
                });
            }

            if (btnCopyPaygWebhook && paygWebhookInput) {
                btnCopyPaygWebhook.addEventListener('click', function() {
                    navigator.clipboard.writeText(paygWebhookInput.value).then(function() {
                        const orig = btnCopyPaygWebhook.innerHTML;
                        btnCopyPaygWebhook.className = 'btn btn-success';
                        btnCopyPaygWebhook.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
                        setTimeout(function() {
                            btnCopyPaygWebhook.className = 'btn btn-primary';
                            btnCopyPaygWebhook.innerHTML = orig;
                        }, 2000);
                    });
                });
            }

            // SGpay OTP Request
            const btnSgpayRequestOtp = document.getElementById('btn-sgpay-request-otp');
            const sgpayPhoneInput = document.getElementById('sgpay_phone');
            const sgpayProviderSelect = document.getElementById('sgpay_provider');
            const rowSgpayOtpVerify = document.getElementById('row-sgpay-otp-verify');
            const sgpayChallengeInput = document.getElementById('sgpay_challenge');
            const sgpayOtpFeedback = document.getElementById('sgpay-otp-feedback');

            if (btnSgpayRequestOtp && sgpayPhoneInput) {
                btnSgpayRequestOtp.addEventListener('click', function() {
                    const phone = sgpayPhoneInput.value.trim();
                    if (!phone) {
                        alert('Silakan masukkan nomor HP terlebih dahulu.');
                        return;
                    }
                    btnSgpayRequestOtp.disabled = true;
                    btnSgpayRequestOtp.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';

                    fetch('{{ route('admin.settings.payg.otp.request', [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            provider: sgpayProviderSelect.value,
                            phone: phone
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        btnSgpayRequestOtp.disabled = false;
                        btnSgpayRequestOtp.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Resend OTP';
                        if (data.success) {
                            if (rowSgpayOtpVerify) rowSgpayOtpVerify.style.display = 'flex';
                            if (sgpayChallengeInput) sgpayChallengeInput.value = data.challenge || '';
                            if (sgpayOtpFeedback) {
                                sgpayOtpFeedback.className = 'mt-2 small text-success';
                                sgpayOtpFeedback.style.display = 'block';
                                sgpayOtpFeedback.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + (data.message || 'OTP challenge terkirim ke HP.');
                            }
                        } else {
                            if (sgpayOtpFeedback) {
                                sgpayOtpFeedback.className = 'mt-2 small text-danger';
                                sgpayOtpFeedback.style.display = 'block';
                                sgpayOtpFeedback.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Gagal mengirim OTP.');
                            }
                        }
                    })
                    .catch(err => {
                        btnSgpayRequestOtp.disabled = false;
                        btnSgpayRequestOtp.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send OTP';
                    });
                });
            }

            // SGpay OTP Verify
            const btnSgpayVerifyOtp = document.getElementById('btn-sgpay-verify-otp');
            const sgpayOtpCodeInput = document.getElementById('sgpay_otp_code');

            if (btnSgpayVerifyOtp && sgpayOtpCodeInput) {
                btnSgpayVerifyOtp.addEventListener('click', function() {
                    const otp = sgpayOtpCodeInput.value.trim();
                    if (!otp) {
                        alert('Silakan masukkan kode OTP.');
                        return;
                    }
                    btnSgpayVerifyOtp.disabled = true;
                    btnSgpayVerifyOtp.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Verifying...';

                    fetch('{{ route('admin.settings.payg.otp.verify', [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            provider: sgpayProviderSelect.value,
                            phone: sgpayPhoneInput.value.trim(),
                            otp: otp,
                            challenge: sgpayChallengeInput ? sgpayChallengeInput.value : ''
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        btnSgpayVerifyOtp.disabled = false;
                        btnSgpayVerifyOtp.innerHTML = '<i class="fas fa-check me-1"></i> Verify & Pair';
                        if (data.success) {
                            if (sgpayOtpFeedback) {
                                sgpayOtpFeedback.className = 'mt-2 small text-success fw-bold';
                                sgpayOtpFeedback.innerHTML = '<i class="fas fa-check-circle me-1"></i> Akun berhasil terverifikasi dan terhubung!';
                            }
                        } else {
                            if (sgpayOtpFeedback) {
                                sgpayOtpFeedback.className = 'mt-2 small text-danger';
                                sgpayOtpFeedback.innerHTML = '<i class="fas fa-times-circle me-1"></i> ' + (data.error || 'Kode OTP tidak valid.');
                            }
                        }
                    })
                    .catch(err => {
                        btnSgpayVerifyOtp.disabled = false;
                        btnSgpayVerifyOtp.innerHTML = '<i class="fas fa-check me-1"></i> Verify & Pair';
                    });
                });
            }

            // SoBpay Fee Calculator
            const btnCalcFees = document.getElementById('btn-calc-fees');
            const feeCountrySelect = document.getElementById('fee_country');
            const feeAmountInput = document.getElementById('fee_amount');
            const feeCalcResults = document.getElementById('fee-calc-results');

            if (btnCalcFees && feeCountrySelect && feeAmountInput && feeCalcResults) {
                btnCalcFees.addEventListener('click', function() {
                    btnCalcFees.disabled = true;
                    btnCalcFees.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Checking...';

                    const country = feeCountrySelect.value;
                    const amount = feeAmountInput.value || 1000000;

                    fetch(`{{ route('admin.settings.payg.fees', [], false) }}?country=${encodeURIComponent(country)}&amount=${encodeURIComponent(amount)}`, {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(res => res.json())
                    .then(data => {
                        btnCalcFees.disabled = false;
                        btnCalcFees.innerHTML = '<i class="fas fa-search-dollar me-1"></i> Cek Fee';
                        if (data.success && data.estimates) {
                            feeCalcResults.style.display = 'block';
                            let html = `<div class="fw-bold mb-1 text-dark">Estimasi Biaya Transaksi (${country}):</div><ul class="mb-0 ps-3">`;
                            data.estimates.forEach(est => {
                                html += `<li><strong>${est.method_name}:</strong> Fee ${est.currency} ${Number(est.estimated_fee).toLocaleString()} &rarr; Total <strong>${est.currency} ${Number(est.total_amount).toLocaleString()}</strong></li>`;
                            });
                            html += `</ul>`;
                            feeCalcResults.innerHTML = html;
                        }
                    })
                    .catch(err => {
                        btnCalcFees.disabled = false;
                        btnCalcFees.innerHTML = '<i class="fas fa-search-dollar me-1"></i> Cek Fee';
                    });
                });
            }

            // Legacy DANA fallback handlers (to preserve test compatibility)
            const danaEnvSelect = document.getElementById('dana_env');
            const badgeDanaMode = document.getElementById('badge-dana-mode');
            const btnPresetDana = document.getElementById('btn-preset-dana');
            const btnCopyDanaWebhook = document.getElementById('btn-copy-dana-webhook');
            const danaWebhookInput = document.getElementById('dana_webhook_url');
            const btnToggleDanaSecret = document.getElementById('btn-toggle-dana-secret');
            const danaSecretInput = document.getElementById('dana_client_secret');
            const iconToggleDanaSecret = document.getElementById('icon-toggle-dana-secret');

            if (danaEnvSelect && badgeDanaMode) {
                danaEnvSelect.addEventListener('change', function() {
                    const isProd = this.value === 'production';
                    badgeDanaMode.className = 'badge ' + (isProd ? 'bg-danger' : 'bg-info text-dark');
                    badgeDanaMode.innerHTML = '<i class="fas ' + (isProd ? 'fa-fire' : 'fa-flask') + ' me-1"></i> ' +
                        (isProd ? 'Live Production Snap' : 'Sandbox (Simulator)');
                });
            }

            if (btnPresetDana) {
                btnPresetDana.addEventListener('click', function() {
                    if (danaEnvSelect) {
                        danaEnvSelect.value = 'sandbox';
                        danaEnvSelect.dispatchEvent(new Event('change'));
                    }
                    const merchantInput = document.getElementById('dana_merchant_id');
                    const clientInput = document.getElementById('dana_client_id');
                    if (merchantInput) merchantInput.value = '2166100000000001';
                    if (clientInput) clientInput.value = 'fastcargo-snap-sandbox';
                    if (danaSecretInput) {
                        danaSecretInput.value = 'sandbox_secret_998877665544';
                        danaSecretInput.type = 'text';
                        if (iconToggleDanaSecret) {
                            iconToggleDanaSecret.className = 'fas fa-eye-slash';
                        }
                    }
                });
            }

            if (btnCopyDanaWebhook && danaWebhookInput) {
                btnCopyDanaWebhook.addEventListener('click', function() {
                    const text = danaWebhookInput.value;
                    navigator.clipboard.writeText(text).then(function() {
                        const originalHtml = btnCopyDanaWebhook.innerHTML;
                        btnCopyDanaWebhook.className = 'btn btn-success';
                        btnCopyDanaWebhook.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
                        setTimeout(function() {
                            btnCopyDanaWebhook.className = 'btn btn-primary';
                            btnCopyDanaWebhook.innerHTML = originalHtml;
                        }, 2000);
                    });
                });
            }

            if (btnToggleDanaSecret && danaSecretInput && iconToggleDanaSecret) {
                btnToggleDanaSecret.addEventListener('click', function() {
                    if (danaSecretInput.type === 'password') {
                        danaSecretInput.type = 'text';
                        iconToggleDanaSecret.className = 'fas fa-eye-slash';
                    } else {
                        danaSecretInput.type = 'password';
                        iconToggleDanaSecret.className = 'fas fa-eye';
                    }
                });
            }

            // Embedded PayG Smart Simulator Logic
            const btnGenDirect = document.getElementById('btnGenSandboxDirect');
            const selectTxDirect = document.getElementById('selectTransactionDirect');
            const partnerRefDirect = document.getElementById('partnerRefDirect');
            const amountDirect = document.getElementById('amountDirect');
            const btnDispatchDirect = document.getElementById('btnDispatchDirect');
            const statusBadgeDirect = document.getElementById('responseStatusBadgeDirect');

            const stateTxDirect = document.getElementById('stateTxDirect');
            const stateInvoiceDirect = document.getElementById('stateInvoiceDirect');
            const stateEscrowDirect = document.getElementById('stateEscrowDirect');

            const jsonDirectReq = document.getElementById('jsonDirectRequest');
            const jsonDirectRes = document.getElementById('jsonDirectResponse');

            const recentTxEmpty = document.getElementById('recentTxEmpty');
            const recentTxTableWrapper = document.getElementById('recentTxTableWrapper');
            const recentTxTableBody = document.getElementById('recentTransactionsTableBody');
            const simulatorAuditTableBody = document.getElementById('simulatorAuditTableBody');

            // Prepend new row to the embedded audit trail table
            function prependAuditLog(action, targetRef) {
                if (!simulatorAuditTableBody) return;
                const emptyRow = simulatorAuditTableBody.querySelector('.audit-empty-row');
                if (emptyRow) emptyRow.remove();

                const now = new Date();
                const timeStr = String(now.getHours()).padStart(2, '0') + ':' +
                                String(now.getMinutes()).padStart(2, '0') + ':' +
                                String(now.getSeconds()).padStart(2, '0');

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-nowrap">${timeStr}</td>
                    <td><span class="badge bg-light text-dark border">${action}</span></td>
                    <td class="font-monospace text-primary text-truncate" style="max-width: 100px;">${targetRef || 'N/A'}</td>
                    <td>127.0.0.1</td>
                `;
                simulatorAuditTableBody.prepend(tr);
            }

            // Safe clipboard copy with fallback
            function copyDirectJson(text, btnElement) {
                if (!text || text.startsWith('//')) return;
                const successCallback = () => {
                    const orig = btnElement.innerHTML;
                    btnElement.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
                    setTimeout(() => { btnElement.innerHTML = orig; }, 2000);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(successCallback).catch(() => fallbackDirectCopy(text, successCallback));
                } else {
                    fallbackDirectCopy(text, successCallback);
                }
            }

            function fallbackDirectCopy(text, callback) {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    callback();
                } catch (e) {}
                document.body.removeChild(ta);
            }

            function updateRecentTransactionsTable(ref, status, amount, invoiceNo) {
                if (recentTxEmpty) recentTxEmpty.classList.add('d-none');
                if (recentTxTableWrapper) recentTxTableWrapper.classList.remove('d-none');

                let badgeHtml = '';
                const upperStatus = (status || '').toUpperCase();
                let hlClass = 'table-danger';

                if (upperStatus === 'SETTLEMENT' || upperStatus === 'SETTLED') {
                    badgeHtml = '<span class="badge bg-success">SETTLED</span>';
                    hlClass = 'table-success';
                } else if (upperStatus === 'PENDING') {
                    badgeHtml = '<span class="badge bg-warning text-dark">PENDING</span>';
                    hlClass = 'table-warning';
                } else if (upperStatus === 'EXPIRED') {
                    badgeHtml = '<span class="badge bg-secondary">EXPIRED</span>';
                    hlClass = 'table-secondary';
                } else if (upperStatus === 'CANCELLED') {
                    badgeHtml = '<span class="badge bg-dark">CANCELLED</span>';
                    hlClass = 'table-secondary';
                } else {
                    badgeHtml = `<span class="badge bg-danger">${upperStatus}</span>`;
                }

                const existingRow = recentTxTableBody ? recentTxTableBody.querySelector(`tr[data-tx-ref="${ref}"]`) : null;
                if (existingRow) {
                    const statusCell = existingRow.querySelector('.tx-status-cell');
                    if (statusCell) statusCell.innerHTML = badgeHtml;
                    const timeCell = existingRow.querySelector('.tx-time');
                    if (timeCell) timeCell.textContent = 'Baru saja';
                    if (amount) {
                        const amountCell = existingRow.querySelector('.tx-amount');
                        if (amountCell) amountCell.textContent = 'Rp ' + Number(amount).toLocaleString('id-ID');
                    }
                    if (invoiceNo) {
                        const invSpan = existingRow.querySelector('.tx-invoice');
                        if (invSpan && (!invSpan.textContent || invSpan.textContent.trim() === '-')) {
                            invSpan.textContent = invoiceNo;
                        }
                    }
                    recentTxTableBody.prepend(existingRow);
                    existingRow.classList.remove('table-success', 'table-warning', 'table-secondary', 'table-danger');
                    existingRow.classList.add(hlClass);
                    setTimeout(() => existingRow.classList.remove(hlClass), 2500);
                } else if (recentTxTableBody) {
                    const newRow = document.createElement('tr');
                    newRow.dataset.txRef = ref;
                    newRow.innerHTML = `
                        <td>
                            <div class="fw-bold font-monospace text-truncate tx-ref" style="max-width: 120px;" title="${ref}">
                                ${ref}
                            </div>
                            <div class="text-muted" style="font-size: 10px;">
                                <span class="badge bg-light text-dark border">PAYG</span>
                                <span class="tx-invoice">${invoiceNo || '-'}</span>
                            </div>
                        </td>
                        <td class="fw-bold text-dark tx-amount">
                            Rp ${Number(amount || 0).toLocaleString('id-ID')}
                        </td>
                        <td class="text-center tx-status-cell">
                            ${badgeHtml}
                        </td>
                        <td class="text-end text-muted tx-time" style="font-size: 10.5px;">
                            Baru saja
                        </td>
                    `;
                    recentTxTableBody.prepend(newRow);
                    newRow.classList.add(hlClass);
                    setTimeout(() => newRow.classList.remove(hlClass), 2500);
                }
            }

            // Prevent accidental form submission on Enter in simulator inputs
            if (partnerRefDirect) {
                partnerRefDirect.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (btnDispatchDirect) btnDispatchDirect.click();
                    }
                });
            }
            if (amountDirect) {
                amountDirect.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (btnDispatchDirect) btnDispatchDirect.click();
                    }
                });
            }

            // Quick Switch button from Right Column to Tab 3
            const btnSwitchToSimulator = document.getElementById('btnSwitchToSimulatorTab');
            if (btnSwitchToSimulator) {
                btnSwitchToSimulator.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabBtn = document.getElementById('tab-simulator-btn');
                    if (tabBtn) {
                        tabBtn.click();
                        tabBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            }

            // Auto-activate Tab 3 if URL hash or parameter requests it
            if (window.location.hash === '#tab-simulator' || window.location.search.includes('tab=simulator')) {
                const tabBtn = document.getElementById('tab-simulator-btn');
                if (tabBtn) {
                    setTimeout(() => {
                        tabBtn.click();
                        tabBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 150);
                }
            }

            // 1-Click Sandbox Test Generator
            if (btnGenDirect) {
                btnGenDirect.addEventListener('click', function(e) {
                    e.preventDefault();
                    btnGenDirect.disabled = true;
                    btnGenDirect.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating Sandbox TX...';

                    fetch('{{ route("admin.simulator.generate", [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        btnGenDirect.disabled = false;
                        btnGenDirect.innerHTML = '<i class="fas fa-magic me-1"></i> Generate Sandbox Test Invoice';

                        if (data.success && data.transaction) {
                            if (partnerRefDirect) partnerRefDirect.value = data.transaction.transaction_ref;
                            if (amountDirect) amountDirect.value = data.transaction.amount;

                            if (selectTxDirect) {
                                const opt = document.createElement('option');
                                opt.value = data.transaction.transaction_ref;
                                opt.dataset.amount = data.transaction.amount;
                                opt.dataset.invoice = data.transaction.invoice_number;
                                opt.dataset.txStatus = 'PENDING';
                                opt.dataset.invoiceStatus = 'PENDING';
                                opt.dataset.escrowStatus = 'NOT_FUNDED';
                                opt.dataset.escrowAmount = '0.00';
                                opt.textContent = `${data.transaction.transaction_ref} (${data.transaction.invoice_number}) — IDR ${Number(data.transaction.amount).toLocaleString('id-ID')} [PENDING]`;

                                // Keep the placeholder at index 0
                                if (selectTxDirect.children.length > 0 && selectTxDirect.children[0].value === '') {
                                    selectTxDirect.children[0].insertAdjacentElement('afterend', opt);
                                } else {
                                    selectTxDirect.prepend(opt);
                                }
                                selectTxDirect.value = data.transaction.transaction_ref;
                            }

                            if (stateTxDirect) stateTxDirect.innerHTML = '<span class="badge bg-warning text-dark">PENDING</span>';
                            if (stateInvoiceDirect) stateInvoiceDirect.innerHTML = '<span class="badge bg-warning text-dark">PENDING</span>';
                            if (stateEscrowDirect) stateEscrowDirect.innerHTML = '<span class="badge bg-secondary">NOT_FUNDED</span>';

                            updateRecentTransactionsTable(
                                data.transaction.transaction_ref,
                                'PENDING',
                                data.transaction.amount,
                                data.transaction.invoice_number
                            );

                            prependAuditLog('SIMULATOR_SANDBOX_TX_CREATED', data.transaction.transaction_ref);
                        } else {
                            alert(data.message || 'Gagal generate sandbox transaction');
                        }
                    })
                    .catch(err => {
                        btnGenDirect.disabled = false;
                        btnGenDirect.innerHTML = '<i class="fas fa-magic me-1"></i> Generate Sandbox Test Invoice';
                        alert('Network Error saat generate sandbox: ' + err);
                    });
                });
            }

            // Dropdown selection change
            if (selectTxDirect) {
                selectTxDirect.addEventListener('change', function() {
                    const selected = selectTxDirect.options[selectTxDirect.selectedIndex];
                    if (selected && selected.value) {
                        if (partnerRefDirect) partnerRefDirect.value = selected.value;
                        if (selected.dataset.amount && amountDirect) {
                            amountDirect.value = selected.dataset.amount;
                        }

                        // Update Live Ledger Indicators to reflect selected transaction
                        if (selected.dataset.txStatus && stateTxDirect) {
                            const st = selected.dataset.txStatus;
                            const cls = st === 'SETTLEMENT' ? 'bg-success' : (st === 'PENDING' ? 'bg-warning text-dark' : (st === 'EXPIRED' || st === 'CANCELLED' ? 'bg-secondary' : 'bg-danger'));
                            stateTxDirect.innerHTML = `<span class="badge ${cls}">${st}</span>`;
                        }
                        if (selected.dataset.invoiceStatus && stateInvoiceDirect) {
                            const invSt = selected.dataset.invoiceStatus;
                            const cls = invSt === 'PAID_SETTLED' ? 'bg-success' : (invSt === 'PENDING' ? 'bg-warning text-dark' : 'bg-danger');
                            stateInvoiceDirect.innerHTML = `<span class="badge ${cls}">${invSt}</span>`;
                        }
                        if (selected.dataset.escrowStatus && stateEscrowDirect) {
                            const escSt = selected.dataset.escrowStatus;
                            const cls = escSt === 'HELD_IN_ESCROW' ? 'bg-success' : 'bg-secondary';
                            const escAmt = selected.dataset.escrowAmount ? Number(selected.dataset.escrowAmount).toLocaleString('id-ID') : '0';
                            const text = escSt === 'HELD_IN_ESCROW' ? `HELD (Rp ${escAmt})` : escSt;
                            stateEscrowDirect.innerHTML = `<span class="badge ${cls}">${text}</span>`;
                        }
                    } else if (selected && !selected.value) {
                        if (partnerRefDirect) partnerRefDirect.value = '';
                        if (stateTxDirect) stateTxDirect.innerHTML = '—';
                        if (stateInvoiceDirect) stateInvoiceDirect.innerHTML = '—';
                        if (stateEscrowDirect) stateEscrowDirect.innerHTML = '—';
                    }
                });
            }

            // Webhook Dispatcher
            if (btnDispatchDirect) {
                btnDispatchDirect.addEventListener('click', function(e) {
                    e.preventDefault();
                    const ref = partnerRefDirect ? partnerRefDirect.value.trim() : '';
                    const amount = (amountDirect && amountDirect.value.trim() !== '') ? amountDirect.value.trim() : '6500000';
                    const scenarioRadio = document.querySelector('input[name="direct_scenario"]:checked');
                    const scenario = scenarioRadio ? scenarioRadio.value : 'SETTLEMENT';

                    if (!ref) {
                        alert('Mohon masukkan atau pilih Partner Reference No.');
                        if (partnerRefDirect) partnerRefDirect.focus();
                        return;
                    }

                    btnDispatchDirect.disabled = true;
                    btnDispatchDirect.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Dispatching Webhook...';
                    if (statusBadgeDirect) {
                        statusBadgeDirect.className = 'badge bg-secondary';
                        statusBadgeDirect.textContent = 'Sending...';
                    }

                    fetch('{{ route("admin.simulator.fire", [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            partnerReferenceNo: ref,
                            scenario: scenario,
                            amount: amount
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        btnDispatchDirect.disabled = false;
                        btnDispatchDirect.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Dispatch Simulated Webhook';

                        if (statusBadgeDirect) {
                            if (data.http_status === 200) {
                                statusBadgeDirect.className = 'badge bg-success';
                                statusBadgeDirect.textContent = `HTTP ${data.http_status} OK`;
                            } else {
                                statusBadgeDirect.className = 'badge bg-danger';
                                statusBadgeDirect.textContent = `HTTP ${data.http_status || 'ERR'}`;
                            }
                        }

                        // Render payload and response JSON
                        if (jsonDirectReq) {
                            jsonDirectReq.textContent = typeof data.request_payload === 'string'
                                ? data.request_payload
                                : JSON.stringify(data.request_payload, null, 2);
                        }
                        if (jsonDirectRes) {
                            jsonDirectRes.textContent = typeof data.response_payload === 'string'
                                ? data.response_payload
                                : JSON.stringify(data.response_payload, null, 2);
                        }

                        // Auto expand response collapse box
                        const collapseResEl = document.getElementById('collapseDirectResponse');
                        if (collapseResEl && window.bootstrap && window.bootstrap.Collapse) {
                            bootstrap.Collapse.getOrCreateInstance(collapseResEl).show();
                        }

                        // Update Live Ledger Indicators
                        if (data.ledger_state) {
                            const txStatus = data.ledger_state.transaction_status;
                            const invStatus = data.ledger_state.invoice_status;
                            const escStatus = data.ledger_state.escrow_status;
                            const escAmount = data.ledger_state.escrow_amount_held;

                            if (stateTxDirect) {
                                const txClass = txStatus === 'SETTLEMENT' ? 'bg-success' : (txStatus === 'PENDING' ? 'bg-warning text-dark' : (txStatus === 'EXPIRED' || txStatus === 'CANCELLED' ? 'bg-secondary' : 'bg-danger'));
                                stateTxDirect.innerHTML = `<span class="badge ${txClass}">${txStatus}</span>`;
                            }

                            if (stateInvoiceDirect) {
                                const invClass = invStatus === 'PAID_SETTLED' ? 'bg-success' : (invStatus === 'PENDING' ? 'bg-warning text-dark' : 'bg-danger');
                                stateInvoiceDirect.innerHTML = `<span class="badge ${invClass}">${invStatus}</span>`;
                            }

                            if (stateEscrowDirect) {
                                const escClass = escStatus === 'HELD_IN_ESCROW' ? 'bg-success' : 'bg-secondary';
                                const escText = escStatus === 'HELD_IN_ESCROW' ? `HELD (Rp ${Number(escAmount).toLocaleString('id-ID')})` : escStatus;
                                stateEscrowDirect.innerHTML = `<span class="badge ${escClass}">${escText}</span>`;
                            }
                        }

                        // Determine resolved status
                        const resolvedTxStatus = (data.ledger_state && data.ledger_state.transaction_status && data.ledger_state.transaction_status !== 'NOT_FOUND')
                            ? data.ledger_state.transaction_status
                            : scenario;

                        // Lookup invoice number if known
                        let invoiceNo = null;
                        if (selectTxDirect) {
                            for (let i = 0; i < selectTxDirect.options.length; i++) {
                                if (selectTxDirect.options[i].value === ref) {
                                    invoiceNo = selectTxDirect.options[i].dataset.invoice || null;
                                    break;
                                }
                            }
                        }

                        // Immediately update "Riwayat Transaksi Masuk" table on right column
                        updateRecentTransactionsTable(ref, resolvedTxStatus, amount, invoiceNo);

                        // Update or insert option in dropdown
                        if (selectTxDirect) {
                            let found = false;
                            for (let i = 0; i < selectTxDirect.options.length; i++) {
                                if (selectTxDirect.options[i].value === ref) {
                                    const inv = selectTxDirect.options[i].dataset.invoice || 'INV';
                                    const amt = selectTxDirect.options[i].dataset.amount || amount;
                                    selectTxDirect.options[i].textContent = `${ref} (${inv}) — IDR ${Number(amt).toLocaleString('id-ID')} [${resolvedTxStatus}]`;
                                    selectTxDirect.options[i].dataset.txStatus = resolvedTxStatus;
                                    if (data.ledger_state) {
                                        selectTxDirect.options[i].dataset.invoiceStatus = data.ledger_state.invoice_status;
                                        selectTxDirect.options[i].dataset.escrowStatus = data.ledger_state.escrow_status;
                                        selectTxDirect.options[i].dataset.escrowAmount = data.ledger_state.escrow_amount_held;
                                    }
                                    found = true;
                                    break;
                                }
                            }
                            if (!found) {
                                const opt = document.createElement('option');
                                opt.value = ref;
                                opt.dataset.amount = amount;
                                opt.dataset.invoice = 'MANUAL';
                                opt.dataset.txStatus = resolvedTxStatus;
                                if (data.ledger_state) {
                                    opt.dataset.invoiceStatus = data.ledger_state.invoice_status;
                                    opt.dataset.escrowStatus = data.ledger_state.escrow_status;
                                    opt.dataset.escrowAmount = data.ledger_state.escrow_amount_held;
                                }
                                opt.textContent = `${ref} (MANUAL) — IDR ${Number(amount).toLocaleString('id-ID')} [${resolvedTxStatus}]`;
                                if (selectTxDirect.children.length > 0 && selectTxDirect.children[0].value === '') {
                                    selectTxDirect.children[0].insertAdjacentElement('afterend', opt);
                                } else {
                                    selectTxDirect.prepend(opt);
                                }
                                selectTxDirect.value = ref;
                            }
                        }

                        prependAuditLog('WEBHOOK_SIMULATOR_DISPATCHED', ref);
                    })
                    .catch(err => {
                        btnDispatchDirect.disabled = false;
                        btnDispatchDirect.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Dispatch Simulated Webhook';
                        if (statusBadgeDirect) {
                            statusBadgeDirect.className = 'badge bg-danger';
                            statusBadgeDirect.textContent = 'Network Error';
                        }
                        if (jsonDirectRes) jsonDirectRes.textContent = String(err);
                        const collapseResEl = document.getElementById('collapseDirectResponse');
                        if (collapseResEl && window.bootstrap && window.bootstrap.Collapse) {
                            bootstrap.Collapse.getOrCreateInstance(collapseResEl).show();
                        }
                    });
                });
            }

            // Copy JSON buttons
            const btnCopyDirectReq = document.getElementById('btnCopyDirectReq');
            const btnCopyDirectRes = document.getElementById('btnCopyDirectRes');

            if (btnCopyDirectReq && jsonDirectReq) {
                btnCopyDirectReq.addEventListener('click', function() {
                    copyDirectJson(jsonDirectReq.textContent, btnCopyDirectReq);
                });
            }

            if (btnCopyDirectRes && jsonDirectRes) {
                btnCopyDirectRes.addEventListener('click', function() {
                    copyDirectJson(jsonDirectRes.textContent, btnCopyDirectRes);
                });
            }
        });
    </script>
@endsection
