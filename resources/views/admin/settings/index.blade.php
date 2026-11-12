@extends('layouts.dashboard')

@section('title', 'Platform Settings & AI Configuration')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="text-dark mb-0 fw-bold">Platform Governance &amp; AI Configuration</h3>
    <p class="text-muted small mb-0">Manage 9router / Gemini AI providers, operator VPN IP whitelists, and escrow milestone splits.</p>
  </div>
</div>

@if($errors->any())
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
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-robot me-1"></i> AI Intelligence Engine (FastCargo AI)</h6>
        @php
          $currentAi = $aiConfig['active_provider'] ?? '9router';
        @endphp
        <span class="badge {{ $currentAi === '9router' ? 'bg-primary' : 'bg-info text-dark' }} px-3 py-2">
          Active: {{ $aiConfig['active_label'] ?? strtoupper($currentAi) }}
        </span>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.ai.switch', [], false) }}" class="mb-4">
          @csrf
          <label class="form-label small fw-bold text-muted" for="ai_provider_select">Select Active AI Provider Engine</label>
          <div class="input-group">
            <select id="ai_provider_select" name="ai_provider" class="form-select">
              <option value="9router" {{ $currentAi === '9router' ? 'selected' : '' }}>
                9router Proxy ({{ $aiConfig['ninerouter_model'] }})
              </option>
              <option value="gemini" {{ $currentAi === 'gemini' ? 'selected' : '' }}>
                Direct Google Gemini API ({{ $aiConfig['gemini_model'] }})
              </option>
            </select>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-sync-alt me-1"></i> Switch Engine
            </button>
          </div>
          <small class="text-muted">Switches active provider across all dashboards immediately without restarting services.</small>
        </form>

        <form method="POST" action="{{ route('admin.settings.update', [], false) }}">
          @csrf
          @method('PUT')

          <h6 class="small fw-bold text-uppercase text-primary mb-2">9router Gateway</h6>
          <div class="mb-3">
            <label class="form-label small fw-bold text-muted" for="ninerouter_base_url">9router Base URL</label>
            <input id="ninerouter_base_url" type="url" name="ninerouter_base_url" value="{{ old('ninerouter_base_url', $aiConfig['ninerouter_base_url']) }}" class="form-control" placeholder="http://host.containers.internal:20128/v1">
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small fw-bold text-muted mb-0" for="ninerouter_api_key">9router API Key</label>
              <span class="badge {{ $aiConfig['ninerouter_key_configured'] ? 'bg-success' : 'bg-secondary' }}">
                {{ $aiConfig['ninerouter_key_configured'] ? 'Configured' : 'Not Set' }}
              </span>
            </div>
            <input id="ninerouter_api_key" type="password" name="ninerouter_api_key" value="" class="form-control" autocomplete="new-password" placeholder="{{ $aiConfig['ninerouter_key_configured'] ? 'Configured (leave blank to keep current key)' : 'Enter 9router API key' }}">
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold text-muted" for="ninerouter_model">9router Model Identifier</label>
            <input id="ninerouter_model" type="text" name="ninerouter_model" value="{{ old('ninerouter_model', $aiConfig['ninerouter_model']) }}" class="form-control">
          </div>

          <hr class="my-3">
          <h6 class="small fw-bold text-uppercase text-info mb-2">Direct Google Gemini API (Primary or Fallback)</h6>

          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small fw-bold text-muted mb-0" for="gemini_api_key">Gemini API Key</label>
              <span class="badge {{ $aiConfig['gemini_key_configured'] ? 'bg-success' : 'bg-secondary' }}">
                {{ $aiConfig['gemini_key_configured'] ? 'Configured' : 'Not Set' }}
              </span>
            </div>
            <input id="gemini_api_key" type="password" name="gemini_api_key" value="" class="form-control" autocomplete="new-password" placeholder="{{ $aiConfig['gemini_key_configured'] ? 'Configured (leave blank to keep current key)' : 'Enter Google Gemini API key' }}">
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold text-muted" for="gemini_model">Gemini Model Identifier</label>
            <input id="gemini_model" type="text" name="gemini_model" value="{{ old('gemini_model', $aiConfig['gemini_model']) }}" class="form-control" placeholder="gemini-2.0-flash">
            <small class="text-muted">FastCargo AI Assistant uses active engine settings for live chat &amp; operational summaries.</small>
          </div>

          <button type="submit" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-save me-1"></i> Save AI Engine Settings
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Operator Security & VPN Whitelist -->
  <div class="col-lg-6 mb-4">
    <div class="card shadow border-start-warning">
      <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-shield-alt me-1"></i> Operator Terminal VPN Whitelist</h6>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.update', [], false) }}">
          @csrf
          @method('PUT')

          <div class="mb-3">
            <label class="form-label small fw-bold text-muted" for="operator_vpn_whitelist">Allowed Operator VPN CIDR Ranges (JSON array)</label>
            <textarea id="operator_vpn_whitelist" name="operator_vpn_whitelist" rows="3" class="form-control font-monospace" required>{{ $settings['operator_vpn_whitelist']->value ?? '["10.0.0.0/8", "172.16.0.0/12", "192.168.0.0/16"]' }}</textarea>
            <small class="text-muted">Requests to <code>{{ config('app.domains.operator', 'operator.fastcargo.local') }}</code> from outside these CIDRs receive HTTP 403 Forbidden when IP whitelist enforcement is enabled.</small>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted" for="platform_fee_percent">Platform Fee (%)</label>
              <input id="platform_fee_percent" type="number" name="platform_fee_percent" value="{{ $settings['platform_fee_percent']->value ?? 12 }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted" for="escrow_milestone_1_percent">Milestone 1 Gate-In (%)</label>
              <input id="escrow_milestone_1_percent" type="number" name="escrow_milestone_1_percent" value="{{ $settings['escrow_milestone_1_percent']->value ?? 30 }}" class="form-control" required>
            </div>
          </div>

          <div class="row g-2 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted" for="escrow_milestone_2_percent">Milestone 2 Sailing (%)</label>
              <input id="escrow_milestone_2_percent" type="number" name="escrow_milestone_2_percent" value="{{ $settings['escrow_milestone_2_percent']->value ?? 40 }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-muted" for="escrow_milestone_3_percent">Milestone 3 Delivery (%)</label>
              <input id="escrow_milestone_3_percent" type="number" name="escrow_milestone_3_percent" value="{{ $settings['escrow_milestone_3_percent']->value ?? 30 }}" class="form-control" required>
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
          <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-envelope me-1"></i> Email Delivery Engine (Resend SMTP &amp; Verification)</h6>
          <small class="text-muted">Powers Email Verification, Password Reset, and 2-Factor Authentication Emergency OTP.</small>
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
                  <label class="form-label small fw-bold text-muted" for="mail_mailer">Mail Driver</label>
                  <select id="mail_mailer" name="mail_mailer" class="form-select">
                    <option value="smtp" {{ $mailConfig['mail_mailer'] === 'smtp' ? 'selected' : '' }}>SMTP (Recommended / Resend)</option>
                    <option value="log" {{ $mailConfig['mail_mailer'] === 'log' ? 'selected' : '' }}>Local Log (Debug only)</option>
                  </select>
                </div>
                <div class="col-md-5">
                  <label class="form-label small fw-bold text-muted" for="mail_host">SMTP Host</label>
                  <input id="mail_host" type="text" name="mail_host" value="{{ old('mail_host', $mailConfig['mail_host']) }}" class="form-control" placeholder="smtp.resend.com">
                </div>
                <div class="col-md-3">
                  <label class="form-label small fw-bold text-muted" for="mail_port">Port</label>
                  <input id="mail_port" type="number" name="mail_port" value="{{ old('mail_port', $mailConfig['mail_port']) }}" class="form-control" placeholder="465">
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label small fw-bold text-muted" for="mail_encryption">Encryption</label>
                  <select id="mail_encryption" name="mail_encryption" class="form-select">
                    <option value="tls" {{ $mailConfig['mail_encryption'] === 'tls' ? 'selected' : '' }}>TLS / STARTTLS (Port 587)</option>
                    <option value="ssl" {{ $mailConfig['mail_encryption'] === 'ssl' ? 'selected' : '' }}>SSL / SMTPS (Port 465)</option>
                    <option value="none" {{ $mailConfig['mail_encryption'] === 'none' ? 'selected' : '' }}>None</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold text-muted" for="mail_username">SMTP Username</label>
                  <input id="mail_username" type="text" name="mail_username" value="{{ old('mail_username', $mailConfig['mail_username']) }}" class="form-control" placeholder="resend">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold text-muted" for="mail_password">Resend API Key (Password)</label>
                  <input id="mail_password" type="password" name="mail_password" value="" class="form-control" autocomplete="new-password" placeholder="{{ $mailConfig['is_password_configured'] ? 'Configured (leave blank to keep)' : 're_xxxxxxxxxxxx' }}">
                </div>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label small fw-bold text-muted" for="mail_from_address">From Address</label>
                  <input id="mail_from_address" type="email" name="mail_from_address" value="{{ old('mail_from_address', $mailConfig['mail_from_address']) }}" class="form-control" placeholder="noreply@leonproject.my.id">
                  <small class="text-muted">Must use verified domain from Resend (e.g. <code>noreply@leonproject.my.id</code>).</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold text-muted" for="mail_from_name">From Sender Name</label>
                  <input id="mail_from_name" type="text" name="mail_from_name" value="{{ old('mail_from_name', $mailConfig['mail_from_name']) }}" class="form-control" placeholder="FastCargo">
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
                Verify that your Resend DNS records and API key are operating properly by sending an instant test email.
              </p>

              <form method="POST" action="{{ route('admin.settings.mail.test', [], false) }}">
                @csrf
                <div class="mb-3">
                  <label class="form-label small fw-bold text-muted" for="test_email">Recipient Email Address</label>
                  <input id="test_email" type="email" name="test_email" value="{{ Auth::guard('admin')->user()?->email ?? 'mtaufiq2157@gmail.com' }}" class="form-control" placeholder="your-email@gmail.com" required>
                </div>
                <button type="submit" class="btn btn-success w-100 fw-bold py-2">
                  <i class="fas fa-paper-plane me-1"></i> Dispatch Test Email
                </button>
              </form>
            </div>

            <div class="alert alert-light border small mt-4 mb-0 text-muted">
              <i class="fas fa-info-circle text-primary me-1"></i>
              <strong>DNS Tip:</strong> Ensure DKIM, SPF (TXT), and MX records are set to <em>DNS Only</em> (Gray Cloud) in Cloudflare.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>
@endsection
