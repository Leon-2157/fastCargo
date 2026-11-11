<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>{{ $pageTitle ?? 'Setup 2-Factor Authentication — FastCargo' }}</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #0e1526 0%, #1e3a8a 100%);">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
      <div class="card shadow-lg my-5 border-0 rounded-4 overflow-hidden">
        <div class="card-body p-4 p-md-5 text-center">
          <div class="mb-3 text-primary">
            <i class="fas fa-qrcode fa-3x"></i>
          </div>
          <h4 class="text-dark fw-bold mb-1">{{ $heading ?? 'Set Up 2-Factor Security' }}</h4>
          <p class="text-muted small">Scan this QR code with Google Authenticator, Aegis, or Authy to protect your freight account.</p>

          @if($errors->any())
            <div class="alert alert-danger small mb-3 text-start">
              <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="my-4 p-3 bg-light rounded d-inline-block border">
            {!! $qrCodeSvg !!}
          </div>

          <div class="mb-3 font-monospace small bg-light p-2 rounded text-muted">
            Secret Key: <strong class="text-dark">{{ $secretKey }}</strong>
          </div>

          <form action="{{ $enableRoute ?? route('customer.2fa.enable', [], false) }}" method="POST">
            @csrf
            <input type="hidden" name="secret" value="{{ $secretKey }}">
            <div class="mb-3">
              <label class="form-label small text-muted" for="code">Enter 6-Digit Authenticator Code</label>
              <input type="text" id="code" name="code" class="form-control form-control-lg text-center font-monospace" placeholder="123456" maxlength="6" autocomplete="one-time-code" autofocus required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
              Verify &amp; Activate 2FA
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

