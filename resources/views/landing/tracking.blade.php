@extends('layouts.landing')

@section('title', 'Shipment Tracking #' . $booking->tracking_number . ' — FastCargo')
@section('body-class', 'page-dark')
@section('header-class', 'site-header--solid')

@push('styles')
<style>
  .tracking-hero-card {
    background: linear-gradient(145deg, #0e172a 0%, #090e1a 100%);
    border: 1px solid rgba(56, 189, 248, 0.22);
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(2, 6, 23, 0.75), 0 0 45px -10px rgba(56, 189, 248, 0.12);
    position: relative;
    overflow: hidden;
  }
  .tracking-hero-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #0ea5e9 0%, #0fb5a2 50%, #38bdf8 100%);
  }
  .waybill-code {
    font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    letter-spacing: 0.04em;
    color: #38bdf8;
    text-shadow: 0 0 20px rgba(56, 189, 248, 0.35);
  }
  .status-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 9999px;
    font-weight: 700;
    font-size: 0.88rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
  }
  .status-pill.status-sailing {
    background: #082f49;
    border: 1px solid #38bdf8;
    color: #7dd3fc;
    box-shadow: 0 0 20px -3px rgba(14, 165, 233, 0.4);
  }
  .status-pill.status-delivered {
    background: #064e3b;
    border: 1px solid #34d399;
    color: #a7f3d0;
    box-shadow: 0 0 20px -3px rgba(16, 185, 129, 0.4);
  }
  .status-pill.status-booked {
    background: #1e1b4b;
    border: 1px solid #818cf8;
    color: #c7d2fe;
    box-shadow: 0 0 20px -3px rgba(99, 102, 241, 0.4);
  }
  .status-pill.status-warning {
    background: #451a03;
    border: 1px solid #fbbf24;
    color: #fef3c7;
  }
  .pulse-beacon {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: currentColor;
    position: relative;
  }
  .pulse-beacon::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid currentColor;
    animation: beacon-ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
  }
  @keyframes beacon-ping {
    0% { transform: scale(0.9); opacity: 1; }
    75%, 100% { transform: scale(2.4); opacity: 0; }
  }
  .route-corridor {
    background: rgba(15, 23, 42, 0.65);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 24px;
    backdrop-filter: blur(8px);
  }
  .route-node {
    flex: 1;
    min-width: 220px;
  }
  .route-beam {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0 16px;
    min-width: 160px;
  }
  .route-line {
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, #0ea5e9, #0fb5a2);
    border-radius: 9999px;
    position: relative;
    box-shadow: 0 0 10px rgba(14, 165, 233, 0.5);
  }
  .transit-icon-badge {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #0f172a;
    border: 2px solid #38bdf8;
    border-radius: 50%;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    box-shadow: 0 0 15px rgba(56, 189, 248, 0.6);
    animation: transit-float 2.5s ease-in-out infinite;
  }
  @keyframes transit-float {
    0%, 100% { transform: translate(-50%, -50%) translateY(0); }
    50% { transform: translate(-50%, -50%) translateY(-3px); }
  }
  .timeline-stepper {
    position: relative;
    padding-left: 36px;
  }
  .timeline-stepper::before {
    content: '';
    position: absolute;
    left: 14px;
    top: 14px;
    bottom: 24px;
    width: 2px;
    background: linear-gradient(180deg, #38bdf8 0%, #0ea5e9 60%, rgba(255, 255, 255, 0.12) 100%);
  }
  .milestone-item {
    position: relative;
    margin-bottom: 24px;
  }
  .milestone-item:last-child {
    margin-bottom: 0;
  }
  .milestone-node {
    position: absolute;
    left: -36px;
    top: 4px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
  }
  .milestone-node.is-latest {
    background: #0284c7;
    color: #fff;
    border: 3px solid #38bdf8;
    box-shadow: 0 0 0 6px rgba(56, 189, 248, 0.22);
    animation: active-node-pulse 2s infinite;
  }
  @keyframes active-node-pulse {
    0% { box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(56, 189, 248, 0.1); }
    100% { box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.4); }
  }
  .milestone-node.is-completed {
    background: #0f172a;
    color: #38bdf8;
    border: 2px solid #0ea5e9;
  }
  .milestone-card {
    background: rgba(15, 23, 42, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 16px 20px;
    transition: transform 0.2s ease, border-color 0.2s ease;
  }
  .milestone-card:hover {
    border-color: rgba(56, 189, 248, 0.35);
    transform: translateX(4px);
  }
  .milestone-card.card-latest {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.09) 0%, rgba(15, 23, 42, 0.7) 100%);
    border: 1px solid rgba(56, 189, 248, 0.45);
    box-shadow: 0 8px 24px -6px rgba(14, 165, 233, 0.18);
  }
  .milestone-badge {
    background: #082f49 !important;
    border: 1px solid #0284c7 !important;
    color: #38bdf8 !important;
    font-size: 0.76rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-block;
  }
  .spec-badge {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 12px 16px;
    flex: 1;
    min-width: 140px;
  }
  .spec-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
    display: block;
    margin-bottom: 4px;
  }
  .spec-val {
    font-size: 1rem;
    font-weight: 600;
    color: #f8fafc;
  }
  .btn-wa-live {
    background: #128c7e;
    color: #fff;
    border: 1px solid #25d366;
    border-radius: 9999px;
    padding: 8px 18px;
    font-weight: 600;
    font-size: 0.88rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
  }
  .btn-wa-live:hover {
    background: #25d366;
    color: #03231f;
    box-shadow: 0 0 20px rgba(37, 211, 102, 0.45);
    transform: translateY(-1px);
  }
  .copy-btn {
    cursor: pointer;
    background: rgba(56, 189, 248, 0.12);
    border: 1px solid rgba(56, 189, 248, 0.3);
    color: #7dd3fc;
    border-radius: 8px;
    padding: 4px 10px;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
  }
  .copy-btn:hover {
    background: rgba(56, 189, 248, 0.25);
    color: #fff;
  }
