@props([
    'mapId' => 'cockpitMap',
    'title' => 'ASEAN Operations Map',
    'payload' => [],
    'refreshUrl' => '',
])

@php
  $layers = $payload['layers'] ?? [];
  $defaultLayer = $payload['default_layer'] ?? (array_key_first($layers) ?? 'cargo');
  $initialLayer = $layers[$defaultLayer] ?? ['label' => 'Map', 'description' => '', 'items' => [], 'unmapped_count' => 0];
  $generatedAt = $payload['generated_at'] ?? now()->format('H:i:s');
@endphp

<div
  class="card cockpit-map-card w-100 h-100"
  data-cockpit-map-root="{{ $mapId }}"
  data-refresh-url="{{ $refreshUrl }}"
>
  <div class="card-header bg-white py-3 px-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="me-2">
      <h6 class="m-0 fw-bold cockpit-heading">
        <i class="fas fa-globe-asia text-primary me-1" aria-hidden="true"></i>
        <span>{{ $title }}</span>
      </h6>
      <p class="mb-0 mt-1 cockpit-subcopy" data-map-description>
        {{ $initialLayer['description'] ?? 'Interactive operational telemetry layer.' }}
      </p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <div class="btn-group cockpit-layer-group" role="group" aria-label="{{ $title }} layers">
        @foreach($layers as $layerKey => $layerData)
          @php $isActive = $layerKey === $defaultLayer; @endphp
          <button
            type="button"
            class="btn btn-sm cockpit-touch-btn cockpit-layer-btn {{ $isActive ? 'is-active' : '' }}"
            data-layer-switch="{{ $layerKey }}"
            aria-pressed="{{ $isActive ? 'true' : 'false' }}"
          >
            <span>{{ $layerData['label'] }}</span>
            <span class="badge ms-1 cockpit-count-badge" data-layer-count="{{ $layerKey }}">
              {{ count($layerData['items'] ?? []) }}
            </span>
          </button>
        @endforeach
      </div>

      @if($refreshUrl !== '')
        <button
          type="button"
          class="btn btn-sm btn-outline-secondary cockpit-touch-btn"
          data-map-refresh
          aria-label="Refresh map data"
          title="Refresh map telemetry"
        >
          <i class="fas fa-sync-alt me-1" aria-hidden="true"></i>
          <span>Refresh</span>
        </button>
      @endif
    </div>
  </div>

  <div class="card-body p-0 position-relative d-flex flex-column flex-grow-1">
    <div id="{{ $mapId }}" class="cockpit-map-canvas" tabindex="0" aria-label="{{ $title }} interactive viewport"></div>

    <div class="cockpit-map-overlay d-none" data-map-loading role="status" aria-live="polite">
      <div class="cockpit-overlay-box">
        <span class="spinner-border spinner-border-sm text-primary me-2" aria-hidden="true"></span>
        <span>Updating spatial telemetry...</span>
      </div>
    </div>

    <div class="cockpit-map-overlay d-none" data-map-empty aria-live="polite">
      <div class="cockpit-overlay-box text-center">
        <i class="fas fa-map-pin text-primary mb-2 d-block fs-5" aria-hidden="true"></i>
        <div class="fw-bold cockpit-heading" data-map-empty-title>No mapped points in this layer</div>
        <div class="cockpit-subcopy mt-1" data-map-empty-body>Records with coordinates will appear automatically on this ASEAN map.</div>
      </div>
    </div>

    <div class="cockpit-map-error d-none" data-map-error role="alert">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span data-map-error-text>Unable to refresh telemetry data.</span>
        <button type="button" class="btn btn-sm btn-outline-danger cockpit-touch-btn" data-map-retry>
          Retry
        </button>
      </div>
    </div>

    <div class="cockpit-tile-notice d-none" data-tile-fallback aria-live="polite">
      <i class="fas fa-info-circle me-1" aria-hidden="true"></i>
      Base map tiles unavailable; vector corridors &amp; pins remain active.
    </div>
  </div>

  <div class="card-footer bg-white py-2 px-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="cockpit-meta-row" data-map-stats>
      <span class="fw-semibold cockpit-heading" data-map-mapped-summary>
        {{ count($initialLayer['items'] ?? []) }} mapped
      </span>
      <span class="cockpit-subcopy" data-map-unmapped-summary>
        @if(($initialLayer['unmapped_count'] ?? 0) > 0)
          · {{ $initialLayer['unmapped_count'] }} without coordinates
        @endif
      </span>
      <span class="cockpit-subcopy" data-map-updated-at>
        · Updated {{ $generatedAt }}
      </span>
    </div>

    <div class="cockpit-legend" data-map-legend aria-label="Map marker legend"></div>
  </div>
