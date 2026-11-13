@extends('layouts.dashboard')

@section('title', 'Platform Settings & System Integrations')

@section('content')
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-dark mb-0 fw-bold">Platform Settings &amp; System Integrations</h3>
            <p class="text-muted small mb-0">Manage AI Intelligence Engines, WhatsApp Go Gateway pairing, and Resend SMTP email delivery.</p>
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
        <div class="col-lg-6 mb-4">
            <div class="card shadow border-start-primary h-100 d-flex flex-column">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-robot me-1"></i> AI Intelligence Engine
                    </h6>
                    @php
                        $currentAi = $aiConfig['active_provider'] ?? 'custom';
                        $isGemini = in_array($currentAi, ['gemini'], true);
                    @endphp
                    <span id="activeAiBadge" class="badge {{ $isGemini ? 'bg-info text-dark' : 'bg-primary' }} px-3 py-2">
                        Active: {{ $aiConfig['active_label'] ?? strtoupper($currentAi) }}
                    </span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between flex-grow-1">
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

                    <form id="formAiSettings" method="POST" action="{{ route('admin.settings.update', [], false) }}"
                        class="d-flex flex-column justify-content-between flex-grow-1">
                        @csrf
                        @method('PUT')

                        <!-- SECTION GOOGLE GEMINI (Gambar 3) -->
                        <div id="aiSectionGemini" class="{{ $isGemini ? '' : 'd-none' }}">
                            <h6 class="small fw-bold text-uppercase text-info mb-2"><i class="fab fa-google me-1"></i>
                                Google Gemini API</h6>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1" for="gemini_api_key">Gemini API Key</label>
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
                                <label class="form-label small fw-bold text-muted mb-1" for="custom_ai_api_key">API Key</label>
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
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-auto">
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
        <div class="col-lg-6 mb-4">
            <div class="card shadow border-start-success h-100 d-flex flex-column">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-success"><i class="fab fa-whatsapp me-1"></i> WhatsApp Gateway Station</h6>
                    <div id="wag-status-badge">
                        @if(!($waConfig['gatewayStatus']['reachable'] ?? false))
                            <span class="badge bg-danger px-2 py-1 small"><i class="fas fa-plug me-1"></i> Offline</span>
                        @elseif($waConfig['isConnected'])
                            <span class="badge bg-success px-2 py-1 small"><i class="fas fa-check-circle me-1"></i> Connected</span>
                        @else
                            <span class="badge bg-warning text-dark px-2 py-1 small"><i class="fas fa-qrcode me-1"></i> Pairing Needed</span>
                        @endif
                    </div>
                </div>

                <div class="card-body p-3 d-flex flex-column justify-content-between flex-grow-1">
                    <!-- BARIS ATAS: 2 KOTAK BERSEBELAHAN -->
                    <div class="row g-2 mb-3">
                        <!-- Kotak Kiri: QR Code / Status Perangkat -->
                        <div class="col-sm-6">
                            <div id="wag-left-box" class="border rounded p-2 bg-light d-flex flex-column align-items-center justify-content-center text-center h-100 shadow-sm" style="min-height: 180px;">
                                @if($waConfig['qrSvg'])
                                    <div class="p-1 bg-white rounded border mb-1 d-flex align-items-center justify-content-center" style="width: 122px; height: 122px;">
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
                                        {{ $waConfig['gatewayStatus']['user']['name'] ?? 'Device Connected' }}
                                    </div>
                                    <code class="text-muted" style="font-size: 10px;">+{{ $waConfig['gatewayStatus']['user']['phone'] ?? '' }}</code>
                                @elseif(!($waConfig['gatewayStatus']['reachable'] ?? false))
                                    <div class="text-danger mb-2">
                                        <i class="fas fa-plug fa-3x"></i>
                                    </div>
                                    <span class="badge bg-danger px-2 py-1 mb-1 small">waG Offline</span>
                                    <div class="text-muted" style="font-size: 10px;">Layanan tidak dapat dijangkau</div>
                                @else
                                    <div class="spinner-border text-warning mb-2" role="status" style="width: 2rem; height: 2rem;"></div>
                                    <div class="fw-bold text-dark" style="font-size: 11px;">Menunggu Kode QR...</div>
                                @endif
                            </div>
                        </div>

                        <!-- Kotak Kanan: Info Detail Layanan & Sesi -->
                        <div class="col-sm-6">
                            <div id="wag-right-box" class="border rounded p-3 bg-white d-flex flex-column justify-content-between h-100 shadow-sm" style="min-height: 180px;">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 10px;">
                                            <i class="fas fa-info-circle me-1 text-info"></i> Info Detail
                                        </span>
                                        <span id="wag-service-badge" class="badge bg-light text-dark border small" style="font-size: 9px;">
                                            {{ $waConfig['gatewayStatus']['service'] ?? 'waG' }}
                                        </span>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted" style="font-size: 10px;">Status Layanan:</div>
                                        <div id="wag-detail-status" class="fw-bold small {{ $waConfig['isConnected'] ? 'text-success' : (($waConfig['gatewayStatus']['reachable'] ?? false) ? 'text-warning' : 'text-danger') }}">
                                            <i class="fas {{ $waConfig['isConnected'] ? 'fa-check-circle' : (($waConfig['gatewayStatus']['reachable'] ?? false) ? 'fa-hourglass-half' : 'fa-times-circle') }} me-1"></i>
                                            {{ $waConfig['isConnected'] ? 'Online & Terhubung' : (($waConfig['gatewayStatus']['reachable'] ?? false) ? 'Menunggu Pairing' : 'Offline / Terputus') }}
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted" style="font-size: 10px;">Nomor Aktif:</div>
                                        <div id="wag-detail-phone" class="fw-bold small text-dark">
                                            @if(!empty($waConfig['gatewayStatus']['user']['phone']))
                                                <code>+{{ $waConfig['gatewayStatus']['user']['phone'] }}</code>
                                            @else
                                                <span class="text-muted fst-italic">Belum Terhubung</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-muted" style="font-size: 10px;">Waktu Pairing:</div>
                                        <div id="wag-detail-paired-at" class="small text-secondary fw-semibold">
                                            <i class="far fa-clock me-1"></i> {{ $waConfig['pairedAt'] ?? 'Menunggu scan' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-1 mt-1 border-top d-flex justify-content-between align-items-center">
                                    <span class="text-muted" style="font-size: 9px;">Gateway:</span>
                                    <code style="font-size: 9.5px;">{{ $waConfig['gatewayBaseUrl'] }}</code>
                                </div>
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
                                <span class="text-muted" style="font-size: 10px;">Live Direct Dispatch</span>
                            </div>

                            <div id="waTestAlertBox" class="alert py-1 px-2 small mb-2 d-none" role="alert"></div>

                            <div class="row g-2 mb-2">
                                <div class="col-sm-5">
                                    <label class="form-label mb-1 text-muted" style="font-size: 10px;" for="wa_direct_phone">Nomor Tujuan (E.164 / 08xx)</label>
                                    <input id="wa_direct_phone" type="text" name="phone" value="{{ old('phone', '+62811000003') }}" class="form-control form-control-sm" placeholder="+628123456789" required>
                                </div>
                                <div class="col-sm-7">
                                    <label class="form-label mb-1 text-muted" style="font-size: 10px;" for="wa_direct_message">Pesan Notifikasi</label>
                                    <textarea id="wa_direct_message" name="message" rows="2" class="form-control form-control-sm" placeholder="Isi pesan uji coba..." required>[FastCargo Alert] Test notifikasi WhatsApp dari Platform Settings.</textarea>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <button id="btnWaTestSubmit" type="submit" class="btn btn-success btn-sm px-3">
                                    <i class="fas fa-paper-plane me-1"></i> Send Test Message
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('formWaResetDirect').submit();">
                                    <i class="fas fa-redo me-1"></i> Reset Session
                                </button>
                            </div>
                        </form>
                        <form id="formWaResetDirect" method="POST" action="{{ route('admin.wa.reset', [], false) }}" class="d-none">
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
                            Engine (Resend SMTP &amp; Verification)</h6>
                        <small class="text-muted">Powers Email Verification, Password Reset, and 2-Factor Authentication
                            Emergency OTP.</small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-secondary">Driver: {{ strtoupper($mailConfig['mail_mailer']) }}</span>
                        <span class="badge {{ $mailConfig['is_password_configured'] ? 'bg-success' : 'bg-danger' }}">
                            {{ $mailConfig['is_password_configured'] ? 'API Key Configured' : 'API Key Not Set' }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Configuration Form -->
                        <div class="col-lg-8 border-end pe-lg-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="small fw-bold text-uppercase text-muted mb-0">SMTP Server Configuration</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-preset-resend">
                                    <i class="fas fa-magic me-1"></i> Auto-fill Resend SMTP Preset
                                </button>
                            </div>

                            <form method="POST" action="{{ route('admin.settings.update', [], false) }}">
                                @csrf
                                @method('PUT')

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted" for="mail_mailer">Mail
                                            Driver</label>
                                        <select id="mail_mailer" name="mail_mailer" class="form-select">
                                            <option value="smtp"
                                                {{ $mailConfig['mail_mailer'] === 'smtp' ? 'selected' : '' }}>SMTP
                                                (Recommended / Resend)</option>
                                            <option value="log"
                                                {{ $mailConfig['mail_mailer'] === 'log' ? 'selected' : '' }}>Local Log
                                                (Debug only)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold text-muted" for="mail_host">SMTP
                                            Host</label>
                                        <input id="mail_host" type="text" name="mail_host"
                                            value="{{ old('mail_host', $mailConfig['mail_host']) }}" class="form-control"
                                            placeholder="smtp.resend.com">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold text-muted" for="mail_port">Port</label>
                                        <input id="mail_port" type="number" name="mail_port"
                                            value="{{ old('mail_port', $mailConfig['mail_port']) }}" class="form-control"
                                            placeholder="465">
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted"
                                            for="mail_encryption">Encryption</label>
                                        <select id="mail_encryption" name="mail_encryption" class="form-select">
                                            <option value="tls"
                                                {{ $mailConfig['mail_encryption'] === 'tls' ? 'selected' : '' }}>TLS /
                                                STARTTLS (Port 587)</option>
                                            <option value="ssl"
                                                {{ $mailConfig['mail_encryption'] === 'ssl' ? 'selected' : '' }}>SSL /
                                                SMTPS (Port 465)</option>
                                            <option value="none"
                                                {{ $mailConfig['mail_encryption'] === 'none' ? 'selected' : '' }}>None
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted" for="mail_username">SMTP
                                            Username</label>
                                        <input id="mail_username" type="text" name="mail_username"
                                            value="{{ old('mail_username', $mailConfig['mail_username']) }}"
                                            class="form-control" placeholder="resend">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-muted" for="mail_password">Resend API
                                            Key (Password)</label>
                                        <input id="mail_password" type="password" name="mail_password" value=""
                                            class="form-control" autocomplete="new-password"
                                            placeholder="{{ $mailConfig['is_password_configured'] ? 'Configured (leave blank to keep)' : 're_xxxxxxxxxxxx' }}">
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted" for="mail_from_address">From
                                            Address</label>
                                        <input id="mail_from_address" type="email" name="mail_from_address"
                                            value="{{ old('mail_from_address', $mailConfig['mail_from_address']) }}"
                                            class="form-control" placeholder="noreply@leonproject.my.id">
                                        <small class="text-muted">Must use verified domain from Resend (e.g.
                                            <code>noreply@leonproject.my.id</code>).</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted" for="mail_from_name">From
                                            Sender Name</label>
                                        <input id="mail_from_name" type="text" name="mail_from_name"
                                            value="{{ old('mail_from_name', $mailConfig['mail_from_name']) }}"
                                            class="form-control" placeholder="FastCargo">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-outline-success btn-sm fw-bold">
                                    <i class="fas fa-save me-1"></i> Save Mail Settings
                                </button>
                            </form>
                        </div>

                        <!-- Send Live Test Email Card -->
                        <div class="col-lg-4 ps-lg-4 mt-4 mt-lg-0 d-flex flex-column justify-content-between">
                            <div>
                                <h6 class="small fw-bold text-uppercase text-primary mb-2">Live Delivery Verification</h6>
                                <p class="text-muted small">
                                    Verify that your Resend DNS records and API key are operating properly by sending an
                                    instant test email.
                                </p>

                                <form method="POST" action="{{ route('admin.settings.mail.test', [], false) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-muted" for="test_email">Recipient
                                            Email Address</label>
                                        <input id="test_email" type="email" name="test_email"
                                            value="{{ Auth::guard('admin')->user()?->email ?? 'mtaufiq2157@gmail.com' }}"
                                            class="form-control" placeholder="your-email@gmail.com" required>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100 fw-bold py-2">
                                        <i class="fas fa-paper-plane me-1"></i> Dispatch Test Email
                                    </button>
                                </form>
                            </div>

                            <div class="alert alert-light border small mt-4 mb-0 text-muted">
                                <i class="fas fa-info-circle text-primary me-1"></i>
                                <strong>DNS Tip:</strong> Ensure DKIM, SPF (TXT), and MX records are set to <em>DNS
                                    Only</em> (Gray Cloud) in Cloudflare.
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
            // Preset Resend
            const presetBtn = document.getElementById('btn-preset-resend');
            if (presetBtn) {
                presetBtn.addEventListener('click', function() {
                    document.getElementById('mail_mailer').value = 'smtp';
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
                    fetch(wagStatusUrl, { headers: { 'Accept': 'application/json' } })
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            if (!data.reachable) {
                                wagBadgeEl.innerHTML = '<span class="badge bg-danger px-2 py-1 small"><i class="fas fa-plug me-1"></i> Offline</span>';
                                if (wagDetailStatus) {
                                    wagDetailStatus.className = 'fw-bold small text-danger';
                                    wagDetailStatus.innerHTML = '<i class="fas fa-times-circle me-1"></i> Offline / Terputus';
                                }
                                if (wagDetailPhone) wagDetailPhone.innerHTML = '<span class="text-muted fst-italic">Belum Terhubung</span>';
                                if (wagDetailPairedAt) wagDetailPairedAt.innerHTML = '<i class="far fa-clock me-1"></i> Menunggu scan';
                                wagLeftBox.innerHTML = `
                                    <div class="text-danger mb-2"><i class="fas fa-plug fa-3x"></i></div>
                                    <span class="badge bg-danger px-2 py-1 mb-1 small">waG Offline</span>
                                    <div class="text-muted" style="font-size: 10px;">Layanan tidak dapat dijangkau</div>
                                `;
                            } else if (data.connected) {
                                wagBadgeEl.innerHTML = '<span class="badge bg-success px-2 py-1 small"><i class="fas fa-check-circle me-1"></i> Connected</span>';
                                const userName = (data.user && data.user.name) ? data.user.name : 'Device Connected';
                                const userPhone = (data.user && data.user.phone) ? '+' + data.user.phone : '';
                                if (wagDetailStatus) {
                                    wagDetailStatus.className = 'fw-bold small text-success';
                                    wagDetailStatus.innerHTML = '<i class="fas fa-check-circle me-1"></i> Online &amp; Terhubung';
                                }
                                if (wagDetailPhone) {
                                    wagDetailPhone.innerHTML = userPhone ? `<code>${userPhone}</code>` : '<span class="text-muted fst-italic">Terhubung</span>';
                                }
                                if (wagDetailPairedAt && data.paired_at) {
                                    wagDetailPairedAt.innerHTML = `<i class="far fa-clock me-1"></i> ${data.paired_at}`;
                                }
                                wagLeftBox.innerHTML = `
                                    <div class="text-success mb-2"><i class="fab fa-whatsapp fa-3x"></i></div>
                                    <span class="badge bg-success px-2 py-1 mb-1 small"><i class="fas fa-check-circle me-1"></i> Sesi Terhubung</span>
                                    <div class="small fw-bold text-dark mb-0" style="font-size: 11px;">${userName}</div>
                                    <code class="text-muted" style="font-size: 10px;">${userPhone}</code>
                                `;
                            } else if (data.qr_svg) {
                                wagBadgeEl.innerHTML = '<span class="badge bg-warning text-dark px-2 py-1 small"><i class="fas fa-qrcode me-1"></i> Pairing Needed</span>';
                                if (wagDetailStatus) {
                                    wagDetailStatus.className = 'fw-bold small text-warning';
                                    wagDetailStatus.innerHTML = '<i class="fas fa-hourglass-half me-1"></i> Menunggu Pairing';
                                }
                                if (wagDetailPhone) wagDetailPhone.innerHTML = '<span class="text-muted fst-italic">Belum Terhubung</span>';
                                if (wagDetailPairedAt) wagDetailPairedAt.innerHTML = '<i class="far fa-clock me-1"></i> Menunggu scan';
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
                    btnWaTestSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...';

                    if (waTestAlertBox) {
                        waTestAlertBox.className = 'alert alert-info py-1 px-2 small mb-2';
                        waTestAlertBox.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Mengirim notifikasi WhatsApp via gateway...';
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
                            return { ok: res.ok, data: data };
                        });
                    })
                    .then(function(result) {
                        btnWaTestSubmit.disabled = false;
                        btnWaTestSubmit.innerHTML = originalHtml;

                        if (result.ok && result.data.success) {
                            if (waTestAlertBox) {
                                waTestAlertBox.className = 'alert alert-success py-1 px-2 small mb-2';
                                waTestAlertBox.innerHTML = '<i class="fas fa-check-circle me-1"></i> <strong>Berhasil:</strong> ' + result.data.message;
                            }
                        } else {
                            if (waTestAlertBox) {
                                waTestAlertBox.className = 'alert alert-danger py-1 px-2 small mb-2';
                                waTestAlertBox.innerHTML = '<i class="fas fa-times-circle me-1"></i> <strong>Gagal:</strong> ' + (result.data.message || 'Gagal mengirim pesan.');
                            }
                        }
                    })
                    .catch(function(err) {
                        btnWaTestSubmit.disabled = false;
                        btnWaTestSubmit.innerHTML = originalHtml;
                        if (waTestAlertBox) {
                            waTestAlertBox.className = 'alert alert-danger py-1 px-2 small mb-2';
                            waTestAlertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> <strong>Error:</strong> ' + (err.message || 'Gagal menghubungi server.');
                        }
                    });
                });
            }
        });
    </script>
@endsection
