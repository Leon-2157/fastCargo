<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Reset Two-Factor Authentication — FastCargo</title>
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
          <div class="mb-3 text-warning">
            <i class="fas fa-user-shield fa-3x"></i>
          </div>
          <h4 class="text-dark fw-bold mb-1">Reset Two-Factor Security</h4>
          <p class="text-muted small">Lost your phone or authenticator app? Choose a verification channel to receive a 6-digit reset code.</p>

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

          <form action="{{ $sendRoute }}" method="POST" class="text-start mt-4">
            @csrf
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Select Delivery Channel</label>

              <!-- Option 1: Email -->
              <div class="form-check p-3 border rounded mb-2 bg-light">
                <input class="form-check-input" type="radio" name="channel" id="channel_email" value="EMAIL" checked>
                <label class="form-check-label w-100 cursor-pointer" for="channel_email">
                  <div class="d-flex align-items-center">
                    <i class="fas fa-envelope text-primary fa-lg me-3"></i>
                    <div>
                      <div class="fw-bold text-dark">Email Verification</div>
                      <div class="small text-muted">{{ $user->email }}</div>
                    </div>
                  </div>
                </label>
              </div>

              <!-- Option 2: WhatsApp -->
              @php
                $hasPhone = !empty(trim((string)$user->phone));
              @endphp
              <div class="form-check p-3 border rounded bg-light {{ !$hasPhone ? 'opacity-50' : '' }}">
                <input class="form-check-input" type="radio" name="channel" id="channel_wa" value="WHATSAPP" {{ !$hasPhone ? 'disabled' : '' }}>
                <label class="form-check-label w-100 cursor-pointer" for="channel_wa">
                  <div class="d-flex align-items-center">
                    <i class="fab fa-whatsapp text-success fa-lg me-3"></i>
                    <div>
                      <div class="fw-bold text-dark">WhatsApp Instant OTP</div>
                      <div class="small text-muted">
                        {{ $hasPhone ? $user->phone : 'No mobile phone registered' }}
                      </div>
                    </div>
                  </div>
                </label>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold mt-2">
              <i class="fas fa-paper-plane me-1"></i> Send 6-Digit Reset Code
            </button>
          </form>

          <div class="mt-4 text-center">
            <a href="{{ $challengeRoute }}" class="small text-decoration-none text-muted">
              <i class="fas fa-arrow-left me-1"></i> Back to Authenticator Challenge
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