</style>
@endpush

@section('content')
<div class="container py-5" style="padding-top: 110px !important; min-height: 75vh;">
  <div class="row justify-content-center">
    <div class="col-xl-10 col-lg-11">

      <!-- Breadcrumb navigation -->
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="/" class="text-info text-decoration-none">&larr; FastCargo Public Portal</a></li>
          <li class="breadcrumb-item active text-white-50" aria-current="page">Shipment {{ $booking->tracking_number }}</li>
        </ol>
      </nav>

      <!-- Main Tracking Glassmorphic Card -->
      <div class="tracking-hero-card p-4 p-md-5 mb-4 text-white">

        <!-- Header: Waybill & Status -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom border-white border-opacity-10 pb-4 mb-4">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="text-white-50 small fw-semibold text-uppercase tracking-wider">INTERNATIONAL WAYBILL</span>
              <button type="button" class="copy-btn" id="copyWaybillBtn" onclick="copyWaybill('{{ $booking->tracking_number }}')">
                <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><path d="M8 4v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2h-8a2 2 0 0 0-2 2z"></path><path d="M16 4h2a2 2 0 0 1 2 2v4M4 8H2v12a2 2 0 0 0 2 2h12v-2"></path></svg>
                <span id="copyBtnText">Copy</span>
              </button>
            </div>
            <h1 class="mb-0 fs-2 waybill-code">{{ $booking->tracking_number }}</h1>
          </div>

          <div class="d-flex flex-wrap align-items-center gap-2">
            @php
              $status = strtoupper($booking->cargo_status ?? 'BOOKED');
              $pillClass = match($status) {
                'DELIVERED' => 'status-delivered',
                'SAILING', 'IN_TRANSIT', 'GATE_IN', 'CUSTOMS_INSPECTION' => 'status-sailing',
                'BOOKED' => 'status-booked',
                default => 'status-warning'
              };
            @endphp
            <div class="status-pill {{ $pillClass }}" role="status" aria-live="polite">
              <span class="pulse-beacon" aria-hidden="true"></span>
              <span>{{ str_replace('_', ' ', $status) }}</span>
            </div>

            <!-- WhatsApp Instant Bot Link -->
            <a href="https://wa.me/6285836290424?text={{ urlencode($booking->tracking_number) }}" target="_blank" rel="noopener noreferrer" class="btn-wa-live" title="Ask FastCargo WhatsApp Support Bot">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20.52 3.48A11.9 11.9 0 0 0 12.06 0C5.46 0 .09 5.37.09 11.97c0 2.11.55 4.16 1.6 5.97L0 24l6.23-1.63a11.9 11.9 0 0 0 5.83 1.51h.01c6.6 0 11.97-5.37 11.97-11.97 0-3.2-1.25-6.21-3.52-8.43zM12.06 21.88h-.01a9.9 9.9 0 0 1-5.04-1.38l-.36-.21-3.7 .97.99-3.6-.23-.37a9.9 9.9 0 0 1-1.52-5.32c0-5.46 4.45-9.91 9.92-9.91 2.65 0 5.14 1.03 7.01 2.9a9.83 9.83 0 0 1 2.91 7.01c0 5.46-4.45 9.91-9.97 9.91zm5.45-7.42c-.3-.15-1.77-.87-2.04-.97-.28-.1-.48-.15-.68.15-.2.3-.78.97-.95 1.17-.18.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.18-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.68-1.64-.93-2.25-.25-.6-.5-.52-.68-.53l-.58-.01c-.2 0-.53.08-.8.38-.28.3-1.05 1.03-1.05 2.51s1.08 2.92 1.23 3.12c.15.2 2.12 3.24 5.14 4.54.72.31 1.28.5 1.72.64.72.23 1.38.2 1.9.12.58-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.18-1.42-.08-.12-.28-.2-.58-.35z"/></svg>
              <span>Ask Bot</span>
            </a>
          </div>
        </div>

        <!-- Route Corridor Visualizer -->
        <div class="route-corridor mb-4">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

            <!-- Origin Node -->
            <div class="route-node">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-secondary bg-opacity-50 text-white font-monospace small px-2 py-1">
                  {{ $booking->route->originPort->country_code ?? 'ID' }}
                </span>
                <span class="text-white-50 small fw-bold text-uppercase">PORT OF ORIGIN</span>
              </div>
              <h4 class="mb-1 text-white fs-5 fw-bold">{{ $booking->route->originPort->name ?? $booking->route->origin_port_id }}</h4>
              <div class="small text-info">
                {{ $booking->route->originPort->city ?? '' }}, {{ $booking->route->originPort->country_code ?? '' }}
              </div>
            </div>

            <!-- Transit Beam with Animated Icon -->
            <div class="route-beam text-center my-3 my-md-0">
              <span class="badge bg-info bg-opacity-20 text-info border border-info border-opacity-30 rounded-pill px-3 py-1 mb-2 small fw-semibold">
                @if($booking->booking_type === 'AIR') ✈️ AIR EXPRESS
                @elseif($booking->booking_type === 'LAND') 🚚 OVERLAND BONDED
                @else 🚢 OCEAN VESSEL
                @endif
              </span>
              <div class="route-line position-relative">
                <span class="transit-icon-badge" aria-hidden="true">
                  @if($booking->booking_type === 'AIR') ✈️
                  @elseif($booking->booking_type === 'LAND') 🚚
                  @else 🚢
                  @endif
                </span>
              </div>
              <small class="text-white-50 mt-2 d-block" style="font-size: 0.76rem;">ASEAN Trade Corridor</small>
            </div>

            <!-- Destination Node -->
            <div class="route-node text-md-end">
              <div class="d-flex align-items-center justify-content-md-end gap-2 mb-1">
                <span class="text-white-50 small fw-bold text-uppercase">PORT OF DESTINATION</span>
                <span class="badge bg-secondary bg-opacity-50 text-white font-monospace small px-2 py-1">
                  {{ $booking->route->destinationPort->country_code ?? 'SG' }}
                </span>
              </div>
              <h4 class="mb-1 text-white fs-5 fw-bold">{{ $booking->route->destinationPort->name ?? $booking->route->destination_port_id }}</h4>
              <div class="small text-info">
                {{ $booking->route->destinationPort->city ?? '' }}, {{ $booking->route->destinationPort->country_code ?? '' }}
              </div>
            </div>

          </div>
        </div>

        <!-- Shipment Specifications Bar -->
        <div class="d-flex flex-wrap gap-3 mb-4">
          <div class="spec-badge">
            <span class="spec-label">Cargo Service</span>
            <div class="spec-val">{{ $booking->cargo_type ?? 'General Merchandise' }}</div>
          </div>
          <div class="spec-badge">
            <span class="spec-label">Total Weight</span>
            <div class="spec-val">{{ number_format((float) ($booking->total_weight_kg ?? 0), 0) }} kg</div>
          </div>
          <div class="spec-badge">
            <span class="spec-label">Total Volume</span>
            <div class="spec-val">{{ number_format((float) ($booking->total_volume_cbm ?? 0), 2) }} CBM</div>
          </div>
          <div class="spec-badge">
            <span class="spec-label">Customs Clearance</span>
            <div class="spec-val">
              @php $channel = strtoupper($booking->customs_channel ?? 'GREEN'); @endphp
              <span class="badge @if($channel === 'GREEN') bg-success @elseif($channel === 'YELLOW') bg-warning @else bg-danger @endif bg-opacity-25 text-white border border-opacity-30 border-white px-2 py-1">
                {{ $channel }} CHANNEL
              </span>
            </div>
          </div>
        </div>

        <!-- Milestone Progression Header -->
        <div class="d-flex justify-content-between align-items-center border-bottom border-white border-opacity-10 pb-3 mb-4">
          <div class="d-flex align-items-center gap-2">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="#38bdf8" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <h5 class="mb-0 text-white fw-bold">Live Milestone Progression</h5>
          </div>
          <span class="text-white-50 small">Timezone: Asia/Jakarta (WIB)</span>
        </div>

        <!-- Vertical Milestone Stepper -->
        <div class="timeline-stepper my-3" role="list">
          @forelse($booking->trackingEvents as $index => $ev)
            @php
              $isLatest = ($index === count($booking->trackingEvents) - 1);
            @endphp
            <div class="milestone-item" role="listitem">
              <span class="milestone-node {{ $isLatest ? 'is-latest' : 'is-completed' }}" aria-hidden="true">
                @if($isLatest)
                  <span>&bull;</span>
                @else
                  <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                @endif
              </span>

              <div class="milestone-card {{ $isLatest ? 'card-latest' : '' }}">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-1">
                  <div>
                    <h6 class="mb-1 text-white fw-bold">{{ $ev->event_name }}</h6>
                    <span class="milestone-badge">
                      {{ str_replace('_', ' ', $ev->current_status) }}
                    </span>
                  </div>
                  <div class="text-md-end text-white-50 small font-monospace">
                    {{ $ev->event_timestamp ? \Carbon\Carbon::parse($ev->event_timestamp)->format('d M Y, H:i') : '' }} WIB
                  </div>
                </div>

                @if($ev->latitude && $ev->longitude)
                  <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top border-white border-opacity-10 small text-white-50">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="#38bdf8" fill="none" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <span>GPS Telemetry: <code class="text-info bg-black bg-opacity-30 px-2 py-1 rounded">{{ $ev->latitude }}, {{ $ev->longitude }}</code></span>
                    <a href="https://www.google.com/maps?q={{ $ev->latitude }},{{ $ev->longitude }}" target="_blank" rel="noopener noreferrer" class="text-info text-decoration-none ms-auto" style="font-size: 0.78rem;">View on Map &rarr;</a>
                  </div>
                @endif
              </div>
            </div>
          @empty
            <div class="p-4 rounded bg-black bg-opacity-40 border border-secondary border-opacity-25 text-white-50 text-center">
              <div class="fs-4 mb-2">📋</div>
              <h6 class="text-white mb-1">Booking Confirmed</h6>
              <p class="small mb-0">Cargo booking logged. Awaiting container terminal gate-in and customs clearance.</p>
            </div>
          @endforelse
        </div>

        <!-- Customer Portal Notice Callout -->
        <div class="mt-5 p-4 rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-3" style="background: linear-gradient(135deg, rgba(14, 165, 233, 0.12) 0%, rgba(15, 181, 162, 0.12) 100%); border: 1px solid rgba(56, 189, 248, 0.25);">
          <div>
            <h6 class="text-white mb-1 fw-bold">Commercial Documents &amp; Telemetry</h6>
            <p class="text-white-50 small mb-0">Sign in to your customer account to download English PDF commercial invoices, electronic Proof of Delivery (e-POD) signatures, and ISO container telemetry.</p>
          </div>
          <a href="{{ config('app.portal_urls.customer', 'http://customer.fastcargo.local:8080') }}/login" class="btn btn-primary px-4 py-2" style="white-space: nowrap;">
            Sign In to Customer Portal &rarr;
          </a>
        </div>

      </div>

    </div>
  </div>
</div>

@push('scripts')
<script>
  function copyWaybill(code) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(code).then(() => {
        const textSpan = document.getElementById('copyBtnText');
        if (textSpan) {
          textSpan.textContent = 'Copied!';
          setTimeout(() => { textSpan.textContent = 'Copy'; }, 2000);
        }
      });
    }
  }
</script>
@endpush
@endsection
