<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>FastCargo Invoice - {{ $invoice->invoice_number }}</title>
  <style>
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333; line-height: 1.4; padding: 20px; }
    .header { width: 100%; border-bottom: 2px solid #224abe; padding-bottom: 15px; margin-bottom: 20px; }
    .title { font-size: 22px; font-weight: bold; color: #224abe; margin: 0; }
    .subtitle { font-size: 11px; color: #666; margin-top: 3px; }
    .meta-table { width: 100%; margin-bottom: 25px; }
    .meta-table td { vertical-align: top; width: 50%; }
    .label { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #777; margin-bottom: 4px; }
    .value { font-size: 13px; font-weight: bold; color: #111; }
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    .items-table th { background: #f4f6fa; border: 1px solid #ddd; padding: 8px 10px; font-size: 11px; text-transform: uppercase; text-align: left; }
    .items-table td { border: 1px solid #ddd; padding: 8px 10px; font-size: 11px; }
    .items-table .text-right { text-align: right; }
    .items-table .text-center { text-align: center; }
    .total-box { float: right; width: 45%; margin-top: 10px; }
    .total-table { width: 100%; border-collapse: collapse; }
    .total-table td { padding: 6px 10px; font-size: 12px; }
    .grand-total { background: #224abe; color: #fff; font-size: 14px; font-weight: bold; }
    .footer { clear: both; margin-top: 50px; padding-top: 15px; border-top: 1px solid #eee; font-size: 10px; color: #888; text-align: center; }
    .stamp { display: inline-block; padding: 6px 14px; border: 2px solid; border-radius: 4px; font-weight: bold; text-transform: uppercase; font-size: 11px; }
    .stamp-paid { color: #198754; border-color: #198754; }
    .stamp-unpaid { color: #d63384; border-color: #d63384; }
  </style>
</head>
<body>

  <!-- Header -->
  <table class="header">
    <tr>
      <td>
        <div class="title">FastCargo Enterprise</div>
        <div class="subtitle">ASEAN Cross-Border Freight &amp; Multi-Modal Logistics Operating System</div>
        <div style="font-size: 10px; color: #666; margin-top: 4px;">Menara Astra Lt. 32, Jl. Jend. Sudirman Kav. 5-6, Jakarta 10220, Indonesia</div>
      </td>
      <td style="text-align: right; vertical-align: top;">
        <div class="value" style="font-size: 16px; color: #224abe;">{{ $invoice->invoice_number }}</div>
        <div style="margin-top: 8px;">
          @if($invoice->payment_status === 'PAID_SETTLED')
            <span class="stamp stamp-paid">PAID &amp; SETTLED</span>
          @else
            <span class="stamp stamp-unpaid">UNPAID / OUTSTANDING</span>
          @endif
        </div>
      </td>
    </tr>
  </table>

  <!-- Meta Table -->
  <table class="meta-table">
    <tr>
      <td>
        <div class="label">Billed To (Consignee / Shipper)</div>
        <div class="value">{{ $invoice->company->company_name ?? 'Individual Shipper' }}</div>
        <div style="margin-top: 4px;">{{ $invoice->company->company_address ?? 'Jakarta, Indonesia' }}</div>
        <div style="margin-top: 2px;">NPWP: {{ $invoice->company->npwp ?? '01.234.567.8-012.000' }}</div>
        <div>NIB: {{ $invoice->company->nib ?? '1234567890123' }}</div>
      </td>
      <td>
        <div class="label">Invoice Specifications</div>
        <div><strong>Issue Date:</strong> {{ $invoice->created_at->format('d F Y') }}</div>
        <div><strong>Payment Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') }}</div>
        @if($invoice->booking)
          <div style="margin-top: 4px;"><strong>Tracking Consignment:</strong> {{ $invoice->booking->tracking_number }}</div>
          <div><strong>Corridor:</strong> {{ $invoice->booking->route?->origin_port_id }} &rarr; {{ $invoice->booking->route?->destination_port_id }} ({{ $invoice->booking->route?->transport_mode ?? 'SEA' }})</div>
          <div><strong>Total Load:</strong> {{ number_format($invoice->booking->total_weight_kg, 1) }} KG / {{ number_format($invoice->booking->total_volume_cbm, 3) }} CBM</div>
        @endif
      </td>
    </tr>
  </table>

  <!-- Itemized Breakdown -->
  <table class="items-table">
    <thead>
      <tr>
        <th style="width: 50%;">Description</th>
        <th class="text-center" style="width: 15%;">Type</th>
        <th class="text-center" style="width: 15%;">Unit / Qty</th>
        <th class="text-right" style="width: 20%;">Amount (IDR)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <strong>Corridor Cross-Border Freight</strong><br>
          <span style="color: #666; font-size: 10px;">Primary line haulage &amp; container space booking</span>
        </td>
        <td class="text-center">Line Haul</td>
        <td class="text-center">1 Consignment</td>
        <td class="text-right">{{ number_format($invoice->total_invoice_amount * 0.75, 2, ',', '.') }}</td>
      </tr>
      <tr>
        <td>
          <strong>Terminal Handling Charges (THC) &amp; Port Dues</strong><br>
          <span style="color: #666; font-size: 10px;">Quay crane moves, storage, and port entrance clearance</span>
        </td>
        <td class="text-center">Terminal Dues</td>
        <td class="text-center">1 Unit</td>
        <td class="text-right">{{ number_format($invoice->total_invoice_amount * 0.15, 2, ',', '.') }}</td>
      </tr>
      <tr>
        <td>
          <strong>Customs Clearance Pre-Filing &amp; Marine Cargo Indemnity</strong><br>
          <span style="color: #666; font-size: 10px;">ASEAN Single Window e-customs and 0.2% cargo insurance</span>
        </td>
        <td class="text-center">Statutory</td>
        <td class="text-center">1 Policy</td>
        <td class="text-right">{{ number_format($invoice->total_invoice_amount * 0.10, 2, ',', '.') }}</td>
      </tr>
    </tbody>
  </table>

  <!-- Total Summary -->
  <div class="total-box">
    <table class="total-table">
      <tr>
        <td><strong>Subtotal:</strong></td>
        <td class="text-right">IDR {{ number_format($invoice->total_invoice_amount, 2, ',', '.') }}</td>
      </tr>
      <tr>
        <td><strong>VAT / PPN (0% Export Freight):</strong></td>
        <td class="text-right">IDR 0,00</td>
      </tr>
      <tr class="grand-total">
        <td style="color: #fff; padding: 8px 10px;">TOTAL PAYABLE:</td>
        <td class="text-right" style="color: #fff; padding: 8px 10px;">IDR {{ number_format($invoice->total_invoice_amount, 2, ',', '.') }}</td>
      </tr>
    </table>
  </div>

  <div style="clear: both;"></div>

  <!-- Footer -->
  <div class="footer">
    <p>This is a computer-generated tax and freight invoice. No physical signature is required under electronic commerce regulations.<br>
    All transactions are recorded immutably under FastCargo Governance Ledger. Inquiry: {{ config('app.billing_email', 'billing@leonproject.my.id') }}</p>
  </div>

</body>
</html>

