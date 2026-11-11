<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Operator Terminal Registration — FastCargo Enterprise</title>
  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
</head>
<body class="bg-gradient-primary" style="background: linear-gradient(135deg, #090e17 0%, #111827 100%);">
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-9 col-lg-8">
      <div class="card shadow-lg border-0 rounded-4 overflow-hidden" style="background: #ffffff;">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="mb-2 text-warning">
              <i class="fas fa-id-card-alt fa-3x"></i>
            </div>
            <h4 class="text-dark fw-bold mb-1">Finalize Operator Onboarding</h4>
            <span class="text-muted small">Invitation Token Validated for Station: <strong class="text-dark">{{ $invitation->hub_name ?? 'Regional Hub' }}</strong></span>
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

          <form action="{{ route('operator.register', $invitation->invitation_token, false) }}" method="POST" enctype="multipart/form-data" class="user">
            @csrf

            <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">1. Verified Invitation Information</h6>
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label small text-muted">Operator Name</label>
                <input type="text" class="form-control" value="{{ $invitation->name }}" readonly disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Official Email</label>
                <input type="email" class="form-control" value="{{ $invitation->email }}" readonly disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Contact Phone</label>
                <input type="text" class="form-control" value="{{ $invitation->phone }}" readonly disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Station / Assigned Hub</label>
                <input type="text" class="form-control" value="{{ $invitation->assigned_hub_id ?? $invitation->city }}" readonly disabled>
              </div>
            </div>

            <h6 class="text-primary fw-bold border-bottom pb-2 mb-3 mt-4">2. Security &amp; Identity Credentials</h6>
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label small text-muted">Create Terminal Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-control" placeholder="••••••••" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">National Identity Number (NIK / Passport)</label>
                <input type="text" name="nik" class="form-control" value="{{ old('nik') }}" placeholder="3201xxxxxxxxxxxx" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Upload Identity Document (KTP Scan/Photo)</label>
                <input type="file" name="ktp_file" class="form-control" accept="image/*,.pdf" required>
              </div>
            </div>

            <h6 class="text-primary fw-bold border-bottom pb-2 mb-3 mt-4">3. Settlement Bank Account Details</h6>
            <div class="row g-2 mb-3">
              <div class="col-md-4">
                <label class="form-label small text-muted">Bank Name</label>
                <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name') }}" placeholder="BCA / Mandiri / BNI" required>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Account Number</label>
                <input type="text" name="bank_account_number" class="form-control" value="{{ old('bank_account_number') }}" placeholder="1234567890" required>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Account Beneficiary Name</label>
                <input type="text" name="bank_account_name" class="form-control" value="{{ old('bank_account_name') }}" placeholder="Beneficiary Legal Name" required>
              </div>
            </div>

            <button type="submit" class="btn btn-warning text-dark d-block w-100 py-3 fw-bold mt-4">
              Activate Operator Terminal &amp; Proceed to 2FA Setup
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

