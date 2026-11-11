# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

FastCargo serves four distinct roles across isolated portals plus unauthenticated public visitors:

- **Retail & Corporate Customers (`RETAIL_CUSTOMER`, `CORPORATE_USER`)**: Individual shippers and B2B exporters/importers across ASEAN who create Ocean (LCL/FCL), Air Express, and Cross-Border Road bookings, track shipments end to end, download English PDF invoices, trigger QR payment initialization, request corporate credit/tier upgrades, query an in-portal AI assistant, and receive or query updates via WhatsApp (`TRACK <resi>`). Authenticated at `customer.*` with verified email and mandatory TOTP 2FA.
- **Hub Operators (`OPERATOR_USER`)**: Invitation-only warehouse, port handler, and fleet operators who review/approve/reject customer bookings, build and seal cargo manifests, post tracking milestones, capture electronic Proof of Delivery (ePOD), file incidents, monitor fleet telemetry and hub-scoped cockpit maps (`Manifest | Cargo`), and consult the AI Ops copilot. Restricted to VPN/IP-whitelisted sources (`operator.vpn`), single-use invitation links, and mandatory TOTP 2FA at `operator.*`.
- **Platform Administrators (`ADMIN_USER`)**: Internal operations and compliance administrators (no public landing or self-signup) overseeing user suspension/blocking, operator invitations, KYC/AML approvals, corporate credit, escrow ledger & settlement release, invoice administration, incident reviews, audit trail, Go WhatsApp Gateway (`waG`) pairing/dispatch, dynamic AI provider switching (`9router` / `gemini`), and network-wide cockpit maps (`Cargo | Ops | User`) at `admin.*` with TOTP 2FA.
- **Public Visitors & Operator Applicants**: Unauthenticated visitors checking shipment status at `/track/{trackingNumber}` or applying to join the hub network via `/#operator-apply` on the public landing host.

## Product Purpose

FastCargo is a unified ASEAN cross-border multi-modal freight and logistics operating system that brings shipment booking, hub manifest consolidation, tracking telemetry/ePOD, invoice & escrow governance, WhatsApp customer/operator messaging, and AI-assisted operational insights into a single coordinated workflow. It addresses fragmented multi-carrier handoffs, lack of shipment milestone visibility, manual booking-to-manifest reconciliation, and trust gaps between shippers and regional logistics hubs.

## Positioning

- **Current Stage**: Academic / portfolio enterprise system actively prepared toward live deployment on `leonproject.my.id` (not yet launched as a live commercial freight carrier).
- **Differentiating Mechanism**: Strict multi-subdomain portal separation sharing one transactional core (`booking -> manifest -> tracking/map telemetry -> ePOD/escrow -> WhatsApp & audit log`), paired with a self-hosted Go WhatsApp Gateway (`services/waG`), interactive role-scoped Leaflet cockpit maps, and a runtime-switchable dual-provider AI layer (`AiManager` across `9router` and `gemini`).

## Operating Context

- **Geographic Scope**: Southeast Asian cross-border trade corridors across seeded regional seaports, international airports, cross-border road checkpoints, and warehouse hubs (~50 ASEAN hubs seeded for local/test workflows).
- **Runtime Topology**: Containerized locally via `podman-compose.yml` (`nginx` on `127.0.0.1:8080` / `8443`, `php`, Redis `worker`, `mysql` 8.0, `redis` 7, `soketi` WebSocket server, hardened `wag` Go container, and optional `cloudflared` tunnel profile). Target production deployment uses OCI (`Indonesia North (Batam)`) fronted by Cloudflare Tunnel + Cloudflare Zero Trust Access on `leonproject.my.id` (not yet deployed live).
- **Phase Roadmap Boundary**:
  - *Phase 1 & 1B (Implemented)*: Subdomain routing, role authentication, operator IP whitelist, TOTP 2FA, booking workflow, manifest consolidation, tracking timeline, PDF invoicing, and admin governance.
  - *Phase 2 (Implemented locally)*: Switchable AI Copilot & insight cards (`AiManager`), self-hosted Go WhatsApp Gateway (`waG`) live pairing/outbound notifications/bot webhook, and Leaflet cockpit maps across Customer, Operator, and Admin dashboards.
  - *Phase 3 (Pending / Not Yet Live)*: Live Dana Business API QRIS payment & automated escrow settlement webhooks, OCI Object Storage file backend wiring, full GeoNames ASEAN offline dump / OpenWeatherMap runtime sync, live IoT fleet telemetry ingestion, immutable audit trail enforcement, and production domain + TLS deployment.
- **Timezones**: Server and Admin default to `Asia/Jakarta` (WIB); Operator and Customer views adapt to gateway/profile region preferences.

## Capabilities and Constraints

- **Subdomain & Security Perimeters (`routes/web.php`, `bootstrap/app.php`)**:
  - Enforces separate entry domains per portal (`*.fastcargo.local:8080` locally; target `leonproject.my.id`, `customer.leonproject.my.id`, `operator.leonproject.my.id`, `admin.leonproject.my.id` via Cloudflare Tunnel & Zero Trust).
  - `admin.*` exposes no public landing page or registration flow and redirects unauthenticated requests straight to login.
  - `operator.*` enforces `operator.vpn` IP allowlisting (toggleable via `OPERATOR_IP_WHITELIST_ENABLED` when Zero Trust Access handles perimeter) plus invitation token onboarding and TOTP 2FA.
