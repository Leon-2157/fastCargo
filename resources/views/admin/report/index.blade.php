@extends('layouts.dashboard')

@section('title', 'Reports & Data Export Center — FastCargo Admin')

@section('content')
<div class="container-fluid">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="text-dark fw-bold mb-1">
        <i class="fas fa-file-export text-primary me-2"></i>Reports &amp; Data Export Center
      </h3>
      <p class="text-muted small mb-0">Generate verified Excel/CSV managerial exports for financial audit, manifest compliance, and escrow splits.</p>
    </div>
    <div class="badge bg-primary px-3 py-2 text-white rounded-pill">
      <i class="fas fa-file-excel me-1"></i> UTF-8 BOM Excel-Compatible
    </div>
  </div>

  <div class="row g-4">
    <!-- Card 1: Invoices & Revenue -->
    <div class="col-xl-6">
      <div class="card shadow-sm border-0 h-100 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-bold text-dark">
            <i class="fas fa-file-invoice-dollar text-primary me-2"></i>Invoices &amp; Revenue Ledger
          </h6>
          <span class="badge bg-light text-dark border">{{ number_format($counts['invoices']) }} Invoices</span>
        </div>
        <div class="card-body">
          <p class="text-muted small">Download itemized payment records including Dana QRIS transaction references, gross values, tax, and settlement status.</p>

          <form action="{{ route('admin.reports.export', ['category' => 'invoices']) }}" method="GET">
            <div class="mb-3">
              <label class="form-label small text-muted fw-bold">Quick Presets</label>
              <div class="btn-group btn-group-sm w-100" role="group">
                <button type="submit" name="preset" value="today" class="btn btn-outline-secondary">Today</button>
                <button type="submit" name="preset" value="last_7_days" class="btn btn-outline-secondary">Last 7 Days</button>
                <button type="submit" name="preset" value="this_month" class="btn btn-outline-secondary">This Month</button>
                <button type="submit" name="preset" value="all" class="btn btn-outline-secondary">All Time</button>
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-sm-6">
                <label class="form-label small text-muted">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm">
              </div>
              <div class="col-sm-6">
                <label class="form-label small text-muted">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted">Filter Payment Status</label>
              <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="PAID_SETTLED">PAID_SETTLED (Completed)</option>
                <option value="PENDING">PENDING (Unpaid)</option>
                <option value="EXPIRED">EXPIRED</option>
                <option value="FAILED">FAILED</option>
              </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
              <i class="fas fa-download me-1"></i> Export Invoices CSV
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Card 2: Escrow & Revenue Splits -->
    <div class="col-xl-6">
      <div class="card shadow-sm border-0 h-100 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-bold text-dark">
            <i class="fas fa-hand-holding-usd text-success me-2"></i>Escrow &amp; Revenue Splits
          </h6>
          <span class="badge bg-light text-dark border">{{ number_format($counts['escrow']) }} Escrow Records</span>
        </div>
        <div class="card-body">
          <p class="text-muted small">Export escrow holdings across shipment milestones (30% gate-in, 40% sailing, 30% delivered) and operator 78% share splits.</p>

          <form action="{{ route('admin.reports.export', ['category' => 'escrow']) }}" method="GET">
            <div class="mb-3">
              <label class="form-label small text-muted fw-bold">Quick Presets</label>
              <div class="btn-group btn-group-sm w-100" role="group">
                <button type="submit" name="preset" value="today" class="btn btn-outline-secondary">Today</button>
                <button type="submit" name="preset" value="last_7_days" class="btn btn-outline-secondary">Last 7 Days</button>
                <button type="submit" name="preset" value="this_month" class="btn btn-outline-secondary">This Month</button>
                <button type="submit" name="preset" value="all" class="btn btn-outline-secondary">All Time</button>
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-sm-6">
                <label class="form-label small text-muted">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm">
              </div>
              <div class="col-sm-6">
                <label class="form-label small text-muted">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted">Filter Escrow Status</label>
              <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="HELD_IN_ESCROW">HELD_IN_ESCROW</option>
                <option value="PARTIALLY_RELEASED">PARTIALLY_RELEASED</option>
                <option value="FULLY_SETTLED">FULLY_SETTLED</option>
              </select>
            </div>

            <button type="submit" class="btn btn-success text-white btn-sm w-100 fw-bold">
              <i class="fas fa-download me-1"></i> Export Escrow Ledger CSV
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Card 3: Manifests & Vessel Operations -->
    <div class="col-xl-4">
      <div class="card shadow-sm border-0 h-100 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-bold text-dark">
            <i class="fas fa-ship text-info me-2"></i>Manifests &amp; Fleet Operations
          </h6>
          <span class="badge bg-light text-dark border">{{ number_format($counts['manifests']) }} Manifests</span>
        </div>
        <div class="card-body">
          <p class="text-muted small">Voyage logs, origin/destination corridors, consignment koli counts, and vessel departure/arrival stamps.</p>

          <form action="{{ route('admin.reports.export', ['category' => 'manifests']) }}" method="GET">
            <div class="mb-3">
              <label class="form-label small text-muted fw-bold">Quick Presets</label>
              <div class="btn-group btn-group-sm w-100" role="group">
                <button type="submit" name="preset" value="this_month" class="btn btn-outline-secondary">This Month</button>
                <button type="submit" name="preset" value="all" class="btn btn-outline-secondary">All Time</button>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted">Filter Operational Status</label>
              <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="OPEN">OPEN (Draft)</option>
                <option value="LOCKED_SEALED">LOCKED_SEALED</option>
                <option value="SAILING">SAILING (In Transit)</option>
                <option value="ARRIVED">ARRIVED</option>
              </select>
            </div>

            <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-bold">
              <i class="fas fa-download me-1"></i> Export Manifests CSV
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Card 4: Consignment Bookings & Waybills -->
    <div class="col-xl-4">
      <div class="card shadow-sm border-0 h-100 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-bold text-dark">
            <i class="fas fa-boxes text-warning me-2"></i>Waybill Consignments
          </h6>
          <span class="badge bg-light text-dark border">{{ number_format($counts['bookings']) }} Bookings</span>
        </div>
        <div class="card-body">
          <p class="text-muted small">Cargo waybill records, shipper identities, declared weights/values, and latest GPS telemetry milestone coordinates.</p>

          <form action="{{ route('admin.reports.export', ['category' => 'bookings']) }}" method="GET">
            <div class="mb-3">
              <label class="form-label small text-muted fw-bold">Quick Presets</label>
              <div class="btn-group btn-group-sm w-100" role="group">
                <button type="submit" name="preset" value="this_month" class="btn btn-outline-secondary">This Month</button>
                <button type="submit" name="preset" value="all" class="btn btn-outline-secondary">All Time</button>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted">Filter Cargo Status</label>
              <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="GATE_IN">GATE_IN</option>
                <option value="CUSTOMS_INSPECTION">CUSTOMS_INSPECTION</option>
                <option value="SAILING">SAILING</option>
                <option value="DELIVERED">DELIVERED</option>
              </select>
            </div>

            <button type="submit" class="btn btn-outline-warning text-dark btn-sm w-100 fw-bold">
              <i class="fas fa-download me-1"></i> Export Bookings CSV
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Card 5: Immutable Governance Audit Trail -->
    <div class="col-xl-4">
      <div class="card shadow-sm border-0 h-100 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-bold text-dark">
            <i class="fas fa-shield-alt text-danger me-2"></i>Governance Audit Trail
          </h6>
          <span class="badge bg-light text-dark border">{{ number_format($counts['audit']) }} Logs</span>
        </div>
        <div class="card-body">
          <p class="text-muted small">Immutable security logs of administrative actions, password resets, financial changes, and IP addresses.</p>

          <form action="{{ route('admin.reports.export', ['category' => 'audit']) }}" method="GET">
            <div class="mb-3">
              <label class="form-label small text-muted fw-bold">Quick Presets</label>
              <div class="btn-group btn-group-sm w-100" role="group">
                <button type="submit" name="preset" value="this_month" class="btn btn-outline-secondary">This Month</button>
                <button type="submit" name="preset" value="all" class="btn btn-outline-secondary">All Time</button>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small text-muted">Filter Action Code</label>
              <input type="text" name="status" class="form-control form-control-sm" placeholder="e.g. PASSWORD_RESET_REQUESTED">
            </div>

            <button type="submit" class="btn btn-outline-danger btn-sm w-100 fw-bold">
              <i class="fas fa-download me-1"></i> Export Audit Trail CSV
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

