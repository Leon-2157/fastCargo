@extends('layouts.landing')

@section('title', 'Shipment Not Found — FastCargo')
@section('body-class', 'page-dark')
@section('header-class', 'site-header--solid')

@section('content')
<div class="container py-5 text-center text-white" style="padding-top: 130px !important; min-height: 70vh;">
  <div class="card p-4 p-md-5 mx-auto" style="max-width: 620px; background: linear-gradient(145deg, #10192e 0%, #090e1a 100%); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 18px; box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.7), 0 0 35px -5px rgba(245, 158, 11, 0.12);">
    
    <div class="mb-3">
      <span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-30 rounded-circle p-3 d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 1.7rem;">
        ⚠️
      </span>
    </div>

    <h2 class="text-white mb-2 fs-3 fw-bold">Tracking Number Not Found</h2>
    <p class="text-white-50 mb-4 small">No shipment was located under the tracking identifier <code class="text-warning bg-black bg-opacity-40 px-2 py-1 rounded font-monospace">{{ $trackingNumber }}</code>. Please verify your waybill number or try our live demo shipment.</p>

    <!-- Inline Search Form -->
    <div class="p-3 rounded-3 mb-4 text-start" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.08);">
      <form action="" method="GET" onsubmit="event.preventDefault(); const resi = document.getElementById('notFoundInput').value.trim(); if(resi) window.location.href='/track/'+encodeURIComponent(resi);">
        <label class="form-label text-white-50 small mb-1" for="notFoundInput">Re-enter Waybill Number</label>
        <div class="input-group">
          <input type="text" id="notFoundInput" class="form-control landing-input" placeholder="e.g. RPN-20261106-001" autocomplete="off" required>
          <button class="btn btn-primary px-3 fw-semibold" type="submit">Search Again</button>
        </div>
      </form>
      <div class="mt-2 text-center">
        <a href="/track/RPN-20261106-001" class="text-info text-decoration-none small">
          &rarr; View Live Demo Shipment (RPN-20261106-001)
        </a>
      </div>
    </div>

    <div class="d-flex justify-content-center flex-wrap gap-2">
      <a href="/" class="btn btn-outline-light px-4">Back to Home</a>
      <a href="https://wa.me/6285836290424?text=Halo%20FastCargo,%20saya%20butuh%20bantuan%20cek%20resi%20{{ urlencode($trackingNumber) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-info px-4">
        Contact WhatsApp Support
      </a>
    </div>

  </div>
</div>
@endsection