- **Operational Workflows (`app/Modules/`)**:
  - Customers manage bookings, public/private tracking, PDF invoices (`barryvdh/laravel-dompdf`), QR payment initialization, and corporate upgrades.
  - Operators manage bookings (`approve`, `reject`, `assign`), manifests (`create`, `update`, `seal`), tracking milestone events, ePOD, incidents, fleet views, and duty status.
  - Admins govern users, operator applications/invitations, KYC/AML, escrow release, invoices, audit logs, AI provider selection, and WhatsApp session controls.
- **Integrated Services**:
  - `App\Modules\Shared\AI\AiManager`: switches between `9router` and `gemini` via admin settings.
  - `App\Modules\Shared\WhatsApp\WaGatewayClient` & `services/waG`: read-only rootless Go service (`whatsmeow`) handling live QR session state, test/event notifications, and inbound bot webhook replies.
- **Non-Negotiable Constraints**:
  - Phase 3 features (Dana Business API live QRIS processing, OCI cloud storage, live commercial fleet IoT telemetry) must not be portrayed in copy or UX flows as active production financial/live services until integrated and verified.
  - Visual improvements on future **landing and showcase surfaces** follow a **comp-first** build path (`.impeccable/config.json`: `"buildPath": "comp"`), generating concept comps before code, whereas **operational dashboard screens and documentation remain code-first**.

## Brand Commitments

- **UI & Document Language**: Professional English (`APP_LOCALE=en`) across all public pages, Customer/Operator/Admin portals, and generated PDF invoices; developer comments and optional WhatsApp conversations may use Indonesian.
- **Brand Name & Mark**: `FastCargo` with the existing geometric hex-box SVG mark in `resources/views/layouts/landing.blade.php`.
- **Legal Entity, Official Domain & Commercial Contact**: Target production domain locked to `leonproject.my.id` (`*.leonproject.my.id` via Cloudflare DNS & Tunnel). Registered corporate legal entity and commercial support contacts remain **Undecided / TBD**; seeded company names (`PT Nusantara Logistik Hub`) and sample resi identifiers (`RPN-20261106-001`) are development fixtures.
- **Visual System Documentation (`DESIGN.md`)**: Intentionally excluded from this document; design tokens and component styling are currently defined in code only and not yet captured in a `DESIGN.md`.

## Evidence on Hand

- **Present in Repository**:
  - Working modular Laravel + Livewire + Bootstrap codebase across Landing, Customer, Operator, Admin, and Shared modules (`app/Modules/`).
  - Hardened containerized Go WhatsApp gateway (`services/waG`) with unit tests and live local Podman integration (`podman-compose.yml`).
  - Feature and integration test suites in `tests/Feature/` (`FastCargoEndToEndTest.php`, `CockpitMapsTest.php`, `Phase2AiIntegrationTest.php`, `Phase2WaGatewayTest.php`, `CustomerEmailVerificationTest.php`, `AdminTwoFactorTest.php`).
  - ~50 seeded ASEAN hubs/ports and local Leaflet spatial cockpit views (`CockpitMapService.php`).
- **Demo / Placeholder Copy Currently in Codebase (Not Real Production Proof)**:
  - Landing page claims in `resources/views/landing/index.blade.php` — including `"Now Live: 10 ASEAN Countries Multi-Modal Coverage"`, `99.8%` SLA On-Time KPI, and the simulated vessel snippet (`KM Sora Bahari 08 [VESSEL]`) — are **demonstration/placeholder copy**, not audited operational metrics or live commercial tonnage.
  - Stock template imagery in `public/assets/landing/img/` (`hero.jpg`, plus unused template assets `avatar1.jpg`–`avatar4.jpg`, `dashboard.jpg`, `integrate.jpg`, `office.jpg`) is illustrative placeholder media, not real customer photography or verified testimonials.
- **Absent / Not On Hand**:
  - Audited SLA reports, real shipper testimonials/logos, registered corporate legal entity/forwarding licenses, live verified Dana QRIS production receipts, and `DESIGN.md`.

## Product Principles

1. **Truth Before Polish**: Keep seeded/demo logistics figures, simulated telemetry, and pending Phase 3 payment/escrow capabilities clearly distinct from live verified proof; never fabricate testimonials, legal credentials, or uptime claims.
2. **Perimeter & Role Discipline**: Maintain strict isolation across Public, Customer, VPN+2FA Operator, and Private 2FA Admin surfaces so privilege boundaries never depend on UI hiding alone.
3. **Connected Operational Spine**: Keep booking, manifest consolidation, tracking/cockpit mapping, ePOD, escrow governance, WhatsApp alerts, and audit logging synchronized on one consistent shipment state model.
4. **Decoupled Infrastructure Adapters**: Preserve swappable boundaries (`AiManager`, self-hosted `waG`, local geodata fallbacks) so operational visibility works without rigid single-vendor lock-in.
5. **Clarity Under Operational Density**: Present dense freight tables, maps, and governance controls with clear hierarchy, responsive mobile reflow, and predictable actions across viewports.

## Accessibility & Inclusion

- **Existing Baselines**: `resources/views/layouts/landing.blade.php` implements a `.skip-link` to `#main`, ARIA navigation landmarks/labels (`aria-labelledby`, `aria-label`, `aria-expanded`), and accessible form labels; portal dashboards include `<576px` mobile responsive table/card stacking, wrapped alert badges, and `>=44x44px` tap targets on cockpit map controls.
- **Undecided / Next Accessibility Scope**: Formal WCAG 2.1 AA audit sign-off and any multi-language UI toggle beyond `en` remain undecided.

