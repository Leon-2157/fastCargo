<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>FastCargo Password Reset</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      background-color: #f4f6f9;
      margin: 0;
      padding: 0;
      color: #1e293b;
    }
    .email-container {
      max-width: 560px;
      margin: 40px auto;
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
      border: 1px solid #e2e8f0;
    }
    .email-header {
      background: linear-gradient(135deg, #0e1526 0%, #1e3a8a 100%);
      color: #ffffff;
      padding: 32px 28px;
      text-align: center;
    }
    .email-header h1 {
      margin: 0;
      font-size: 24px;
      font-weight: 700;
      letter-spacing: -0.5px;
    }
    .email-body {
      padding: 32px 28px;
    }
    .btn-container {
      margin: 28px 0;
      text-align: center;
    }
    .btn-reset {
      display: inline-block;
      background-color: #1d4ed8;
      color: #ffffff !important;
      font-size: 15px;
      font-weight: 600;
      text-decoration: none;
      padding: 14px 32px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(29, 78, 216, 0.25);
    }
    .btn-reset:hover {
      background-color: #1e40af;
    }
    .warning {
      background-color: #fef2f2;
      border-left: 4px solid #ef4444;
      padding: 12px 16px;
      border-radius: 4px;
      font-size: 13px;
      color: #991b1b;
      margin-top: 24px;
    }
    .footer {
      background: #f8fafc;
      padding: 20px 28px;
      text-align: center;
      font-size: 12px;
      color: #64748b;
      border-top: 1px solid #e2e8f0;
    }
    .raw-link {
      word-break: break-all;
      font-size: 12px;
      color: #64748b;
      background: #f1f5f9;
      padding: 10px 14px;
      border-radius: 6px;
      border: 1px solid #e2e8f0;
      margin-top: 8px;
    }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="email-header">
      <h1>FastCargo Security</h1>
      <p style="margin: 6px 0 0 0; font-size: 14px; opacity: 0.9;">ASEAN Cross-Border Freight Platform</p>
    </div>
    <div class="email-body">
      <p>Hello <strong>{{ $userName }}</strong>,</p>
      <p>We received an official request to reset the password for your FastCargo <strong>{{ $portalName }}</strong> account.</p>
      <p>Click the button below to establish a new password for your access credentials:</p>

      <div class="btn-container">
        <a href="{{ $resetUrl }}" target="_blank" rel="noopener noreferrer" class="btn-reset">
          Reset Account Password
        </a>
      </div>

      <p style="font-size: 14px; color: #475569;">
        This single-use security link is valid for <strong>{{ $validMinutes }} minutes</strong>. Once expired, a new request must be generated.
      </p>

      <p style="font-size: 13px; color: #64748b; margin-top: 16px;">
        If the button above does not work, copy and paste the following URL into your web browser:
      </p>
      <div class="raw-link">{{ $resetUrl }}</div>

      <div class="warning">
        <strong>Security Notice:</strong> If you did not initiate this password change, your account may be at risk. Ignore this email or contact your FastCargo regional administrator immediately. Note that Two-Factor Authentication (2FA) challenges remain enforced upon subsequent sign-in.
      </div>
    </div>
    <div class="footer">
      &copy; {{ date('Y') }} FastCargo Logistics Inc. All rights reserved.<br>
      Automated enterprise security notification &bull; Do not reply to this email.
    </div>
  </div>
</body>
</html>

