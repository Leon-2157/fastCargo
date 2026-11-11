<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Customer Registration — FastCargo Enterprise</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #0e1526 0%, #1e3a8a 100%);">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-9 col-lg-7 col-xl-6">
      <div class="card shadow-lg my-5 border-0 rounded-4 overflow-hidden" style="background: #ffffff;">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="mb-2 text-primary">
              <i class="fas fa-box-open fa-3x"></i>
            </div>
            <h4 class="text-dark fw-bold mb-1">Create Customer Account</h4>
            <span class="text-muted small">Instant retail freight booking &amp; cross-border tracking</span>
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

          <form action="{{ route('customer.register', [], false) }}" method="POST" class="user">
            @csrf
            <input type="hidden" name="browser_timezone" id="browserTimezone" value="">

            <div class="mb-3">
              <label class="form-label small text-muted" for="name">Full Name / Primary Contact</label>
              <input class="form-control form-control-user" type="text" id="name" name="name" value="{{ old('name') }}" placeholder="John Doe" autocomplete="name" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted" for="email">Email Address</label>
              <input class="form-control form-control-user" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="john@example.com" autocomplete="email" required>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted" for="phone">Mobile / WhatsApp Number</label>
              <input class="form-control form-control-user" type="text" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+628123456789" autocomplete="tel" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-sm-6">
                <label class="form-label small text-muted" for="password">Password (min 8 chars)</label>
                <input class="form-control form-control-user" type="password" id="password" name="password" placeholder="••••••••" autocomplete="new-password" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label small text-muted" for="password_confirmation">Confirm Password</label>
                <input class="form-control form-control-user" type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••" autocomplete="new-password" required>
              </div>
            </div>

            <div class="alert alert-info bg-light border-0 small text-muted py-2 mb-3">
              <i class="fas fa-shield-alt text-primary me-1"></i> 2-Factor Authentication (TOTP QR) setup will be required on your first login for cargo escrow security.
            </div>

            <button class="btn btn-primary d-block w-100 btn-user py-2 fw-bold" type="submit">
              Register Customer Account
            </button>
          </form>

          <hr class="my-4">
          <div class="text-center small">
            Already have an account?
            <a href="{{ route('customer.login', [], false) }}" class="fw-bold text-primary">Sign In</a>
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

