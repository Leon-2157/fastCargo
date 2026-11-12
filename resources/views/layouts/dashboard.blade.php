<!DOCTYPE html>
<html data-bs-theme="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>@yield('title', 'Dashboard') — FastCargo Enterprise</title>

  <link rel="stylesheet" href="/assets/dashboard/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
  <link rel="stylesheet" href="/assets/dashboard/css/styles.min.css?v=2">
  @livewireStyles
  @stack('styles')
</head>
<body id="page-top">
<div id="wrapper">
  <!-- Sidebar -->
  <nav class="navbar align-items-start p-0 sidebar sidebar-dark accordion bg-gradient-primary navbar-dark">
    <div class="container-fluid d-flex flex-column p-0">
      <a class="navbar-brand d-flex justify-content-center align-items-center m-0 sidebar-brand py-3" href="#">
        <div class="sidebar-brand-icon">
          <i class="fas fa-shipping-fast fs-3"></i>
        </div>
        <div class="mx-3 sidebar-brand-text">
          <span>FastCargo</span>
          <div style="font-size: 9px; opacity: 0.7; letter-spacing: 1px;">
            @if(isset($role)) {{ strtoupper($role) }} PORTAL @else ENTERPRISE @endif
          </div>
        </div>
      </a>
      <hr class="my-0 sidebar-divider">

      <ul class="navbar-nav text-light" id="accordionSidebar">
        @php
          $currentRole = $role ?? 'customer';
        @endphp

        @if($currentRole === 'customer')
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}" href="{{ route('customer.dashboard') }}">
              <i class="fas fa-tachometer-alt"></i><span>Overview</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('customer.bookings.*') ? 'active' : '' }}" href="{{ route('customer.bookings.index') }}">
              <i class="fas fa-boxes"></i><span>My Bookings</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('customer.tracking.*') ? 'active' : '' }}" href="{{ route('customer.tracking.index') }}">
              <i class="fas fa-map-marked-alt"></i><span>Live Tracking</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('customer.invoices.*') ? 'active' : '' }}" href="{{ route('customer.invoices.index') }}">
              <i class="fas fa-file-invoice-dollar"></i><span>Invoices &amp; Pay</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('customer.profile.*') ? 'active' : '' }}" href="{{ route('customer.profile.show') }}">
              <i class="fas fa-user-circle"></i><span>Account &amp; KYC</span>
            </a>
          </li>

        @elseif($currentRole === 'operator')
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('operator.dashboard') ? 'active' : '' }}" href="{{ route('operator.dashboard') }}">
              <i class="fas fa-tachometer-alt"></i><span>Ops Overview</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('operator.manifests.*') ? 'active' : '' }}" href="{{ route('operator.manifests.index') }}">
              <i class="fas fa-clipboard-list"></i><span>Manifest Builder</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('operator.bookings.*') ? 'active' : '' }}" href="{{ route('operator.bookings.index') }}">
              <i class="fas fa-check-circle"></i><span>Booking Approvals</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('operator.tracking.*') ? 'active' : '' }}" href="{{ route('operator.tracking.index') }}">
              <i class="fas fa-route"></i><span>Milestone Events</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('operator.fleet.*') ? 'active' : '' }}" href="{{ route('operator.fleet.index') }}">
              <i class="fas fa-satellite"></i><span>Fleet Telemetry</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('operator.incidents.*') ? 'active' : '' }}" href="{{ route('operator.incidents.index') }}">
              <i class="fas fa-exclamation-triangle"></i><span>Incident Logs</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('operator.profile.*') ? 'active' : '' }}" href="{{ route('operator.profile.show') }}">
              <i class="fas fa-id-badge"></i><span>Hub Duty Profile</span>
            </a>
          </li>

        @elseif($currentRole === 'admin')
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
              <i class="fas fa-chart-line"></i><span>Governance Cockpit</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
              <i class="fas fa-users-cog"></i><span>User Directory</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.invitations.*') ? 'active' : '' }}" href="{{ route('admin.invitations.index') }}">
              <i class="fas fa-envelope-open-text"></i><span>Operator Applications</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.kyc.*') ? 'active' : '' }}" href="{{ route('admin.kyc.index') }}">
              <i class="fas fa-user-check"></i><span>KYC &amp; AML Approvals</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.finance.*') ? 'active' : '' }}" href="{{ route('admin.finance.index') }}">
              <i class="fas fa-vault"></i><span>Escrow &amp; Revenue</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}" href="{{ route('admin.invoices.index') }}">
              <i class="fas fa-file-invoice"></i><span>All Invoices</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.incidents.*') ? 'active' : '' }}" href="{{ route('admin.incidents.index') }}">
              <i class="fas fa-fire-extinguisher"></i><span>Escalation Review</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.credits.*') ? 'active' : '' }}" href="{{ route('admin.credits.index') }}">
              <i class="fas fa-landmark"></i><span>Corporate Credit</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.wa.*') ? 'active' : '' }}" href="{{ route('admin.wa.index') }}">
              <i class="fab fa-whatsapp"></i><span>WA Gateway Station</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}" href="{{ route('admin.audit.index') }}">
              <i class="fas fa-shield-alt"></i><span>Immutable Audit Trail</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}">
              <i class="fas fa-cogs"></i><span>System &amp; AI Settings</span>
            </a>
          </li>
        @endif
      </ul>

      <div class="text-center d-none d-md-inline mt-3">
        <button class="btn rounded-circle border-0" id="sidebarToggle" type="button"></button>
      </div>
    </div>
  </nav>

  <!-- Content Wrapper -->
  <div class="d-flex flex-column" id="content-wrapper">
    <div id="content">
      <!-- Topbar -->
      <nav class="navbar navbar-expand bg-white shadow mb-4 topbar static-top">
        <div class="container-fluid">
          <button class="btn btn-link d-md-none me-3 rounded-circle" id="sidebarToggleTop" type="button">
            <i class="fas fa-bars"></i>
          </button>

          <!-- Topbar Status Info -->
          <div class="d-none d-sm-inline-block me-auto">
            <span class="badge bg-primary text-uppercase me-2">{{ $currentRole }}</span>
            <span class="text-secondary small">
              <i class="fas fa-network-wired text-success me-1"></i> ASEAN Multi-Modal Network Connected
            </span>
          </div>

          <!-- Topbar Navbar -->
          <ul class="navbar-nav flex-nowrap ms-auto">
            <!-- User Info Dropdown -->
            <li class="nav-item dropdown no-arrow">
              <a class="dropdown-toggle nav-link" data-bs-toggle="dropdown" aria-expanded="false" href="#">
                <span class="d-none d-lg-inline me-2 text-gray-600 small fw-bold">
                  {{ auth($currentRole)->user()->name ?? 'Operator / User' }}
                </span>
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                  <i class="fas fa-user-tie"></i>
                </div>
              </a>
              <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in">
                <span class="dropdown-item-text small text-muted">
                  Logged in as: <strong>{{ auth($currentRole)->user()->email ?? '' }}</strong>
                </span>
                <div class="dropdown-divider"></div>
                <form action="{{ route($currentRole . '.logout') }}" method="POST">
                  @csrf
                  <button type="submit" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-danger"></i> Logout
                  </button>
                </form>
              </div>
            </li>
          </ul>
        </div>
      </nav>

      <!-- Main Content -->
      <div class="container-fluid pb-5">
        @if(session('success'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @if(session('error'))
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @yield('content')
      </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white sticky-footer">
      <div class="container my-auto">
        <div class="text-center my-auto copyright">
          <span>Copyright © FastCargo 2026. ASEAN Unified Freight Operations.</span>
        </div>
      </div>
    </footer>
  </div>

  <a class="border rounded d-inline scroll-to-top" href="#page-top"><i class="fas fa-angle-up"></i></a>
</div>

<!-- Floating Livewire AI Chat Widget (Universal across all roles) -->
@livewire('ai-chat-widget', ['role' => $currentRole, 'pageContext' => $pageContext ?? []])

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/dashboard/js/script.min.js"></script>
@livewireScripts
@stack('scripts')
</body>
</html>

