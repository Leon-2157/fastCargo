<div class="card shadow mb-4">
  <div class="card-header py-3 bg-white border-bottom d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary">
      <i class="fas fa-boxes me-1"></i> FastCargo Cross-Border Booking Wizard
    </h6>
    <span class="badge bg-primary px-3 py-2">Step {{ $currentStep }} of 4</span>
  </div>

  <div class="card-body p-4">
    <!-- Wizard Progress Bar -->
    <div class="mb-4">
      <div class="progress" style="height: 8px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar"
             style="width: {{ ($currentStep / 4) * 100 }}%"></div>
      </div>
      <div class="d-flex justify-content-between mt-2 text-xs font-weight-bold text-muted">
        <span class="{{ $currentStep >= 1 ? 'text-primary' : '' }}">1. Route &amp; Mode</span>
        <span class="{{ $currentStep >= 2 ? 'text-primary' : '' }}">2. Consignment Profile</span>
        <span class="{{ $currentStep >= 3 ? 'text-primary' : '' }}">3. Packing List</span>
        <span class="{{ $currentStep >= 4 ? 'text-primary' : '' }}">4. Quote &amp; Confirm</span>
      </div>
    </div>

    <div class="row">
      <!-- Main Wizard Form Column -->
      <div class="col-lg-8">
        <!-- STEP 1: ROUTE & TRANSPORT MODE -->
        @if($currentStep === 1)
          <div class="border rounded p-4 mb-4 bg-light">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-map-marker-alt text-primary me-2"></i>Select Origin &amp; Destination Hub</h5>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary">Origin Port / Inland Hub</label>
                <select wire:model.live="originPortId" class="form-select @error('originPortId') is-invalid @enderror">
                  @foreach($ports as $port)
                    <option value="{{ $port->id }}">
                      [{{ $port->id }}] {{ $port->name }} ({{ $port->city }}, {{ $port->country_code }})
                    </option>
                  @endforeach
                </select>
                @error('originPortId') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary">Destination Port / Hub</label>
                <select wire:model.live="destinationPortId" class="form-select @error('destinationPortId') is-invalid @enderror">
                  @foreach($ports as $port)
                    @if($port->id !== $originPortId)
                      <option value="{{ $port->id }}">
                        [{{ $port->id }}] {{ $port->name }} ({{ $port->city }}, {{ $port->country_code }})
                      </option>
                    @endif
                  @endforeach
                </select>
                @error('destinationPortId') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <label class="form-label fw-semibold text-secondary mb-2">Transport Corridor Mode</label>
            <div class="row g-2 mb-3">
              <div class="col-4">
                <input type="radio" class="btn-check" name="transportMode" id="modeSea" value="SEA" wire:model.live="transportMode">
                <label class="btn btn-outline-primary w-100 py-3" for="modeSea">
                  <i class="fas fa-ship fa-2x d-block mb-1"></i>
                  <strong>Ocean Freight</strong>
                  <div class="small opacity-75">Standard Sea Route</div>
                </label>
              </div>
              <div class="col-4">
                <input type="radio" class="btn-check" name="transportMode" id="modeAir" value="AIR" wire:model.live="transportMode">
                <label class="btn btn-outline-primary w-100 py-3" for="modeAir">
                  <i class="fas fa-plane fa-2x d-block mb-1"></i>
                  <strong>Air Express</strong>
                  <div class="small opacity-75">Expedited Priority</div>
                </label>
              </div>
              <div class="col-4">
                <input type="radio" class="btn-check" name="transportMode" id="modeLand" value="LAND" wire:model.live="transportMode">
                <label class="btn btn-outline-primary w-100 py-3" for="modeLand">
                  <i class="fas fa-truck-moving fa-2x d-block mb-1"></i>
                  <strong>Cross-Border Land</strong>
                  <div class="small opacity-75">Road Haulage</div>
                </label>
              </div>
            </div>

            <div class="alert alert-info d-flex align-items-center mb-0" role="alert">
              <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
              <div>
                <strong>Corridor Transit Estimate:</strong> Approx. {{ $transitDays }} days estimated transit.
                Base corridor rate: <span class="fw-bold">IDR {{ number_format($baseRate, 0, ',', '.') }}</span>.
              </div>
            </div>
          </div>
        @endif

        <!-- STEP 2: CONSIGNMENT PROFILE -->
        @if($currentStep === 2)
          <div class="border rounded p-4 mb-4 bg-light">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-clipboard-list text-primary me-2"></i>Consignment Classification</h5>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary">Booking &amp; Container Model</label>
                <select wire:model.live="bookingType" class="form-select">
                  <option value="RETAIL_LCL">Retail LCL (Less than Container Load - Pallets / Cartons)</option>
                  <option value="CORPORATE_FCL">Corporate FCL (Full Container Load - 20FT / 40FT / HC)</option>
                  <option value="CHARTER_TRUCK">Dedicated Charter Truck (Cross-Border Transit)</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary">Cargo Category</label>
                <select wire:model.live="cargoType" class="form-select">
                  <option value="GENERAL_CARGO">General Cargo (Dry &amp; Ambient)</option>
                  <option value="DANGEROUS_GOODS">Dangerous Goods (IMO Class / MSDS Required)</option>
                  <option value="REEFER_PERISHABLE">Perishable &amp; Reefer Cargo (Cold Chain -18°C)</option>
                </select>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary">Commodity HS Code</label>
                <select wire:model="hsCode" class="form-select">
                  @foreach($hsCodes as $code)
                    <option value="{{ $code->code }}">
                      {{ $code->code }} — {{ $code->description }}
                    </option>
                  @endforeach
                </select>
                <small class="text-muted">ASEAN Harmonized Tariff Nomenclature compliant</small>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary">Declared Cargo Value (IDR)</label>
                <div class="input-group">
                  <span class="input-group-text">IDR</span>
                  <input type="number" step="100000" wire:model.live="declaredValue" class="form-control @error('declaredValue') is-invalid @enderror">
                </div>
                <small class="text-muted">Used for cargo indemnity and marine insurance valuation</small>
                @error('declaredValue') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>
        @endif

        <!-- STEP 3: PACKING LIST ITEMS -->
        @if($currentStep === 3)
          <div class="border rounded p-4 mb-4 bg-light">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="fw-bold text-dark m-0"><i class="fas fa-boxes text-primary me-2"></i>Packing List &amp; Dimensions</h5>
              <button type="button" wire:click="addItem" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-plus me-1"></i> Add Another Item
              </button>
            </div>

            @foreach($items as $index => $item)
              <div class="card mb-3 border-0 shadow-sm">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-secondary">Item #{{ $index + 1 }}</span>
                    @if(count($items) > 1)
                      <button type="button" wire:click="removeItem({{ $index }})" class="btn btn-sm text-danger p-0">
                        <i class="fas fa-trash-alt me-1"></i> Remove
                      </button>
                    @endif
                  </div>

                  <div class="row g-2 mb-2">
                    <div class="col-md-6">
                      <label class="form-label small text-muted">Commodity Description</label>
                      <input type="text" wire:model="items.{{ $index }}.item_name"
                             class="form-control form-control-sm @error('items.'.$index.'.item_name') is-invalid @enderror"
                             placeholder="e.g. Server Racks, Textiles, Coffee Bags">
                    </div>

                    <div class="col-md-3">
                      <label class="form-label small text-muted">Package Unit</label>
                      <select wire:model="items.{{ $index }}.package_type" class="form-select form-select-sm">
                        <option value="CARTON">Carton Box</option>
                        <option value="PALLET">Wooden Pallet</option>
                        <option value="WOODEN_CRATE">Crate</option>
                        <option value="DRUM">Drum</option>
                        <option value="BUNDLE">Bundle</option>
                      </select>
                    </div>

                    <div class="col-md-3">
                      <label class="form-label small text-muted">Quantity</label>
                      <input type="number" min="1" wire:model.live="items.{{ $index }}.quantity" class="form-control form-control-sm">
                    </div>
                  </div>

                  <div class="row g-2 align-items-center">
                    <div class="col-md-3">
                      <label class="form-label small text-muted">Weight per Unit (KG)</label>
                      <input type="number" step="0.5" wire:model.live="items.{{ $index }}.weight_kg" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-2">
                      <label class="form-label small text-muted">Length (cm)</label>
                      <input type="number" wire:model.live="items.{{ $index }}.length_cm" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-2">
                      <label class="form-label small text-muted">Width (cm)</label>
                      <input type="number" wire:model.live="items.{{ $index }}.width_cm" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-2">
                      <label class="form-label small text-muted">Height (cm)</label>
                      <input type="number" wire:model.live="items.{{ $index }}.height_cm" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-3">
                      <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" wire:model.live="items.{{ $index }}.is_dangerous_good" id="dgCheck{{ $index }}">
                        <label class="form-check-label small text-danger fw-semibold" for="dgCheck{{ $index }}">
                          Hazardous / DG
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @endif

        <!-- STEP 4: REVIEW & CONFIRM -->
        @if($currentStep === 4)
          <div class="border rounded p-4 mb-4 bg-light">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-file-invoice text-primary me-2"></i>Consignment Quotation Review</h5>

            <div class="table-responsive mb-3">
              <table class="table table-bordered bg-white">
                <tbody>
                  <tr>
                    <th class="w-35 bg-light">Routing Corridor</th>
                    <td>
                      <span class="badge bg-secondary me-1">{{ $originPortId }}</span>
                      <i class="fas fa-arrow-right mx-1 text-muted"></i>
                      <span class="badge bg-primary">{{ $destinationPortId }}</span>
                      ({{ $transportMode }} Corridor)
                    </td>
                  </tr>
                  <tr>
                    <th class="bg-light">Service Model</th>
                    <td>{{ str_replace('_', ' ', $bookingType) }} — {{ str_replace('_', ' ', $cargoType) }}</td>
                  </tr>
                  <tr>
                    <th class="bg-light">Gross Metric Load</th>
                    <td>
                      <span class="fw-bold">{{ number_format($totalWeight, 2) }} KG</span> |
                      <span class="fw-bold">{{ number_format($totalVolume, 4) }} CBM</span>
                      ({{ count($items) }} packing item lines)
                    </td>
                  </tr>
                  <tr>
                    <th class="bg-light">Declared Cargo Value</th>
                    <td>IDR {{ number_format($declaredValue, 0, ',', '.') }}</td>
                  </tr>
                  <tr>
                    <th class="bg-light">Estimated Transit Time</th>
                    <td>{{ $transitDays }} Business Days (Port-to-Port)</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="alert alert-success d-flex align-items-center mb-0">
              <i class="fas fa-shield-alt fa-2x me-3 text-success"></i>
              <div>
                <strong>FastCargo Escrow &amp; Compliance Guarantee:</strong>
                Your consignment is backed by real-time GPS vessel telemetry, automated customs clearance pre-filing, and transparent escrow milestone settlements.
              </div>
            </div>
          </div>
        @endif

        <!-- Wizard Navigation Actions -->
        <div class="d-flex justify-content-between">
          @if($currentStep > 1)
            <button type="button" wire:click="prevStep" class="btn btn-secondary px-4">
              <i class="fas fa-arrow-left me-1"></i> Previous Step
            </button>
          @else
            <div></div>
          @endif

          @if($currentStep < 4)
            <button type="button" wire:click="nextStep" class="btn btn-primary px-4">
              Continue to Step {{ $currentStep + 1 }} <i class="fas fa-arrow-right ms-1"></i>
            </button>
          @else
            <button type="button" wire:click="submitBooking" class="btn btn-success btn-lg px-4 shadow">
              <i class="fas fa-check-circle me-1"></i> Confirm &amp; Dispatch Booking
            </button>
          @endif
        </div>
      </div>

      <!-- Real-time Cost Estimation Sidebar -->
      <div class="col-lg-4">
        <div class="card border-primary mb-4 shadow-sm sticky-top" style="top: 20px;">
          <div class="card-header bg-primary text-white py-3">
            <h6 class="m-0 fw-bold"><i class="fas fa-calculator me-1"></i> Live Cost Estimate</h6>
          </div>
          <div class="card-body">
            <ul class="list-group list-group-flush mb-3">
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span class="text-secondary">Base Freight Rate:</span>
                <span class="fw-semibold">IDR {{ number_format($estimatedFreight, 0, ',', '.') }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span class="text-secondary">Marine Insurance (0.2%):</span>
                <span class="fw-semibold">IDR {{ number_format($estimatedInsurance, 0, ',', '.') }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span class="text-secondary">Terminal &amp; Customs Port Dues:</span>
                <span class="fw-semibold">IDR {{ number_format($handlingFee, 0, ',', '.') }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0 pt-3 border-top">
                <strong class="text-dark">Total Estimated:</strong>
                <h5 class="text-primary fw-bold mb-0">IDR {{ number_format($grandTotal, 0, ',', '.') }}</h5>
              </li>
            </ul>

            <div class="bg-light p-2 rounded small text-muted text-center mb-3">
              <i class="fas fa-clock me-1"></i> Rates locked for 48 hours upon booking confirmation.
            </div>

            <div class="small">
              <div class="d-flex justify-content-between text-muted mb-1">
                <span>Calculated Weight:</span>
                <span class="fw-bold">{{ number_format($totalWeight, 2) }} kg</span>
              </div>
              <div class="d-flex justify-content-between text-muted mb-1">
                <span>Volumetric Metric:</span>
                <span class="fw-bold">{{ number_format($totalVolume, 4) }} cbm</span>
              </div>
              <div class="d-flex justify-content-between text-muted">
                <span>Corridor:</span>
                <span class="fw-bold">{{ $originPortId }} &rarr; {{ $destinationPortId }} ({{ $transportMode }})</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

