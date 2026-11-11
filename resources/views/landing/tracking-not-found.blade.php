@extends('layouts.landing')

@section('title', 'Shipment Not Found — FastCargo')
@section('body-class', 'page-dark')
@section('header-class', 'site-header--solid')

@section('content')
<div class="container py-5 text-center text-white" style="padding-top: 130px !important; min-height: 65vh;">
  <div class="card border-secondary border-opacity-50 p-5 mx-auto" style="max-width: 600px; background: #131c31;">
    <div class="fs-1 text-warning mb-3">⚠️</div>
    <h3 class="text-white mb-3">Tracking Number Not Found</h3>
    <p class="text-light opacity-75">No shipment was located under the waybill identifier <code class="text-warning">{{ $trackingNumber }}</code>. Please check your documentation or contact FastCargo Operations.</p>
    <div class="mt-4 d-flex justify-content-center flex-wrap gap-2">
      <a href="/" class="btn btn-outline-light">Back to Home</a>
      <a href="/#track" class="btn btn-primary">Try Another Number</a>
    </div>
  </div>
</div>
@endsection

