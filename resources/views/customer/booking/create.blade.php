@extends('layouts.dashboard')

@section('title', 'New Cargo Consignment')

@section('content')
<div class="d-sm-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('customer.bookings.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
      <i class="fas fa-arrow-left me-1"></i> Back to My Bookings
    </a>
    <h3 class="text-dark mb-0 fw-bold">New Cross-Border Consignment</h3>
    <p class="text-muted small mb-0">Book direct multi-modal freight across 10 ASEAN nations with automated customs clearance.</p>
  </div>
</div>

@livewire('booking-wizard')
@endsection

