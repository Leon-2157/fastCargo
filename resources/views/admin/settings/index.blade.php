@extends('layouts.dashboard')

@section('title', 'Platform Settings & AI Configuration')

@section('content')
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-dark mb-0 fw-bold">Platform Governance &amp; AI Configuration</h3>
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
            <div class="card shadow border-start-primary">
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

        <!-- Operator Security & VPN Whitelist -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow border-start-warning">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-shield-alt me-1"></i> Operator Terminal
                        VPN Whitelist</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.update', [], false) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted" for="operator_vpn_whitelist">Allowed
                                Operator VPN CIDR Ranges (JSON array)</label>
                            <textarea id="operator_vpn_whitelist" name="operator_vpn_whitelist" rows="3"
                                class="form-control font-monospace" required>{{ $settings['operator_vpn_whitelist']->value ?? '["10.0.0.0/8", "172.16.0.0/12", "192.168.0.0/16"]' }}</textarea>
                            <small class="text-muted">Requests to
                                <code>{{ config('app.domains.operator', 'operator.fastcargo.local') }}</code> from outside
                                these CIDRs receive HTTP 403 Forbidden when IP whitelist enforcement is enabled.</small>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="platform_fee_percent">Platform Fee
                                    (%)</label>
                                <input id="platform_fee_percent" type="number" name="platform_fee_percent"
                                    value="{{ $settings['platform_fee_percent']->value ?? 12 }}" class="form-control"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted"
                                    for="escrow_milestone_1_percent">Milestone 1 Gate-In (%)</label>
                                <input id="escrow_milestone_1_percent" type="number" name="escrow_milestone_1_percent"
                                    value="{{ $settings['escrow_milestone_1_percent']->value ?? 30 }}"
                                    class="form-control" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted"
                                    for="escrow_milestone_2_percent">Milestone 2 Sailing (%)</label>
                                <input id="escrow_milestone_2_percent" type="number" name="escrow_milestone_2_percent"
                                    value="{{ $settings['escrow_milestone_2_percent']->value ?? 40 }}"
                                    class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted"
                                    for="escrow_milestone_3_percent">Milestone 3 Delivery (%)</label>
                                <input id="escrow_milestone_3_percent" type="number" name="escrow_milestone_3_percent"
                                    value="{{ $settings['escrow_milestone_3_percent']->value ?? 30 }}"
                                    class="form-control" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline-warning text-dark btn-sm fw-bold">
                            <i class="fas fa-save me-1"></i> Save Security &amp; Escrow Rules
                        </button>
                    </form>
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

            let aiFormIsDirty = false;
            let aiConnectionVerified = true;

            if (providerSelect) {
                providerSelect.addEventListener('change', function() {
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
        });
    </script>
@endsection
