@extends('layouts.landing')

@section('title', 'FastCargo — ASEAN Cross-Border Freight & Logistics Operating System')

@section('content')
  <!-- ===== HERO SECTION ===== -->
  <section class="hero" id="top" aria-labelledby="hero-title">
    <div class="hero-bg" aria-hidden="true">
      <img src="/assets/landing/img/hero.jpg" alt="" loading="eager" decoding="async">
      <span class="hero-veil"></span>
      <span class="hero-grid"></span>
    </div>
    <div class="container hero-grid-layout">
      <div class="hero-copy">
        <a class="hero-badge" href="#services">
          <span class="dot" aria-hidden="true"></span>
          Now Live: 10 ASEAN Countries Multi-Modal Coverage
          <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M5 3l5 5-5 5" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <h1 id="hero-title">Unified ASEAN Freight Operations from One Cockpit.</h1>
        <p class="hero-lead">FastCargo orchestrates sea vessels, cargo airliners, and cross-border road fleets across Indonesia, Singapore, Malaysia, and the greater ASEAN corridor with automated customs clearance and escrow settlement.</p>

        <!-- Live Tracking Input in Hero -->
        <div class="card bg-dark bg-opacity-75 border-secondary border-opacity-50 p-3 mb-4" id="track">
          <form action="" method="GET" onsubmit="event.preventDefault(); const resi = document.getElementById('trackingInput').value.trim(); if(resi) window.location.href='/track/'+encodeURIComponent(resi);">
            <label class="form-label text-light small mb-1" for="trackingInput">Instant Public Cargo Tracker</label>
            <div class="input-group">
              <input type="text" id="trackingInput" name="resi" class="form-control landing-input" placeholder="e.g. RPN-20261106-001" autocomplete="off" required>
              <button class="btn btn-primary px-4" type="submit">Track Shipment</button>
            </div>
          </form>
        </div>

        <div class="hero-actions">
          <a class="btn btn-primary btn-lg" href="{{ config('app.portal_urls.customer', 'http://customer.fastcargo.local:8080') }}/register">Start Shipping as Customer</a>
          <a class="btn btn-play" href="#operator-apply">
            <span class="play-ring" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="16" height="16"><path d="M9 7.5v9l7-4.5-7-4.5Z" fill="currentColor"/></svg>
            </span>
            Apply as Operator Hub
          </a>
        </div>
        <ul class="hero-trust">
          <li><svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="M5 10.5l3 3 7-7.5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>Automated Escrow</li>
          <li><svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="M5 10.5l3 3 7-7.5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>Live IoT Telemetry</li>
          <li><svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="M5 10.5l3 3 7-7.5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>24/7 AI Logistics Agent</li>
        </ul>
      </div>

      <!-- Live Cockpit Mockup Preview -->
      <div class="hero-mock reveal" data-reveal>
        <div class="mock-window">
          <div class="mock-chrome">
            <span class="tl tl-r"></span><span class="tl tl-y"></span><span class="tl tl-g"></span>
            <span class="mock-url">ops.fastcargo.internal / fleet / telemetry / asean-corridor</span>
          </div>
          <div class="mock-body text-white">
            <div class="mock-side" aria-hidden="true">
              <span class="ms-logo"></span>
              <span class="ms-item is-active"></span>
              <span class="ms-item"></span>
              <span class="ms-item short"></span>
            </div>
            <div class="mock-main">
              <div class="d-flex justify-content-between align-items-center gap-2">
                <span class="text-info fw-bold small">LIVE FLEET TELEMETRY — MALACCA STRAIT</span>
                <span class="badge bg-success flex-shrink-0">NETWORK ONLINE</span>
              </div>
              <div class="p-3 bg-black bg-opacity-50 rounded border border-secondary border-opacity-25 font-monospace small">
                <div class="text-success">&gt; KM Sora Bahari 08 [VESSEL] — SPD: 18.2 kts | HDG: 042°</div>
                <div class="text-light opacity-75">&gt; Route: Tanjung Priok (IDJKT) &rarr; Singapore PSA (SGSIN)</div>
                <div class="text-info">&gt; Est. Arrival: 04:30 UTC | Status: SAILING (Normal Weather)</div>
              </div>
              <div class="mock-kpis">
                <div class="kpi">
                  <span class="kpi-label">ASEAN Ports</span>
                  <span class="kpi-value">50+</span>
                </div>
                <div class="kpi">
                  <span class="kpi-label">SLA On-Time</span>
                  <span class="kpi-value up">99.8%</span>
                </div>
                <div class="kpi">
                  <span class="kpi-label">Instant Dana Pay</span>
                  <span class="kpi-chip ok">QRIS Ready</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== ASEAN NETWORK COVERAGE ===== -->
  <section class="py-5 bg-dark" id="services">
    <div class="container py-4">
      <div class="text-center mb-5">
        <span class="badge bg-primary px-3 py-2 text-uppercase mb-2">Network Grid</span>
        <h2 class="text-white">Connecting 10 ASEAN Nations</h2>
        <p class="text-light opacity-75 max-w-xl mx-auto">Seamless freight forwarding across major seaports, international airports, and overland trade gateways.</p>
      </div>

      <div class="row g-4 text-white">
        <div class="col-md-4">
          <div class="card h-100 border-secondary border-opacity-25 p-4" style="background: #131c31;">
            <div class="mb-3 text-primary fs-3">🚢</div>
            <h4 class="text-white mb-2">Ocean Freight (LCL &amp; FCL)</h4>
            <p class="text-light opacity-75 small mb-0">Consolidated shipping across Jakarta, Surabaya, Singapore, Port Klang, Bangkok, and Manila with automated manifest locking and ISO container tracking.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 border-secondary border-opacity-25 p-4" style="background: #131c31;">
            <div class="mb-3 text-info fs-3">✈️</div>
            <h4 class="text-white mb-2">Air Express Cargo</h4>
            <p class="text-light opacity-75 small mb-0">Time-critical priority air cargo between CGK, SIN, KUL, BKK, SGN, and MNL international hubs with automated customs green-channel coordination.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 border-secondary border-opacity-25 p-4" style="background: #131c31;">
            <div class="mb-3 text-success fs-3">🚚</div>
            <h4 class="text-white mb-2">Cross-Border Road Transport</h4>
            <p class="text-light opacity-75 small mb-0">Bonded overland trucking across Indonesia island networks, Sadao border, and Greater Mekong corridors with electronic Proof of Delivery (ePOD).</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== OPERATOR APPLICATION FORM ===== -->
  <section class="py-5" id="operator-apply" style="background: #090E17;">
    <div class="container py-4">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card bg-dark border-secondary border-opacity-50 p-4 p-md-5 text-white">
            <div class="text-center mb-4">
              <span class="badge bg-warning text-dark px-3 py-2 text-uppercase mb-2 fw-bold">Operator Network</span>
              <h3 class="text-white">Apply to Become a FastCargo Hub Operator</h3>
              <p class="text-light opacity-75 small">Are you a logistics warehouse, port handler, or fleet owner in ASEAN? Submit your application. Our administrative team will review your qualifications and deliver your secure registration token.</p>
            </div>

            @if(session('success'))
              <div class="alert alert-success bg-success bg-opacity-25 border-success text-white mb-4">
                {{ session('success') }}
              </div>
            @endif

            @if($errors->any())
              <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white mb-4">
                <ul class="mb-0 small">
                  @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            <form action="{{ route('operator.apply', [], false) }}" method="POST">
              @csrf
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small text-light" for="operator_name">Full Name (PIC)</label>
                  <input type="text" id="operator_name" name="name" class="form-control landing-input" value="{{ old('name') }}" placeholder="Captain or Station Manager name" autocomplete="name" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-light" for="operator_email">Business Email Address</label>
                  <input type="email" id="operator_email" name="email" class="form-control landing-input" value="{{ old('email') }}" placeholder="ops@yourcompany.com" autocomplete="email" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-light" for="operator_phone">Direct Phone / WhatsApp Number</label>
                  <input type="text" id="operator_phone" name="phone" class="form-control landing-input" value="{{ old('phone') }}" placeholder="+628123456789" autocomplete="tel" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-light" for="operator_hub_name">Hub / Company Legal Name</label>
                  <input type="text" id="operator_hub_name" name="hub_name" class="form-control landing-input" value="{{ old('hub_name') }}" placeholder="PT Nusantara Logistik Hub" autocomplete="organization" required>
                </div>
                <div class="col-md-12">
                  <label class="form-label small text-light" for="operator_city">Operating City &amp; Country</label>
                  <input type="text" id="operator_city" name="city" class="form-control landing-input" value="{{ old('city') }}" placeholder="Batam / Riau Islands, Indonesia" autocomplete="address-level2" required>
                </div>
                <div class="col-12 mt-4 text-center">
                  <button type="submit" class="btn btn-primary btn-lg w-100">Submit Application for Review</button>
                  <small class="text-light opacity-75 mt-2 d-block">Upon approval by FastCargo Compliance, you will receive an invitation token with encrypted registration credentials valid for 7 days.</small>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

