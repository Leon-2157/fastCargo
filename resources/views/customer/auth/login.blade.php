<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Customer Login — FastCargo Enterprise</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #0e1526 0%, #1e3a8a 100%);">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
      <div class="card shadow-lg my-5 border-0 rounded-4 overflow-hidden" style="background: #ffffff;">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="mb-2 text-primary">
              <i class="fas fa-shipping-fast fa-3x"></i>
            </div>
            <h4 class="text-dark fw-bold mb-1">FastCargo Customer Portal</h4>
            <span class="text-muted small">Sign in to manage bookings &amp; track shipments</span>
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

          <form action="{{ route('customer.login', [], false) }}" method="POST" class="user">
            @csrf
            <!-- Auto-detect timezone from browser on submit -->
            <input type="hidden" name="browser_timezone" id="browserTimezone" value="">

            <div class="mb-3">
              <label class="form-label small text-muted" for="email">Email Address</label>
              <input class="form-control form-control-user" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" autocomplete="email" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted" for="password">Password</label>
              <input class="form-control form-control-user" type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
            </div>
            <div class="mb-3 d-flex justify-content-between align-items-center">
              <div class="form-check small">
                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                <label class="form-check-label" for="rememberMe">Remember Me</label>
              </div>
            </div>

            <button class="btn btn-primary d-block w-100 btn-user py-2 fw-bold" type="submit">
              Sign In to Dashboard
            </button>
          </form>

          <hr class="my-4">
          <div class="text-center small">
            Don't have a customer account?
            <a href="{{ route('customer.register', [], false) }}" class="fw-bold text-primary">Register Here</a>
          </div>
          <div class="text-center small mt-2">
            <a href="{{ config('app.portal_urls.landing', 'http://fastcargo.local:8080') }}" class="text-muted">
              &larr; Back to FastCargo Home
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  try {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (tz) document.getElementById('browserTimezone').value = tz;
  } catch (e) {}
</script>
</body>
</html>