</div>

<script type="application/json" id="{{ $mapId }}-initial-payload">@json($payload)</script>

@once
  @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
      .cockpit-map-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
        overflow: hidden;
      }

      .cockpit-heading {
        color: #0f172a;
      }

      .cockpit-subcopy {
        color: #475569;
        font-size: 0.8125rem;
        line-height: 1.4;
      }

      .cockpit-touch-btn {
        min-height: 44px;
        min-width: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        border-radius: 0.5rem;
        transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease;
      }

      .cockpit-layer-group {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.25rem;
      }

      .cockpit-layer-group > .cockpit-layer-btn {
        border-radius: 0.5rem !important;
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        color: #1e293b;
        padding: 0.375rem 0.75rem;
      }

      .cockpit-layer-group > .cockpit-layer-btn:hover {
        background-color: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a;
      }

      .cockpit-layer-group > .cockpit-layer-btn.is-active {
        background-color: #224abe;
        border-color: #224abe;
        color: #ffffff;
      }

      .cockpit-layer-btn .cockpit-count-badge {
        background-color: #e2e8f0;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
      }

      .cockpit-layer-btn.is-active .cockpit-count-badge {
        background-color: rgba(255, 255, 255, 0.22);
        color: #ffffff;
      }

      .cockpit-workspace-row button:focus-visible,
      .cockpit-workspace-row a:focus-visible,
      .cockpit-map-canvas:focus-visible {
        outline: 3px solid #224abe;
        outline-offset: 2px;
      }

      .cockpit-map-canvas {
        width: 100%;
        flex: 1 1 auto;
        min-height: 480px;
        background-color: #eef3f8;
        z-index: 1;
      }

      .cockpit-map-overlay {
        position: absolute;
        inset: 0;
        z-index: 450;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background-color: rgba(248, 250, 252, 0.72);
        pointer-events: none;
      }

      .cockpit-overlay-box {
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 0.75rem;
        padding: 0.875rem 1.25rem;
        max-width: 320px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        color: #0f172a;
        font-size: 0.875rem;
        pointer-events: auto;
      }

      .cockpit-map-error {
        position: absolute;
        top: 0.75rem;
        left: 0.75rem;
        right: 0.75rem;
        z-index: 460;
        background-color: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
      }

      .cockpit-tile-notice {
        position: absolute;
        bottom: 0.625rem;
        left: 0.625rem;
        z-index: 440;
        background-color: rgba(15, 23, 42, 0.88);
        color: #ffffff;
        border-radius: 0.375rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
      }

      .cockpit-meta-row,
      .cockpit-legend {
        font-variant-numeric: tabular-nums;
      }

      .cockpit-legend {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
      }

      .cockpit-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #334155;
      }

      .cockpit-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        border: 2px solid #ffffff;
        box-shadow: 0 0 0 1px #475569;
        display: inline-block;
      }

      .cockpit-ai-slot > .card {
        margin-bottom: 0 !important;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
      }

      .cockpit-table-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
      }

      .cockpit-tabular {
        font-variant-numeric: tabular-nums;
      }

      @media (max-width: 1199.98px) {
        .cockpit-map-canvas {
          min-height: 360px;
          height: 360px;
        }
      }

      @media (max-width: 767.98px) {
        .cockpit-map-canvas {
          min-height: 300px;
          height: 300px;
        }
      }

      @media (max-width: 575.98px) {
        .cockpit-table-card thead {
          display: none;
        }

        .cockpit-table-card tbody,
        .cockpit-table-card tr,
        .cockpit-table-card td {
          display: block;
          width: 100%;
        }

        .cockpit-table-card tbody tr {
          padding: 0.75rem 1rem;
          border-bottom: 1px solid #e2e8f0;
        }

        .cockpit-table-card tbody tr:last-child {
          border-bottom: 0;
        }

        .cockpit-table-card tbody td {
          border: 0 !important;
          padding: 0.25rem 0 !important;
          text-align: left !important;
        }

        .cockpit-table-card tbody td:last-child {
          padding-top: 0.5rem !important;
        }
      }

      @media (prefers-reduced-motion: reduce) {
        .cockpit-touch-btn {
          transition: none;
        }
      }
    </style>
  @endpush

  @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
      (function () {
        window.FastCargoCockpitMaps = window.FastCargoCockpitMaps || {};

        const MARKER_STYLES = {
          cargo: { fill: '#1d4ed8', radius: 8, label: 'Running Cargo' },
          delivered: { fill: '#15803d', radius: 7, label: 'Delivered' },
          manifest: { fill: '#0369a1', radius: 8, label: 'Active Manifest' },
          ops_active: { fill: '#15803d', radius: 8, label: 'Operator On Duty' },
          ops_off: { fill: '#b45309', radius: 8, label: 'Operator Off Duty' },
          user_admin: { fill: '#7c3aed', radius: 7, label: 'Admin' },
          user_operator: { fill: '#0369a1', radius: 7, label: 'Operator User' },
          user_customer: { fill: '#ea580c', radius: 7, label: 'Customer User' },
          hub_anchor: { fill: '#0f172a', radius: 9, label: 'Assigned Station Hub' }
        };

        function escapeHtml(value) {
          return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        }

        function sanitizeInternalUrl(url) {
          if (typeof url === 'string' && url.startsWith('/')) {
            return url;
          }
          return null;
        }

        function buildLegendItems(layerKey, items, hubAnchor) {
          const types = new Set();
          items.forEach((item) => {
            if (item && item.marker_type) {
              types.add(item.marker_type);
            }
          });
          if (hubAnchor) {
            types.add('hub_anchor');
          }
          if (types.size === 0) {
            if (layerKey === 'ops') {
              types.add('ops_active');
              types.add('ops_off');
            } else if (layerKey === 'user') {
              types.add('user_customer');
              types.add('user_operator');
            } else if (layerKey === 'manifest') {
              types.add('manifest');
            } else {
              types.add('cargo');
            }
          }

          return Array.from(types).map((typeKey) => {
            const cfg = MARKER_STYLES[typeKey] || MARKER_STYLES.cargo;
            return '<span class="cockpit-legend-item">' +
              '<span class="cockpit-legend-dot" style="background-color:' + cfg.fill + ';"></span>' +
              '<span>' + escapeHtml(cfg.label) + '</span>' +
              '</span>';
          }).join('');
        }

        function initCockpitMap(root) {
          const mapId = root.dataset.cockpitMapRoot;
          const payloadNode = document.getElementById(mapId + '-initial-payload');
          if (!payloadNode || typeof L === 'undefined') {
            return;
          }

          let state = {
            payload: JSON.parse(payloadNode.textContent || '{}'),
            activeLayer: null,
            markersById: {},
            tileFailed: false
          };
          state.activeLayer = state.payload.default_layer || Object.keys(state.payload.layers || {})[0] || 'cargo';

          const mapCanvas = document.getElementById(mapId);
          const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
          const map = L.map(mapCanvas, { scrollWheelZoom: false }).setView([-1.5, 107.0], 5);

          const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap | FastCargo Telemetry',
            maxZoom: 18
          });

          const tileNotice = root.querySelector('[data-tile-fallback]');
          let tileErrors = 0;
          tileLayer.on('tileerror', () => {
            tileErrors += 1;
            if (tileErrors >= 3 && tileNotice) {
              tileNotice.classList.remove('d-none');
            }
          });
          tileLayer.on('tileload', () => {
            if (tileNotice) {
              tileNotice.classList.add('d-none');
            }
          });
          tileLayer.addTo(map);

          const vectorGroup = L.layerGroup().addTo(map);
          const loadingEl = root.querySelector('[data-map-loading]');
          const emptyEl = root.querySelector('[data-map-empty]');
          const errorEl = root.querySelector('[data-map-error]');
          const errorTextEl = root.querySelector('[data-map-error-text]');
          const descEl = root.querySelector('[data-map-description]');
          const mappedSummaryEl = root.querySelector('[data-map-mapped-summary]');
          const unmappedSummaryEl = root.querySelector('[data-map-unmapped-summary]');
          const updatedAtEl = root.querySelector('[data-map-updated-at]');
          const legendEl = root.querySelector('[data-map-legend]');

          function updateCounts() {
            const layers = state.payload.layers || {};
            Object.keys(layers).forEach((key) => {
              const badge = root.querySelector('[data-layer-count="' + key + '"]');
              if (badge) {
                badge.textContent = String((layers[key].items || []).length);
              }
            });
          }

          function renderLayer(layerKey, shouldFitBounds) {
            const layers = state.payload.layers || {};
            if (!layers[layerKey]) {
              return;
            }
            state.activeLayer = layerKey;
            state.markersById = {};
            vectorGroup.clearLayers();

            root.querySelectorAll('[data-layer-switch]').forEach((btn) => {
              const active = btn.dataset.layerSwitch === layerKey;
              btn.classList.toggle('is-active', active);
              btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            const layer = layers[layerKey];
            const items = layer.items || [];
            const hubAnchor = state.payload.hub_anchor || null;

            if (descEl) {
              descEl.textContent = layer.description || '';
            }
            if (mappedSummaryEl) {
              mappedSummaryEl.textContent = items.length + ' mapped';
            }
            if (unmappedSummaryEl) {
              const unmapped = Number(layer.unmapped_count || 0);
              unmappedSummaryEl.textContent = unmapped > 0 ? ' · ' + unmapped + ' without coordinates' : '';
            }
            if (updatedAtEl && state.payload.generated_at) {
              updatedAtEl.textContent = ' · Updated ' + state.payload.generated_at;
            }
            if (legendEl) {
              legendEl.innerHTML = buildLegendItems(layerKey, items, hubAnchor);
            }

            if (emptyEl) {
              emptyEl.classList.toggle('d-none', items.length > 0);
            }

            const boundsPoints = [];

            if (hubAnchor && Number.isFinite(Number(hubAnchor.lat)) && Number.isFinite(Number(hubAnchor.lng))) {
              const hubLatLng = [Number(hubAnchor.lat), Number(hubAnchor.lng)];
              boundsPoints.push(hubLatLng);
              const hubPopup =
                '<div style="min-width:190px;">' +
                '<div class="fw-bold" style="color:#0f172a;">' + escapeHtml(hubAnchor.name) + '</div>' +
                '<div class="small text-secondary">' + escapeHtml(hubAnchor.id) + ' · ' + escapeHtml(hubAnchor.city) + '</div>' +
                '<div class="mt-1 badge bg-dark">Assigned Station Hub</div>' +
                '</div>';
              L.circleMarker(hubLatLng, {
                radius: 9,
                color: '#ffffff',
                weight: 2,
                fillColor: MARKER_STYLES.hub_anchor.fill,
                fillOpacity: 1
              }).addTo(vectorGroup).bindPopup(hubPopup);
            }

            items.forEach((item) => {
              const lat = Number(item.lat);
              const lng = Number(item.lng);
              if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
              }
              boundsPoints.push([lat, lng]);

              if (item.origin && item.destination) {
                const path = [
                  [Number(item.origin.lat), Number(item.origin.lng)],
                  [lat, lng],
                  [Number(item.destination.lat), Number(item.destination.lng)]
                ];
                boundsPoints.push(path[0], path[2]);

                L.polyline(path, {
                  color: item.marker_type === 'manifest' ? '#0369a1' : '#224abe',
                  weight: 2.5,
                  opacity: 0.75,
                  dashArray: '6, 8'
                }).addTo(vectorGroup);

                L.circleMarker(path[0], {
                  radius: 4,
                  color: '#ffffff',
                  weight: 1.5,
                  fillColor: '#15803d',
                  fillOpacity: 0.9
                }).addTo(vectorGroup).bindTooltip('Origin: ' + escapeHtml(item.origin.id));

                L.circleMarker(path[2], {
                  radius: 4,
                  color: '#ffffff',
                  weight: 1.5,
                  fillColor: '#b91c1c',
                  fillOpacity: 0.9
                }).addTo(vectorGroup).bindTooltip('Dest: ' + escapeHtml(item.destination.id));
              }

              const style = MARKER_STYLES[item.marker_type] || MARKER_STYLES.cargo;
              const safeUrl = sanitizeInternalUrl(item.detail_url);
              const popupHtml =
                '<div style="min-width:210px;line-height:1.4;">' +
                '<div class="fw-bold" style="color:#0f172a;">' + escapeHtml(item.title) + '</div>' +
                '<div class="small" style="color:#475569;">' + escapeHtml(item.subtitle) + '</div>' +
                '<div class="mt-1 mb-1"><span class="badge bg-primary">' + escapeHtml(item.status) + '</span></div>' +
                '<div class="small" style="color:#334155;">' + escapeHtml(item.meta) + '</div>' +
                (safeUrl ? '<div class="mt-2"><a href="' + escapeHtml(safeUrl) + '" class="btn btn-sm btn-primary text-white py-1 px-2" style="font-size:12px;">' + escapeHtml(item.detail_label || 'Open Detail') + '</a></div>' : '') +
                '</div>';

              const marker = L.circleMarker([lat, lng], {
                radius: style.radius,
                color: '#ffffff',
                weight: 2,
                fillColor: style.fill,
                fillOpacity: 1
              }).addTo(vectorGroup).bindPopup(popupHtml);

              state.markersById[String(item.id)] = marker;
            });

            if (shouldFitBounds) {
              if (boundsPoints.length === 1) {
                map.setView(boundsPoints[0], 9, { animate: !prefersReducedMotion });
              } else if (boundsPoints.length > 1) {
                map.fitBounds(L.latLngBounds(boundsPoints), {
                  padding: [36, 36],
                  maxZoom: 10,
                  animate: !prefersReducedMotion
                });
              } else {
                map.setView([-1.5, 107.0], 5, { animate: false });
              }
            }
          }

          async function refreshData() {
            const url = root.dataset.refreshUrl;
            if (!url) {
              return;
            }
            if (loadingEl) {
              loadingEl.classList.remove('d-none');
            }
            if (errorEl) {
              errorEl.classList.add('d-none');
            }

            try {
              const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
              });
              if (!response.ok) {
                throw new Error('HTTP ' + response.status);
              }
              const nextPayload = await response.json();
              if (!nextPayload || !nextPayload.layers) {
                throw new Error('Malformed map payload');
              }
              state.payload = nextPayload;
              updateCounts();
              renderLayer(state.activeLayer, true);
            } catch (err) {
              if (errorTextEl) {
                errorTextEl.textContent = 'Failed to refresh map telemetry (' + (err && err.message ? err.message : 'network error') + ').';
              }
              if (errorEl) {
                errorEl.classList.remove('d-none');
              }
            } finally {
              if (loadingEl) {
                loadingEl.classList.add('d-none');
              }
            }
          }

          root.querySelectorAll('[data-layer-switch]').forEach((btn) => {
            btn.addEventListener('click', () => {
              renderLayer(btn.dataset.layerSwitch, true);
            });
          });

          const refreshBtn = root.querySelector('[data-map-refresh]');
          if (refreshBtn) {
            refreshBtn.addEventListener('click', refreshData);
          }

          const retryBtn = root.querySelector('[data-map-retry]');
          if (retryBtn) {
            retryBtn.addEventListener('click', refreshData);
          }

          window.FastCargoCockpitMaps[mapId] = {
            focusItem(preferredLayer, itemId) {
              const targetKey = String(itemId);
              const layers = state.payload.layers || {};

              if (preferredLayer && layers[preferredLayer] && state.activeLayer !== preferredLayer) {
                renderLayer(preferredLayer, false);
              }

              let marker = state.markersById[targetKey];
              if (!marker) {
                for (const key of Object.keys(layers)) {
                  if ((layers[key].items || []).some((entry) => String(entry.id) === targetKey)) {
                    renderLayer(key, false);
                    marker = state.markersById[targetKey];
                    break;
                  }
                }
              }

              if (!marker) {
                return false;
              }

              map.setView(marker.getLatLng(), Math.max(map.getZoom(), 7), { animate: !prefersReducedMotion });
              marker.openPopup();
              mapCanvas.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'nearest' });
              return true;
            },
            invalidateSize() {
              map.invalidateSize();
            }
          };

          updateCounts();
          renderLayer(state.activeLayer, true);

          // Real-time Fleet Telemetry via WebSocket (Soketi / Echo)
          if (window.Echo && !state.echoSubscribed) {
            state.echoSubscribed = true;
            try {
              window.Echo.channel('cargo-fleet-telemetry')
                .listen('.fleet.location.updated', function(data) {
                  if (!data || !Number.isFinite(Number(data.latitude)) || !Number.isFinite(Number(data.longitude))) return;
                  const lat = Number(data.latitude);
                  const lng = Number(data.longitude);
                  const markerKey = 'fleet_' + (data.manifest_id || data.device_id || 'live');

                  state.liveFleetMarkers = state.liveFleetMarkers || {};
                  if (state.liveFleetMarkers[markerKey]) {
                    state.liveFleetMarkers[markerKey].setLatLng([lat, lng]);
                  } else {
                    const liveIcon = L.divIcon({
                      className: 'cockpit-live-vessel',
                      html: '<div style="background:#0284c7;width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 8px #0284c7;"></div>',
                      iconSize: [14, 14]
                    });
                    const m = L.marker([lat, lng], { icon: liveIcon }).addTo(vectorGroup);
                    m.bindPopup('<strong>' + escapeHtml(data.carrier_name || 'Vessel') + '</strong><br>' + escapeHtml(data.corridor || '') + '<br>Speed: ' + (data.speed_knots || 0) + ' kts');
                    state.liveFleetMarkers[markerKey] = m;
                  }
                  if (updatedAtEl) {
                    updatedAtEl.textContent = ' · Live AIS: ' + escapeHtml(data.carrier_name || 'Fleet') + ' (' + (data.speed_knots || 0) + ' kts)';
                  }
                });
            } catch(e) {}
          }

          setTimeout(() => {
            map.invalidateSize();
          }, 150);

          window.addEventListener('resize', () => {
            map.invalidateSize();
          });
        }

        document.addEventListener('DOMContentLoaded', () => {
          document.querySelectorAll('[data-cockpit-map-root]').forEach(initCockpitMap);

          document.querySelectorAll('[data-cockpit-focus-map]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
              const mapId = trigger.dataset.cockpitFocusMap;
              const preferredLayer = trigger.dataset.cockpitLayer || '';
              const itemId = trigger.dataset.cockpitItemId || '';
              const controller = window.FastCargoCockpitMaps[mapId];
              if (controller) {
                controller.focusItem(preferredLayer, itemId);
              }
            });
          });

          const sidebarToggles = document.querySelectorAll('#sidebarToggle, #sidebarToggleTop');
          sidebarToggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
              setTimeout(() => {
                Object.values(window.FastCargoCockpitMaps).forEach((ctrl) => {
                  if (ctrl && typeof ctrl.invalidateSize === 'function') {
                    ctrl.invalidateSize();
                  }
                });
              }, 250);
            });
          });
        });
      })();
    </script>
  @endpush
@endonce
