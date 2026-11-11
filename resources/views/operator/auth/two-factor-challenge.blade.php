<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Operator Terminal 2FA — FastCargo Enterprise</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #090e17 0%, #111827 100%);">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
      <div class="card shadow-lg my-5 border-0 rounded-4 overflow-hidden">
        <div class="card-body p-4 p-md-5 text-center">
          <div class="mb-3 text-warning">
            <i class="fas fa-key fa-3x"></i>
          </div>
          <h4 class="text-dark fw-bold mb-1">Operator Security Challenge</h4>
          <p class="text-muted small">Enter the 6-digit cryptographic TOTP code from your mobile authenticator to unlock the terminal.</p>

          @if($errors->any())
            <div class="alert alert-danger small mb-3 text-start">
              <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form action="{{ route('operator.2fa.verify', [], false) }}" method="POST">
            @csrf
            <div class="mb-4">
              <label class="form-label small text-muted visually-hidden" for="code">6-Digit TOTP Code</label>
              <input type="text" id="code" name="code" class="form-control form-control-lg text-center font-monospace fs-3 py-2" placeholder="000000" maxlength="6" autocomplete="one-time-code" autofocus required>
            </div>
            <button type="submit" class="btn btn-warning text-dark w-100 py-2 fw-bold">
              Verify Operator Session
            </button>
          </form>

          <form action="{{ route('operator.logout', [], false) }}" method="POST" class="mt-4">
            @csrf
            <button type="submit" class="btn btn-sm btn-link text-muted">Terminate Session</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

