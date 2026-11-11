<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Private Governance Console — FastCargo Enterprise</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #020617 0%, #0f172a 100%);">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
      <div class="card shadow-lg my-5 border-0 rounded-4 overflow-hidden" style="background: #ffffff;">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="mb-2 text-danger">
              <i class="fas fa-user-shield fa-3x"></i>
            </div>
            <h4 class="text-dark fw-bold mb-1">Administrative Cockpit</h4>
            <span class="text-muted small">Private Governance &amp; Central Control</span>
          </div>

          <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 small mb-3 text-dark">
            <i class="fas fa-shield-virus me-1"></i> Private subnet access only. All actions are cryptographically signed to the immutable audit trail.
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

          <form action="{{ route('admin.login', [], false) }}" method="POST" class="user">
            @csrf
            <div class="mb-3">
              <label class="form-label small text-muted" for="login">Admin Account Identifier</label>
              <input class="form-control form-control-user" type="text" id="login" name="login" value="{{ old('login') }}" placeholder="admin or admin@fastcargo.internal" autocomplete="username" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted" for="password">Master Key Password</label>
              <input class="form-control form-control-user" type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
            </div>
            <div class="mb-3 d-flex justify-content-between align-items-center">
              <div class="form-check small">
                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                <label class="form-check-label" for="rememberMe">Remember Session</label>
              </div>
            </div>

            <button class="btn btn-danger text-white d-block w-100 btn-user py-2 fw-bold" type="submit">
              Access Governance Console
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

