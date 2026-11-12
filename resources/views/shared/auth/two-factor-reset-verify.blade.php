<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Enter 2FA Reset Code — FastCargo</title>
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
            <i class="fas fa-shield-alt fa-3x"></i>
          </div>
          <h4 class="text-dark fw-bold mb-1">Enter Verification Code</h4>
          <p class="text-muted small">
            We sent a 6-digit verification code to your
            <strong>{{ $channel === 'WHATSAPP' ? 'WhatsApp ('.$user->phone.')' : 'Email ('.$user->email.')' }}</strong>.
          </p>

          @if($errors->any())
            <div class="alert alert-danger small mb-3 text-start">
              <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          @if(session('success'))
            <div class="alert alert-success small mb-3 text-start">
              <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            </div>
          @endif

          <form action="{{ $verifyRoute }}" method="POST" class="mt-4">
            @csrf
            <div class="mb-4">
              <label class="form-label small text-muted visually-hidden" for="code">6-Digit Code</label>
              <input type="text" id="code" name="code" class="form-control form-control-lg text-center font-monospace fs-3 py-2" placeholder="000000" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" autofocus required>
              <div class="form-text small text-muted mt-2">
                Code expires in 10 minutes.
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
              <i class="fas fa-check-circle me-1"></i> Verify &amp; Reset 2FA
            </button>
          </form>

          <div class="mt-4 text-center">
            <a href="{{ $resendRoute }}" class="small text-decoration-none text-muted">
              <i class="fas fa-redo me-1"></i> Didn't receive code? Change method or resend
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
