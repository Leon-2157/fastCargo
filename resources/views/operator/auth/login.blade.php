<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Operator Terminal Login — FastCargo Enterprise</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #090e17 0%, #111827 100%);">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
      <div class="card shadow-lg my-5 border-0 rounded-4 overflow-hidden" style="background: #ffffff;">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="mb-2 text-warning">
              <i class="fas fa-network-wired fa-3x"></i>
            </div>
            <h4 class="text-dark fw-bold mb-1">Operator Hub Gateway</h4>
            <span class="text-muted small">Restricted VPN Access Terminal &amp; Field Station</span>
          </div>

          <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 small mb-3 text-dark">
            <i class="fas fa-lock me-1"></i> Access monitored via WireGuard / VPN network signature.
          </div>

          @if(session('status'))
            <div class="alert alert-success small mb-3">{{ session('status') }}</div>
          @endif

          @if($errors->any())
            <div class="alert alert-danger small mb-3">
              <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form action="{{ route('operator.login', [], false) }}" method="POST" class="user">
            @csrf
            <div class="mb-3">
              <label class="form-label small text-muted" for="email">Operator Email / Work Identifier</label>
              <input class="form-control form-control-user" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="operator@fastcargo.com" autocomplete="email" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted" for="password">Password</label>
              <input class="form-control form-control-user" type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
            </div>
            <div class="mb-3 d-flex justify-content-between align-items-center">
              <div class="form-check small">
                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                <label class="form-check-label" for="rememberMe">Remember Terminal</label>
              </div>
            </div>

            <button class="btn btn-warning text-dark d-block w-100 btn-user py-2 fw-bold" type="submit">
              Authenticate Operator
            </button>
          </form>

          <hr class="my-4">
          <div class="text-center text-muted small">
            Registration is by Invitation Only. Received a link from Admin? Open the link in your email to finalize setup.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

