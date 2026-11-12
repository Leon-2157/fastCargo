<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Reset Password — FastCargo Customer Portal</title>
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
              <i class="fas fa-shield-alt fa-3x"></i>
            </div>
            <h4 class="text-dark fw-bold mb-1">Customer Password Recovery</h4>
            <span class="text-muted small">Enter your email to receive a verified password reset link</span>
          </div>

          @if(session('status'))
            <div class="alert alert-success small mb-3">
              <i class="fas fa-check-circle me-1"></i> {{ session('status') }}
            </div>
          @endif

          @if(session('dev_reset_url'))
            <div class="alert alert-info border-0 shadow-sm p-3 mb-4 rounded-3 small">
              <div class="d-flex align-items-center mb-1">
                <i class="fas fa-vial text-info me-2"></i>
                <strong class="text-dark">Development Environment Notice</strong>
              </div>
              <p class="mb-2 text-muted" style="font-size: 11px;">Single-use security token generated. Click below to continue:</p>
              <a href="{{ session('dev_reset_url') }}" class="btn btn-sm btn-primary text-white fw-semibold w-100 py-1">
                <i class="fas fa-external-link-alt me-1"></i> Open Password Reset Form
              </a>
            </div>
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

          <form action="{{ route('customer.password.email', [], false) }}" method="POST" class="user">
            @csrf
            <div class="mb-3">
              <label class="form-label small text-muted" for="email">Registered Email Address</label>
              <input class="form-control form-control-user" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" autocomplete="email" required autofocus>
            </div>

            <button class="btn btn-primary d-block w-100 btn-user py-2 fw-bold" type="submit">
              <i class="fas fa-paper-plane me-1"></i> Send Password Reset Link
            </button>
          </form>

          <hr class="my-4">
          <div class="text-center small">
            Remember your credentials?
            <a href="{{ route('customer.login', [], false) }}" class="fw-bold text-primary">Back to Sign In</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

