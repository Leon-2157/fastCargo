<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify your email — FastCargo</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #0e1526 0%, #1e3a8a 100%);">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6 col-xl-5">
      <div class="card shadow-lg my-5 border-0 rounded-4">
        <div class="card-body p-4 p-md-5 text-center">
          <h1 class="h4 text-dark fw-bold">Verify your email</h1>
          <p class="text-muted">Check the verification link sent to {{ auth('customer')->user()->email }} before signing in.</p>
          @if(session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
          @endif
          <form method="POST" action="{{ route('customer.verification.send', [], false) }}">
            @csrf
            <button class="btn btn-primary w-100" type="submit">Resend verification link</button>
          </form>
          <form method="POST" action="{{ route('customer.logout', [], false) }}" class="mt-3">
            @csrf
            <button class="btn btn-link" type="submit">Sign out</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
