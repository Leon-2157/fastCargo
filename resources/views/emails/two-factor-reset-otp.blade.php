<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>FastCargo 2FA Reset Code</title>
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
    .otp-box {
      margin: 24px 0;
      text-align: center;
      padding: 20px;
      background-color: #f8fafc;
      border: 2px dashed #93c5fd;
      border-radius: 8px;
    }
    .otp-code {
      font-family: monospace;
      font-size: 36px;
      font-weight: 800;
      letter-spacing: 8px;
      color: #1d4ed8;
      display: inline-block;
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
      <p>We received a request to reset Two-Factor Authentication (2FA) for your FastCargo account.</p>
      <p>Use the following 6-digit verification code to complete the reset process:</p>
      
      <div class="otp-box">
        <span class="otp-code">{{ $otpCode }}</span>
      </div>

      <p style="font-size: 14px; color: #475569;">
        This code is valid for <strong>{{ $validMinutes }} minutes</strong>. Once entered, your existing authenticator key will be revoked, and you will be directed to scan a fresh QR code.
      </p>

      <div class="warning">
        <strong>Security Notice:</strong> If you did not initiate this request, your password may have been compromised. Change your password immediately or contact your FastCargo security administrator.
      </div>
    </div>
    <div class="footer">
      &copy; {{ date('Y') }} FastCargo Logistics Inc. All rights reserved.<br>
      Automated security alert &bull; Do not reply to this email.
    </div>
  </div>
</body>
</html>
