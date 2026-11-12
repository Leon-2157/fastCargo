/**
 * FastCargo Maritime & Spatial Telemetry Simulation Engine
 * Enterprise ASEAN Cross-Border AIS Navigation & Live Passage Renderer
 */
(function (global) {
  'use strict';

  // Verified Maritime Navigational Corridors (Bypassing Landmasses)
  const MARITIME_CORRIDORS = {
    // Tanjung Priok (Jakarta, ID) <-> Port Klang (Selangor, MY)
    'IDJKT-MYPKG': [
      [-6.1020, 106.8850], // Tanjung Priok Container Terminal
      [-5.8200, 106.8500], // Jakarta Bay Outer Fairway
      [-4.8500, 106.6000], // Java Sea Northwest Deep Corridor
      [-3.3000, 106.1000], // Bangka Strait South Approach
      [-2.3500, 105.3500], // Bangka Strait Mid-Channel
      [-1.4500, 105.0000], // Bangka Strait North Exit
      [-0.3000, 104.7500], // Lingga / Riau Archipelago Passage
      [0.8500, 104.4500],  // South China Sea Southbound Transit
      [1.2600, 104.2500],  // Singapore Strait Eastern TSS Entry
      [1.2400, 103.7500],  // Singapore Strait Central Deep Channel
      [1.3500, 103.4000],  // Western Singapore Strait TSS Exit
      [1.7500, 102.8000],  // South Malacca Strait TSS Corridor
      [2.2000, 102.1500],  // Malacca Strait Central (Off Melaka Coast)
      [2.8500, 101.2500],  // Port Klang Deep Water Channel Approach
      [3.0020, 101.3980]   // Port Klang Container Terminal (MYPKG)
    ],
    // Reverse route
    'MYPKG-IDJKT': null // Auto-reversed from IDJKT-MYPKG
  };
  MARITIME_CORRIDORS['MYPKG-IDJKT'] = [...MARITIME_CORRIDORS['IDJKT-MYPKG']].reverse();

  // Helper: Haversine distance in kilometers
  function haversineDistance(p1, p2) {
    const R = 6371; // Earth radius in km
    const dLat = (p2[0] - p1[0]) * Math.PI / 180;
    const dLng = (p2[1] - p1[1]) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(p1[0] * Math.PI / 180) * Math.cos(p2[0] * Math.PI / 180) *
      Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  // Calculate cumulative distances along polyline
  function buildCumulativeSegments(points) {
    const distances = [0];
    let total = 0;
    for (let i = 0; i < points.length - 1; i++) {
      const d = haversineDistance(points[i], points[i + 1]);
      total += d;
      distances.push(total);
    }
    return { points, distances, totalKm: total, totalNm: total * 0.539957 };
  }

  // Interpolate position and heading at fraction t (0 <= t <= 1)
  function getInterpolatedState(routeData, t) {
    const { points, distances, totalKm } = routeData;
    const targetDistance = Math.max(0, Math.min(1, t)) * totalKm;

    let segmentIndex = 0;
    for (let i = 0; i < distances.length - 1; i++) {
      if (targetDistance >= distances[i] && targetDistance <= distances[i + 1]) {
        segmentIndex = i;
        break;
      }
    }

    const segStart = points[segmentIndex];
    const segEnd = points[segmentIndex + 1];
    const segDist = distances[segmentIndex + 1] - distances[segmentIndex];
    const segRatio = segDist > 0 ? (targetDistance - distances[segmentIndex]) / segDist : 0;

    const lat = segStart[0] + (segEnd[0] - segStart[0]) * segRatio;
    const lng = segStart[1] + (segEnd[1] - segStart[1]) * segRatio;

    // Calculate heading angle in degrees (compass direction)
    const y = Math.sin((segEnd[1] - segStart[1]) * Math.PI / 180) * Math.cos(segEnd[0] * Math.PI / 180);
    const x = Math.cos(segStart[0] * Math.PI / 180) * Math.sin(segEnd[0] * Math.PI / 180) -
      Math.sin(segStart[0] * Math.PI / 180) * Math.cos(segEnd[0] * Math.PI / 180) * Math.cos((segEnd[1] - segStart[1]) * Math.PI / 180);
    let heading = Math.atan2(y, x) * 180 / Math.PI;
    heading = (heading + 360) % 360;

    const passedPoints = points.slice(0, segmentIndex + 1);
    passedPoints.push([lat, lng]);

    const remainingPoints = [[lat, lng]];
    for (let j = segmentIndex + 1; j < points.length; j++) {
      remainingPoints.push(points[j]);
    }

    return {
      position: [lat, lng],
      heading: Math.round(heading),
      passedPoints,
      remainingPoints,
      progressFraction: t,
      distanceCoveredNm: (targetDistance * 0.539957).toFixed(1),
      distanceRemainingNm: (Math.max(0, totalKm - targetDistance) * 0.539957).toFixed(1)
    };
  }

  // Generate nautical compass bearing label
  function getCompassLabel(heading) {
    const sectors = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
    const idx = Math.round(heading / 22.5) % 16;
    return `${heading}° ${sectors[idx]}`;
  }

  // Format decimal coordinate into professional DMS (Degrees, Minutes, Seconds)
  function formatDms(deg, isLat) {
    const absolute = Math.abs(deg);
    const degrees = Math.floor(absolute);
    const minutesNotTruncated = (absolute - degrees) * 60;
    const minutes = Math.floor(minutesNotTruncated);
    const seconds = Math.floor((minutesNotTruncated - minutes) * 60);
    const direction = isLat ? (deg >= 0 ? 'N' : 'S') : (deg >= 0 ? 'E' : 'W');
    return `${degrees}°${String(minutes).padStart(2, '0')}'${String(seconds).padStart(2, '0')}" ${direction}`;
  }

  // Top-down container cargo vessel SVG icon
  function createVesselIcon(heading) {
    return L.divIcon({
      className: 'fastcargo-vessel-marker-wrapper',
      html: `
        <div class="vessel-marker-container" style="transform: rotate(${heading}deg);">
          <div class="vessel-sonar-ping"></div>
          <svg class="vessel-svg-icon" viewBox="0 0 40 70" width="34" height="60" xmlns="http://www.w3.org/2000/svg">
            <!-- Ship Hull -->
            <path d="M 20 2 C 12 12, 6 25, 6 48 C 6 62, 12 68, 20 68 C 28 68, 34 62, 34 48 C 34 25, 28 12, 20 2 Z" fill="#0f172a" stroke="#38bdf8" stroke-width="2"/>
            <!-- Deck base -->
            <path d="M 20 6 C 14 15, 9 26, 9 46 C 9 58, 14 64, 20 64 C 26 64, 31 58, 31 46 C 31 26, 26 15, 20 6 Z" fill="#1e293b"/>
            <!-- Container Bays (Blue, Red, Yellow TEUs) -->
            <rect x="12" y="22" width="7" height="6" fill="#0284c7" rx="1"/>
            <rect x="21" y="22" width="7" height="6" fill="#ef4444" rx="1"/>
            <rect x="12" y="30" width="7" height="6" fill="#f59e0b" rx="1"/>
            <rect x="21" y="30" width="7" height="6" fill="#0284c7" rx="1"/>
            <rect x="12" y="38" width="7" height="6" fill="#ef4444" rx="1"/>
            <rect x="21" y="38" width="7" height="6" fill="#10b981" rx="1"/>
            <!-- Bridge / Deck House -->
            <polygon points="14,48 26,48 24,56 16,56" fill="#ffffff" stroke="#94a3b8" stroke-width="1"/>
            <!-- Radar Mast Dot -->
            <circle cx="20" cy="51" r="2" fill="#38bdf8"/>
            <!-- Bow Directional Arrow Indicator -->
            <polygon points="20,8 17,14 23,14" fill="#38bdf8"/>
          </svg>
        </div>
      `,
      iconSize: [34, 60],
      iconAnchor: [17, 30]
    });
  }

  // Airplane SVG for air freight corridors
  function createAircraftIcon(heading) {
    return L.divIcon({
      className: 'fastcargo-aircraft-marker-wrapper',
      html: `
        <div class="vessel-marker-container" style="transform: rotate(${heading}deg);">
          <div class="vessel-sonar-ping"></div>
          <svg class="aircraft-svg-icon" viewBox="0 0 60 60" width="40" height="40" xmlns="http://www.w3.org/2000/svg">
            <path d="M30 4 L33 22 L54 34 L54 39 L33 30 L33 46 L40 52 L40 56 L30 53 L20 56 L20 52 L27 46 L27 30 L6 39 L6 34 L27 22 Z" fill="#0284c7" stroke="#ffffff" stroke-width="1.5"/>
          </svg>
        </div>
      `,
      iconSize: [40, 40],
      iconAnchor: [20, 20]
    });
  }

  /**
   * Main class: FastCargoMaritimeCruise
   */
  class FastCargoMaritimeCruise {
    constructor(map, options = {}) {
      this.map = map;
      this.origin = options.origin || [-6.102, 106.885];
      this.destination = options.destination || [3.002, 101.398];
      this.originHub = options.originHub || 'IDJKT';
      this.destinationHub = options.destinationHub || 'MYPKG';
      this.vesselName = options.vesselName || 'MV FastCargo Horizon';
      this.voyageNumber = options.voyageNumber || 'FC-VOY-2026';
      this.trackingNumber = options.trackingNumber || 'FC-SEA-IDMY-001';
      this.modality = options.modality || 'SEA';
      this.isSailing = options.isSailing !== false; // Active voyage

      // Resolve passage waypoints
      const corridorKey = `${this.originHub}-${this.destinationHub}`;
      let rawPoints = MARITIME_CORRIDORS[corridorKey];

      if (!rawPoints || rawPoints.length < 2) {
        // Fallback smooth spline between origin and destination
        rawPoints = [this.origin, this.destination];
      }

      this.routeData = buildCumulativeSegments(rawPoints);

      // Layer groups for smooth updating
      this.passedPolyline = null;
      this.remainingPolyline = null;
      this.vesselMarker = null;

      // Animation loop state
      this.animationFrameId = null;
      this.startTime = null;
      // Duration in milliseconds for one full traversal (e.g. 90 seconds continuous smooth passage)
      this.cycleDurationMs = options.durationMs || 100000;
      this.currentT = 0.35; // Start at ~35% into the passage if active
      this.baseKnots = this.modality === 'AIR' ? 440 : 17.4;

      this.initLayers();
      if (this.isSailing) {
        this.start();
      }
    }

    initLayers() {
      // Draw remaining route corridor (dashed navy line)
      this.remainingPolyline = L.polyline(this.routeData.points, {
        color: '#0284c7',
        weight: 3,
        dashArray: '6, 8',
        opacity: 0.5,
        lineCap: 'round'
      }).addTo(this.map);

      // Draw passed route (solid vibrant cyan)
      this.passedPolyline = L.polyline([], {
        color: '#0ea5e9',
        weight: 4,
        opacity: 0.9,
        lineCap: 'round'
      }).addTo(this.map);

      // Origin Hub Marker (Port anchor)
      L.marker(this.origin, {
        icon: L.divIcon({
          className: 'fastcargo-port-pin',
          html: '<div class="port-hub-badge bg-primary text-white"><i class="fas fa-anchor"></i></div>',
          iconSize: [28, 28],
          iconAnchor: [14, 14]
        })
      }).addTo(this.map).bindPopup(`<strong>Origin Port:</strong> ${this.originHub}`);

      // Destination Hub Marker (Port destination)
      L.marker(this.destination, {
        icon: L.divIcon({
          className: 'fastcargo-port-pin',
          html: '<div class="port-hub-badge bg-success text-white"><i class="fas fa-flag-checkered"></i></div>',
          iconSize: [28, 28],
          iconAnchor: [14, 14]
        })
      }).addTo(this.map).bindPopup(`<strong>Destination Port:</strong> ${this.destinationHub}`);

      // Initial state render
      const initialState = getInterpolatedState(this.routeData, this.currentT);
      const icon = this.modality === 'AIR' ? createAircraftIcon(initialState.heading) : createVesselIcon(initialState.heading);

      this.vesselMarker = L.marker(initialState.position, {
        icon: icon,
        zIndexOffset: 1000
      }).addTo(this.map);

      this.updateHud(initialState);
    }

    start() {
      if (this.animationFrameId) return;

      let lastTimestamp = performance.now();

      const animate = (timestamp) => {
        const delta = timestamp - lastTimestamp;
        lastTimestamp = timestamp;

        // Progress smoothly based on cycle duration
        this.currentT += delta / this.cycleDurationMs;
        if (this.currentT > 1.0) {
          this.currentT = 0.05; // Loop smoothly back near origin for continuous real-time presentation
        }

        const state = getInterpolatedState(this.routeData, this.currentT);

        // Update vessel marker position & rotation
        if (this.vesselMarker) {
          this.vesselMarker.setLatLng(state.position);
          const iconEl = this.vesselMarker.getElement();
          if (iconEl) {
            const container = iconEl.querySelector('.vessel-marker-container');
            if (container) {
              container.style.transform = `rotate(${state.heading}deg)`;
            }
          }
        }

        // Update dynamic polylines
        if (this.passedPolyline) {
          this.passedPolyline.setLatLngs(state.passedPoints);
        }
        if (this.remainingPolyline) {
          this.remainingPolyline.setLatLngs(state.remainingPoints);
        }

        // Update live HUD metrics
        this.updateHud(state);

        this.animationFrameId = requestAnimationFrame(animate);
      };

      this.animationFrameId = requestAnimationFrame(animate);
    }

    stop() {
      if (this.animationFrameId) {
        cancelAnimationFrame(this.animationFrameId);
        this.animationFrameId = null;
      }
    }

    updateHud(state) {
      // Natural micro-fluctuation in maritime speed
      const microVariance = Math.sin(Date.now() / 3000) * 0.4;
      const currentKnots = (this.baseKnots + microVariance).toFixed(1);

      // Elements inside DOM if HUD exists
      const speedEl = document.getElementById('aisHudSpeed');
      const headingEl = document.getElementById('aisHudHeading');
      const coordsEl = document.getElementById('aisHudCoords');
      const progressEl = document.getElementById('aisHudProgress');
      const remainingEl = document.getElementById('aisHudRemaining');
      const progressBar = document.getElementById('aisHudProgressBar');

      if (speedEl) speedEl.textContent = `${currentKnots} kts`;
      if (headingEl) headingEl.textContent = getCompassLabel(state.heading);
      if (coordsEl) coordsEl.textContent = `${formatDms(state.position[0], true)}, ${formatDms(state.position[1], false)}`;
      if (progressEl) progressEl.textContent = `${Math.round(state.progressFraction * 100)}%`;
      if (remainingEl) remainingEl.textContent = `${state.distanceRemainingNm} NM remaining`;
      if (progressBar) progressBar.style.width = `${Math.round(state.progressFraction * 100)}%`;
    }
  }

  // Export to global scope
  global.FastCargoMaritimeCruise = FastCargoMaritimeCruise;

})(typeof window !== 'undefined' ? window : this);

