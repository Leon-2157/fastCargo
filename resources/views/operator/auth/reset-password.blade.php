<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Set New Password — FastCargo Operator Terminal</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
      <div class="card shadow-lg my-5 border-0 rounded-4 overflow-hidden" style="background: #ffffff;">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="mb-2 text-warning">
              <i class="fas fa-key fa-3x"></i>
            </div>
            <h4 class="text-dark fw-bold mb-1">Set Operator Password</h4>
            <span class="text-muted small">Update credentials for FastCargo Terminal Operations</span>
          </div>

          @if($errors->any())
            <div class="alert alert-danger small mb-3">
              <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form action="{{ route('operator.password.update', [], false) }}" method="POST" class="user">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
              <label class="form-label small text-muted" for="email">Operator Identifier Email</label>
              <input class="form-control form-control-user" type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" readonly>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted" for="password">New Password</label>
              <input class="form-control form-control-user" type="password" id="password" name="password" placeholder="Minimum 8 characters" autocomplete="new-password" required autofocus>
              <div class="form-text small" style="font-size: 11px;">Must be at least 8 characters with letters and numbers.</div>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted" for="password_confirmation">Confirm New Password</label>
              <input class="form-control form-control-user" type="password" id="password_confirmation" name="password_confirmation" placeholder="Re-enter password" autocomplete="new-password" required>
            </div>

            <div class="p-3 bg-light rounded-3 mb-3 border text-muted" style="font-size: 12px;">
              <i class="fas fa-lock text-warning me-1"></i>
              <strong>Security Protocol:</strong> Terminal Operators are subject to mandatory Two-Factor Authentication (2FA) verification during sign-in.
            </div>

            <button class="btn btn-warning text-dark d-block w-100 btn-user py-2 fw-bold" type="submit">
              <i class="fas fa-check-circle me-1"></i> Update Credentials &amp; Continue
            </button>
          </form>

          <hr class="my-4">
          <div class="text-center small">
            <a href="{{ route('operator.login', [], false) }}" class="fw-bold text-dark">Return to Terminal Login</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

