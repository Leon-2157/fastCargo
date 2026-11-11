<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Two-Factor Challenge — FastCargo</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #020617 0%, #0f172a 100%);">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
      <div class="card shadow-lg my-5 border-0 rounded-4">
        <div class="card-body p-4 p-md-5 text-center">
          <h1 class="h4 text-dark fw-bold mb-2">Verify Admin Identity</h1>
          <p class="text-muted small">Enter the 6-digit code from your authenticator app.</p>
          @if($errors->any())
            <div class="alert alert-danger small" role="alert">{{ $errors->first() }}</div>
          @endif
          <form action="{{ route('admin.2fa.verify', [], false) }}" method="POST">
            @csrf
            <label class="visually-hidden" for="code">6-digit authenticator code</label>
            <input class="form-control form-control-lg text-center font-monospace mb-3" type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
            <button class="btn btn-danger w-100 py-2 fw-bold" type="submit">Verify and Continue</button>
          </form>
          <form action="{{ route('admin.logout', [], false) }}" method="POST" class="mt-3">
            @csrf
            <button class="btn btn-link text-muted" type="submit">Sign out</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

