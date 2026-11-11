<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'FastCargo — ASEAN Cross-Border Freight & Logistics Operating System')</title>
  <meta name="description" content="FastCargo is the modern freight & multi-modal logistics operating system across 10 ASEAN countries. Multi-currency, escrow settlement, and real-time fleet telemetry.">
  <meta name="theme-color" content="#0E1526">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/landing/css/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/landing/css/style.css">
  <style>
    body.page-dark { background: var(--ink); color: var(--on-dark); }
    .site-header.site-header--solid {
      background: rgba(14, 21, 38, .92);
      backdrop-filter: saturate(180%) blur(12px);
      -webkit-backdrop-filter: saturate(180%) blur(12px);
      box-shadow: 0 1px 0 rgba(255, 255, 255, .08);
    }
    .site-header.site-header--solid.is-scrolled { background: rgba(244, 246, 250, .92); }
    .btn-outline-light { border-color: rgba(255, 255, 255, .45); color: #fff; }
    .btn-outline-light:hover { background: #fff; color: var(--ink); border-color: #fff; }
    .btn-outline-info { border-color: rgba(56, 189, 248, .55); color: #7dd3fc; }
    .btn-outline-info:hover { background: #38bdf8; color: var(--ink); border-color: #38bdf8; }
    .landing-input {
      background-color: #131c31 !important;
      border-color: rgba(255, 255, 255, .24) !important;
      color: #edf1f8 !important;
    }
    .landing-input::placeholder { color: #9aa7c2 !important; opacity: 1; }
    .landing-input:focus {
      background-color: #18233d !important;
      border-color: var(--teal) !important;
      box-shadow: 0 0 0 .2rem rgba(15, 181, 162, .25) !important;
    }
  </style>
  @stack('styles')
</head>
<body class="@yield('body-class')">

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header @yield('header-class')" id="siteHeader" data-header>
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="FastCargo home">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 32 32" width="30" height="30">
          <path d="M4 8l12-5 12 5v16l-12 5-12-5V8z" fill="#3B82F6"/>
          <path d="M16 3v26M4 8l12 7 12-7" stroke="#0E1526" stroke-width="2" fill="none"/>
        </svg>
      </span>
      <span class="brand-word">FastCargo</span>
    </a>

    <nav class="main-nav" id="primary-menu" aria-label="Primary">
      <button class="nav-close" type="button" aria-label="Close menu" data-nav-close>
        <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
      <ul class="nav-list">
        <li><a href="/#track">Track Shipment</a></li>
        <li><a href="/#services">ASEAN Routes</a></li>
        <li><a href="/#operator-apply">Join as Operator</a></li>
      </ul>
      <div class="nav-cta">
        <a class="btn btn-ghost" href="{{ config('app.portal_urls.customer', 'http://customer.fastcargo.local:8080') }}/login">Sign In</a>
        <a class="btn btn-primary" href="{{ config('app.portal_urls.customer', 'http://customer.fastcargo.local:8080') }}/register">Register Customer</a>
      </div>
    </nav>

    <button class="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="primary-menu" data-nav-toggle>
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<main id="main">
  @yield('content')
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-bottom d-flex justify-content-between align-items-center py-4 border-top border-secondary border-opacity-25">
      <span class="text-light opacity-75">© 2026 FastCargo Enterprise. ASEAN Freight & Multi-Modal Logistics Operating System.</span>
      <div class="footer-links">
        <a href="/#top" class="text-light opacity-75 text-decoration-none me-3">Back to Top</a>
        <a href="/#operator-apply" class="text-light opacity-75 text-decoration-none">Operator Program</a>
      </div>
    </div>
  </div>
</footer>

<script src="/assets/landing/js/vendor/bootstrap.bundle.min.js"></script>
<script src="/assets/landing/js/main.js"></script>
@stack('scripts')
</body>
</html>

