@extends('layouts.dashboard')

@section('title', 'Platform Settings & System Integrations')

@push('styles')
    <style>
        .qris-preview-frame {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            min-height: 204px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
        }

        .qris-preview-frame svg {
            width: 100%;
            max-width: 176px;
            height: auto;
            display: block;
        }

        .qris-dropzone {
            border: 1.5px dashed #adb5bd;
            border-radius: 0.5rem;
            background: #f8f9fa;
            transition: border-color 0.15s ease, background-color 0.15s ease;
            cursor: pointer;
        }

        .qris-dropzone:hover,
        .qris-dropzone:focus-visible,
        .qris-dropzone.is-dragover {
            border-color: #0d6efd;
            background: #eef4ff;
            outline: 2px solid rgba(13, 110, 253, 0.35);
            outline-offset: 2px;
        }

        .merchant-meta-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 0.45rem 0.65rem;
        }
    </style>
@endpush

@section('content')
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-dark mb-0 fw-bold">Platform Settings &amp; System Integrations</h3>
            <p class="text-muted small mb-0">Manage AI Intelligence Engines, WhatsApp Gateway pairing, SGpay Direct QRIS, and
                SMTP Email Delivery.</p>
        </div>
        <div>
            <span class="badge bg-light text-secondary border px-3 py-2 small">
                <i class="fas fa-network-wired me-1"></i> Operator: <strong>{{ config('app.domains.operator') }}</strong>
            </span>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Validation failed:</strong> {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Row 1: AI Intelligence (Left) & WhatsApp Gateway (Right) -->
    <div class="row">
        <!-- AI Intelligence Engine Configuration -->
        <div class="col-lg-6 mb-4 align-self-start">
            <div class="card shadow border-start-primary">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-robot me-1"></i> AI Intelligence</h6>
                    @php
                        $currentAi = $aiConfig['active_provider'] ?? 'custom';
                        $isGemini = in_array($currentAi, ['gemini'], true);
                    @endphp
                    <span id="activeAiBadge" class="badge {{ $isGemini ? 'bg-info text-dark' : 'bg-primary' }} px-3 py-2">
                        Active: {{ $aiConfig['active_label'] ?? strtoupper($currentAi) }}
                    </span>
                </div>
                <div class="card-body">
                    <form id="aiSwitchForm" method="POST" action="{{ route('admin.settings.ai.switch', [], false) }}"
                        class="mb-4">
                        @csrf
                        <label class="form-label small fw-bold text-muted" for="ai_provider_select">Select Active AI
                            Engine</label>
                        <div class="input-group">
                            <select id="ai_provider_select" name="ai_provider" class="form-select">
                                <option value="gemini" {{ $isGemini ? 'selected' : '' }}>Google Gemini API</option>
                                <option value="custom" {{ !$isGemini ? 'selected' : '' }}>OpenAI Compatible</option>
                            </select>
                            <button id="btnSwitchEngine" type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-1"></i> Switch Engine
                            </button>
                        </div>
                    </form>

                    <form id="formAiSettings" method="POST" action="{{ route('admin.settings.update', [], false) }}">
                        @csrf
                        @method('PUT')

                        <!-- Google Gemini API Section -->
                        <div id="aiSectionGemini" class="{{ $isGemini ? '' : 'd-none' }}">
                            <h6 class="small fw-bold text-uppercase text-info mb-2"><i class="fab fa-google me-1"></i>
                                Google Gemini API</h6>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1" for="gemini_api_key">Gemini API
                                    Key</label>
                                <input id="gemini_api_key" type="password" name="gemini_api_key"
                                    value="{{ old('gemini_api_key', $aiConfig['gemini_api_key']) }}"
                                    class="form-control ai-dirty-tracker" autocomplete="new-password"
                                    placeholder="Enter Google Gemini API key">
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted" for="dropdownGeminiModelBtn">Gemini Model
                                    Identifier</label>
                                @php
                                    $selectedGeminiModel = old('gemini_model', $aiConfig['gemini_model']);
                                    $geminiModels = $aiModelsRegistry['google_gemini_api'] ?? [];
                                    $hasSelectedGemini = collect($geminiModels)->contains(
                                        'model',
                                        $selectedGeminiModel,
                                    );
                                @endphp
                                <input type="hidden" id="gemini_model" name="gemini_model"
                                    value="{{ $selectedGeminiModel }}" class="ai-dirty-tracker">
                                <div class="input-group">
                                    <button
                                        class="btn btn-outline-secondary dropdown-toggle flex-grow-1 text-start d-flex align-items-center justify-content-between"
                                        type="button" id="dropdownGeminiModelBtn" data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <span id="gemini_model_label"
                                            class="text-truncate">{{ $selectedGeminiModel ?: 'Select Gemini Model' }}</span>
                                    </button>
                                    <ul class="dropdown-menu w-100 shadow-sm" id="dropdownGeminiModelList"
                                        style="max-height: 260px; overflow-y: auto;"
                                        aria-labelledby="dropdownGeminiModelBtn">
                                        @if (!$hasSelectedGemini && !empty($selectedGeminiModel))
                                            <li class="d-flex align-items-center justify-content-between px-2 py-1 model-item"
                                                data-engine="google_gemini_api" data-model="{{ $selectedGeminiModel }}">
                                                <a class="dropdown-item py-1 px-2 text-truncate rounded flex-grow-1 me-1 active fw-semibold"
                                                    style="min-width: 0;" href="#" data-action="select-model"
                                                    data-engine="google_gemini_api" data-model="{{ $selectedGeminiModel }}"
                                                    data-completion="4096" data-output="2048" data-max="8192">
                                                    {{ $selectedGeminiModel }} (Active)
                                                </a>
                                                <button type="button"
                                                    class="btn btn-sm btn-link text-danger text-decoration-none p-1 ms-1 btn-delete-ai-model"
                                                    data-action="delete-model" data-engine="google_gemini_api"
                                                    data-model="{{ $selectedGeminiModel }}"
                                                    title="Remove model {{ $selectedGeminiModel }}"
                                                    aria-label="Remove model {{ $selectedGeminiModel }}">
                                                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                </button>
                                            </li>
                                        @endif
                                        @foreach ($geminiModels as $m)
                                            <li class="d-flex align-items-center justify-content-between px-2 py-1 model-item"
                                                data-engine="google_gemini_api" data-model="{{ $m['model'] }}">
                                                <a class="dropdown-item py-1 px-2 text-truncate rounded flex-grow-1 me-1 {{ $selectedGeminiModel === $m['model'] ? 'active fw-semibold' : '' }}"
                                                    style="min-width: 0;" href="#" data-action="select-model"
                                                    data-engine="google_gemini_api" data-model="{{ $m['model'] }}"
                                                    data-completion="{{ $m['completion_tokens'] ?? 4096 }}"
                                                    data-output="{{ $m['output_tokens'] ?? 2048 }}"
                                                    data-max="{{ $m['max_tokens'] ?? 8192 }}">
                                                    {{ $m['model'] }}
                                                </a>
                                                <button type="button"
                                                    class="btn btn-sm btn-link text-danger text-decoration-none p-1 ms-1 btn-delete-ai-model"
                                                    data-action="delete-model" data-engine="google_gemini_api"
                                                    data-model="{{ $m['model'] }}"
                                                    title="Remove model {{ $m['model'] }}"
                                                    aria-label="Remove model {{ $m['model'] }}">
                                                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn btn-outline-primary" id="btnAddGeminiModel">
                                        <i class="fas fa-plus me-1"></i> Add Model
                                    </button>
                                </div>
                                <button type="button" id="btnDeleteGeminiModel" class="d-none"
                                    aria-hidden="true"></button>
                            </div>
                        </div>

                        <!-- OpenAI Compatible Gateway Section -->
                        <div id="aiSectionCustom" class="{{ $isGemini ? 'd-none' : '' }}">
                            <h6 class="small fw-bold text-uppercase text-primary mb-2"><i class="fas fa-server me-1"></i>
                                OpenAI Compatible</h6>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted" for="custom_ai_provider_name">Provider
                                    Name</label>
                                <input id="custom_ai_provider_name" type="text" name="custom_ai_provider_name"
                                    value="{{ old('custom_ai_provider_name', $aiConfig['custom_ai_provider_name']) }}"
                                    class="form-control ai-dirty-tracker"
                                    placeholder="e.g. OpenAI Official, Local Ollama, Custom Gateway">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted" for="custom_ai_base_url">Base
                                    URL</label>
                                <input id="custom_ai_base_url" type="url" name="custom_ai_base_url"
                                    value="{{ old('custom_ai_base_url', $aiConfig['custom_ai_base_url']) }}"
                                    class="form-control ai-dirty-tracker"
                                    placeholder="http://localhost:20128/v1 or https://api.openai.com/v1">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1" for="custom_ai_api_key">API
                                    Key</label>
                                <input id="custom_ai_api_key" type="password" name="custom_ai_api_key"
                                    value="{{ old('custom_ai_api_key', $aiConfig['custom_ai_api_key']) }}"
                                    class="form-control ai-dirty-tracker" autocomplete="new-password"
                                    placeholder="Enter API Key">
                            </div>

                            <hr class="my-3">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted" for="dropdownCustomModelBtn">Model
                                    Name</label>
                                @php
                                    $selectedCustomModel = old('custom_ai_model', $aiConfig['custom_ai_model']);
                                    $customModels = $aiModelsRegistry['openai_compatible'] ?? [];
                                    $hasSelectedCustom = collect($customModels)->contains(
                                        'model',
                                        $selectedCustomModel,
                                    );
                                @endphp
                                <input type="hidden" id="custom_ai_model" name="custom_ai_model"
                                    value="{{ $selectedCustomModel }}" class="ai-dirty-tracker">
                                <div class="input-group">
                                    <button
                                        class="btn btn-outline-secondary dropdown-toggle flex-grow-1 text-start d-flex align-items-center justify-content-between"
                                        type="button" id="dropdownCustomModelBtn" data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <span id="custom_ai_model_label"
                                            class="text-truncate">{{ $selectedCustomModel ?: 'Select AI Model' }}</span>
                                    </button>
                                    <ul class="dropdown-menu w-100 shadow-sm" id="dropdownCustomModelList"
                                        style="max-height: 260px; overflow-y: auto;"
                                        aria-labelledby="dropdownCustomModelBtn">
                                        @if (!$hasSelectedCustom && !empty($selectedCustomModel))
                                            <li class="d-flex align-items-center justify-content-between px-2 py-1 model-item"
                                                data-engine="openai_compatible" data-model="{{ $selectedCustomModel }}">
                                                <a class="dropdown-item py-1 px-2 text-truncate rounded flex-grow-1 me-1 active fw-semibold"
                                                    style="min-width: 0;" href="#" data-action="select-model"
                                                    data-engine="openai_compatible"
                                                    data-model="{{ $selectedCustomModel }}"
                                                    data-completion="{{ $aiConfig['custom_ai_max_completion_tokens'] ?? 4096 }}"
                                                    data-output="{{ $aiConfig['custom_ai_max_output_tokens'] ?? 2048 }}"
                                                    data-max="{{ $aiConfig['custom_ai_max_tokens'] ?? 8192 }}">
                                                    {{ $selectedCustomModel }} (Active)
                                                </a>
                                                <button type="button"
                                                    class="btn btn-sm btn-link text-danger text-decoration-none p-1 ms-1 btn-delete-ai-model"
                                                    data-action="delete-model" data-engine="openai_compatible"
                                                    data-model="{{ $selectedCustomModel }}"
                                                    title="Remove model {{ $selectedCustomModel }}"
                                                    aria-label="Remove model {{ $selectedCustomModel }}">
                                                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                </button>
                                            </li>
                                        @endif
                                        @foreach ($customModels as $m)
                                            <li class="d-flex align-items-center justify-content-between px-2 py-1 model-item"
                                                data-engine="openai_compatible" data-model="{{ $m['model'] }}">
                                                <a class="dropdown-item py-1 px-2 text-truncate rounded flex-grow-1 me-1 {{ $selectedCustomModel === $m['model'] ? 'active fw-semibold' : '' }}"
                                                    style="min-width: 0;" href="#" data-action="select-model"
                                                    data-engine="openai_compatible" data-model="{{ $m['model'] }}"
                                                    data-completion="{{ $m['completion_tokens'] ?? 4096 }}"
                                                    data-output="{{ $m['output_tokens'] ?? 2048 }}"
                                                    data-max="{{ $m['max_tokens'] ?? 8192 }}">
                                                    {{ $m['model'] }}
                                                </a>
                                                <button type="button"
                                                    class="btn btn-sm btn-link text-danger text-decoration-none p-1 ms-1 btn-delete-ai-model"
                                                    data-action="delete-model" data-engine="openai_compatible"
                                                    data-model="{{ $m['model'] }}"
                                                    title="Remove model {{ $m['model'] }}"
                                                    aria-label="Remove model {{ $m['model'] }}">
                                                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn btn-outline-primary" id="btnAddCustomModel">
                                        <i class="fas fa-plus me-1"></i> Add Model
                                    </button>
                                </div>
                                <button type="button" id="btnDeleteCustomModel" class="d-none"
                                    aria-hidden="true"></button>
                            </div>

                            <div class="row g-2 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted" style="font-size: 0.72rem;"
                                        for="custom_ai_max_completion_tokens">Max Completion Token</label>
                                    <input id="custom_ai_max_completion_tokens" type="number"
                                        name="custom_ai_max_completion_tokens"
                                        value="{{ old('custom_ai_max_completion_tokens', $aiConfig['custom_ai_max_completion_tokens']) }}"
                                        class="form-control ai-dirty-tracker" placeholder="4096">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted" style="font-size: 0.72rem;"
                                        for="custom_ai_max_output_tokens">Max Output Token</label>
                                    <input id="custom_ai_max_output_tokens" type="number"
                                        name="custom_ai_max_output_tokens"
                                        value="{{ old('custom_ai_max_output_tokens', $aiConfig['custom_ai_max_output_tokens']) }}"
                                        class="form-control ai-dirty-tracker" placeholder="2048">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted" style="font-size: 0.72rem;"
                                        for="custom_ai_max_tokens">Max Token</label>
                                    <input id="custom_ai_max_tokens" type="number" name="custom_ai_max_tokens"
                                        value="{{ old('custom_ai_max_tokens', $aiConfig['custom_ai_max_tokens']) }}"
                                        class="form-control ai-dirty-tracker" placeholder="8192">
                                </div>
                            </div>
                        </div>

                        <!-- Test Connection Feedback Message -->
                        <div id="aiTestFeedback" class="d-none alert py-2 px-3 small mb-3"></div>

                        <!-- Footer Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <button id="btnTestAiConnection" type="button" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-satellite-dish me-1 text-primary"></i> Test Connection
                            </button>
                            <button id="btnSaveAiSettings" type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- WhatsApp Gateway Station Card -->
        <div class="col-lg-6 mb-4 align-self-start">
            <div class="card shadow border-start-success">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-success"><i class="fab fa-whatsapp me-1"></i> WhatsApp Gateway
                    </h6>
                    <div id="wag-status-badge">
                        @if (!($waConfig['gatewayStatus']['reachable'] ?? false))
                            <span class="badge bg-danger px-2 py-1 small"><i class="fas fa-plug me-1"></i> Offline</span>
                        @elseif($waConfig['isConnected'])
                            <span class="badge bg-success px-2 py-1 small"><i class="fas fa-check-circle me-1"></i>
                                Connected</span>
                        @else
                            <span class="badge bg-warning text-dark px-2 py-1 small"><i class="fas fa-qrcode me-1"></i>
                                Pairing Needed</span>
                        @endif
                    </div>
                </div>

                <div class="card-body p-3">
                    <div class="row g-2 mb-3">
                        <!-- Left Box: QR Code / Device Status -->
                        <div class="col-sm-6">
                            <div id="wag-left-box"
                                class="border rounded p-2 bg-light d-flex flex-column align-items-center justify-content-center text-center h-100 shadow-sm"
                                style="min-height: 210px;">
                                @if ($waConfig['qrSvg'])
                                    <div class="p-1 bg-white rounded border mb-1 d-flex align-items-center justify-content-center"
                                        style="width: 122px; height: 122px;">
                                        {!! $waConfig['qrSvg'] !!}
                                    </div>
                                    <div class="fw-bold text-dark" style="font-size: 11px;">
                                        <i class="fas fa-qrcode me-1 text-primary"></i> Scan WhatsApp QR
                                    </div>
                                    <div class="text-muted" style="font-size: 9.5px;">Open WhatsApp &gt; Linked Devices
                                    </div>
                                @elseif($waConfig['isConnected'])
                                    <div class="text-success mb-2">
                                        <i class="fab fa-whatsapp fa-3x"></i>
                                    </div>
                                    <span class="badge bg-success px-2 py-1 mb-1 small">
                                        <i class="fas fa-check-circle me-1"></i> Session Connected
                                    </span>
                                    <div class="small fw-bold text-dark mb-0" style="font-size: 11px;">
                                        {{ !empty($waConfig['gatewayStatus']['user']['name']) ? $waConfig['gatewayStatus']['user']['name'] : (!empty($waConfig['gatewayStatus']['user']['phone']) ? 'WhatsApp (+' . $waConfig['gatewayStatus']['user']['phone'] . ')' : 'Device Connected') }}
                                    </div>
                                    <code class="text-muted"
                                        style="font-size: 10px;">+{{ $waConfig['gatewayStatus']['user']['phone'] ?? '' }}</code>
                                @elseif(!($waConfig['gatewayStatus']['reachable'] ?? false))
                                    <div class="text-danger mb-2">
                                        <i class="fas fa-plug fa-3x"></i>
                                    </div>
                                    <span class="badge bg-danger px-2 py-1 mb-1 small">waG Offline</span>
                                    <div class="text-muted" style="font-size: 10px;">Service unreachable</div>
                                @else
                                    <div class="spinner-border text-warning mb-2" role="status"
                                        style="width: 2rem; height: 2rem;"></div>
                                    <div class="fw-bold text-dark" style="font-size: 11px;">Waiting for QR Code...</div>
                                @endif
                            </div>
                        </div>

                        <!-- Right Box: Service Details & AI Integration -->
                        <div class="col-sm-6 d-flex flex-column justify-content-between">
                            <div id="wag-right-box"
                                class="border rounded p-3 bg-white d-flex flex-column justify-content-between shadow-sm mb-2 flex-grow-1"
                                style="min-height: 140px;">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 10px;">
                                            <i class="fas fa-info-circle me-1 text-info"></i> Session Details
                                        </span>
                                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 fw-semibold"
                                            style="font-size: 9.5px; height: 21px; line-height: 20px;"
                                            onclick="document.getElementById('formWaResetDirect').submit();"
                                            title="Disconnect and reset WhatsApp session">
                                            <i class="fas fa-redo me-1"></i> Reset Session
                                        </button>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted" style="font-size: 10px;">Service Status:</div>
                                        <div id="wag-detail-status"
                                            class="fw-bold small {{ $waConfig['isConnected'] ? 'text-success' : ($waConfig['gatewayStatus']['reachable'] ?? false ? 'text-warning' : 'text-danger') }}">
                                            <i
                                                class="fas {{ $waConfig['isConnected'] ? 'fa-check-circle' : ($waConfig['gatewayStatus']['reachable'] ?? false ? 'fa-hourglass-half' : 'fa-times-circle') }} me-1"></i>
                                            {{ $waConfig['isConnected'] ? 'Online' : ($waConfig['gatewayStatus']['reachable'] ?? false ? 'Awaiting Pairing' : 'Offline / Disconnected') }}
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-muted" style="font-size: 10px;">Active Number:</div>
                                        <div id="wag-detail-phone" class="fw-bold small text-dark">
                                            @if (!empty($waConfig['gatewayStatus']['user']['phone']))
                                                <code>+{{ $waConfig['gatewayStatus']['user']['phone'] }}</code>
                                            @else
                                                <span class="text-muted fst-italic">Not Connected</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-muted" style="font-size: 10px;">Paired Timestamp:</div>
                                        <div id="wag-detail-paired-at" class="small text-secondary fw-semibold">
                                            <i class="far fa-clock me-1"></i>
                                            {{ $waConfig['pairedAt'] ?? 'Awaiting scan' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-1 mt-1 border-top d-flex justify-content-between align-items-center">
                                    <span class="text-muted" style="font-size: 9px;">Gateway:</span>
                                    <code style="font-size: 9.5px;">{{ $waConfig['gatewayBaseUrl'] }}</code>
                                </div>
                            </div>

                            <!-- Compact AI Integration Box -->
                            <div class="border rounded p-2 bg-white shadow-sm">
                                <form id="formWaAiSettings" method="POST"
                                    action="{{ route('admin.settings.update', [], false) }}" class="m-0">
                                    @csrf
                                    @method('PUT')
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0 text-muted fw-bold" style="font-size: 10.5px;"
                                            for="wa_ai_provider">
                                            <i class="fas fa-robot me-1 text-primary"></i> AI Integration
                                        </label>
                                        <button type="submit" class="btn btn-primary btn-sm py-0 px-2 fw-semibold"
                                            style="font-size: 9.5px; height: 21px; line-height: 20px;">
                                            <i class="fas fa-save me-1"></i> Save
                                        </button>
                                    </div>
                                    <select id="wa_ai_provider" name="wa_ai_provider" class="form-select form-select-sm"
                                        style="font-size: 11px;">
                                        @php
                                            $currentWaAi = old('wa_ai_provider', $waAiProvider ?? 'default');
                                            $knownWaValues = ['disabled', 'default', 'gemini', 'custom'];
                                            foreach ($aiModelsRegistry['google_gemini_api'] ?? [] as $gm) {
                                                $knownWaValues[] = 'google_gemini_api::' . $gm['model'];
                                            }
                                            foreach ($aiModelsRegistry['openai_compatible'] ?? [] as $cm) {
                                                $knownWaValues[] = 'openai_compatible::' . $cm['model'];
                                            }
                                        @endphp
                                        <option value="disabled" {{ $currentWaAi === 'disabled' ? 'selected' : '' }}>
                                            Disabled</option>
                                        <option value="default" {{ $currentWaAi === 'default' ? 'selected' : '' }}>Auto
                                            (Active: {{ $aiConfig['active_label'] }})</option>
                                        @foreach ($aiModelsRegistry['google_gemini_api'] ?? [] as $m)
                                            <option value="google_gemini_api::{{ $m['model'] }}"
                                                {{ $currentWaAi === 'google_gemini_api::' . $m['model'] ? 'selected' : '' }}>
                                                [Google Gemini API] {{ $m['model'] }}
                                            </option>
                                        @endforeach
                                        @foreach ($aiModelsRegistry['openai_compatible'] ?? [] as $m)
                                            <option value="openai_compatible::{{ $m['model'] }}"
                                                {{ $currentWaAi === 'openai_compatible::' . $m['model'] ? 'selected' : '' }}>
                                                [OpenAI Compatible] {{ $m['model'] }}
                                            </option>
                                        @endforeach
                                        @if (in_array($currentWaAi, ['gemini', 'custom'], true))
                                            <option value="{{ $currentWaAi }}" selected>
                                                [Legacy]
                                                {{ $currentWaAi === 'gemini' ? 'Gemini 2.0 Flash' : $aiConfig['custom_ai_provider_name'] ?? 'Custom AI' }}
                                            </option>
                                        @endif
                                        @if (!in_array($currentWaAi, $knownWaValues, true) && !empty($currentWaAi))
                                            <option value="{{ $currentWaAi }}" selected>[Unlisted] {{ $currentWaAi }}
                                            </option>
                                        @endif
                                    </select>
                                </form>
                            </div>
                        </div>
                    </div>

                    <form id="formWaResetDirect" method="POST" action="{{ route('admin.wa.reset', [], false) }}"
                        class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Multi Channel Payment (Left) & Email Delivery (Right) -->
    <div class="row">
        <!-- PayG Multi-Channel Payment Station -->
        <div class="col-lg-6 mb-4 align-self-start">
            @php
                $oldStaticQr = old('payg_sgpay_static_qr');
                $currentStaticQrValue =
                    $oldStaticQr !== null ? (string) $oldStaticQr : (string) ($paygConfig['sgpay_static_qr'] ?? '');
                if ($oldStaticQr !== null) {
                    $paygQrService = app(\App\Modules\Shared\Services\PayGPaymentGatewayService::class);
                    $merchantMeta =
                        $currentStaticQrValue !== '' ? $paygQrService->parseEmvcoMetadata($currentStaticQrValue) : null;
                    $staticQrSvg =
                        $merchantMeta['valid'] ?? false ? $paygQrService->renderQrSvg($currentStaticQrValue) : null;
                } else {
                    $merchantMeta = $paygConfig['merchant_preview'] ?? null;
                    $staticQrSvg = $paygConfig['static_qr_svg'] ?? null;
                }
                $hasValidStaticQr = (bool) ($merchantMeta['valid'] ?? false);
                $crcValid = (bool) ($merchantMeta['crc_valid'] ?? false);
            @endphp
            <div class="card shadow border-start-primary">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-satellite-dish me-1"></i> Multi Channel Payment
                        </h6>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        @if ($paygConfig['status']['reachable'] ?? false)
                            <span class="badge bg-success">
                                <i class="fas fa-server me-1"></i> PayG (8085)
                            </span>
                        @else
                            <span class="badge bg-info text-dark">
                                <i class="fas fa-flask me-1"></i> Standalone Mode
                            </span>
                        @endif
                        <span id="badge-payg-mode" class="badge bg-warning text-dark text-uppercase fw-bold">
                            <i class="fas fa-bolt me-1"></i> SGpay Direct
                        </span>
                    </div>
                </div>
                <div class="card-body p-3 p-lg-4">
                    <form method="POST" action="{{ route('admin.settings.update', [], false) }}" id="formDanaSettings">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="payg_active_mode" id="payg_active_mode" value="sgpay">

                        <!-- Split 2-Column QR Preview + Image Upload & Merchant Identity -->
                        <div class="border rounded p-3 mb-3 bg-white shadow-sm">
                            <div class="row g-3 align-items-start">
                                <!-- Left Column: Persistent QRIS SVG Preview + Zero-Cost Local Image Decoder -->
                                <div class="col-md-5">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-bold text-dark">Merchant Static QR</span>
                                        <span id="badge-static-qr-state"
                                            class="badge {{ $hasValidStaticQr ? 'bg-success' : 'bg-secondary' }}"
                                            style="font-size: 10px;">
                                            {{ $hasValidStaticQr ? 'Active' : 'Not Set' }}
                                        </span>
                                    </div>

                                    <div id="qris-preview-frame" class="qris-preview-frame mb-2">
                                        @if (!empty($staticQrSvg))
                                            {!! $staticQrSvg !!}
                                        @else
                                            <div class="text-center text-muted py-3 px-2" id="qris-preview-empty">
                                                <i class="fas fa-qrcode fa-2x mb-2 opacity-50"></i>
                                                <div class="small fw-semibold">No Static QRIS Configured</div>
                                                <div class="text-muted" style="font-size: 11px;">Upload your GoBiz / DANA
                                                    / Bank QR poster or paste an EMVCo QRIS string.</div>
                                            </div>
                                        @endif
                                    </div>

                                    <div id="qris-dropzone" class="qris-dropzone p-2 text-center" tabindex="0"
                                        role="button" aria-label="Upload QRIS image (PNG, JPG, WEBP)">
                                        <input type="file" id="qris-image-input"
                                            accept="image/png,image/jpeg,image/webp" class="d-none">
                                        <div class="small fw-semibold text-primary mb-1">
                                            <i class="fas fa-upload me-1"></i> Upload QR
                                        </div>

                                    </div>

                                    @if ($currentStaticQrValue !== '' && !$hasValidStaticQr)
                                        <div id="qris-decode-status"
                                            class="alert alert-warning border py-2 px-2 mt-2 mb-0" role="status"
                                            style="font-size: 11px;">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            {{ $merchantMeta['error'] ?? 'EMVCo QRIS payload is invalid.' }}
                                        </div>
                                    @else
                                        <div id="qris-decode-status"
                                            class="alert alert-light border py-2 px-2 mt-2 mb-0 d-none" role="status"
                                            style="font-size: 11px;"></div>
                                    @endif
                                </div>

                                <!-- Right Column: Merchant Identity Preview Badges + EMVCo String Input -->
                                <div class="col-md-7">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-bold text-dark">Merchant Identity Preview</span>
                                        <span id="preview-crc-badge"
                                            class="badge {{ $currentStaticQrValue === '' ? 'bg-secondary' : ($crcValid ? 'bg-success' : 'bg-danger') }}"
                                            style="font-size: 10px;">
                                            @if ($currentStaticQrValue === '')
                                                CRC-16 Unconfigured
                                            @elseif ($crcValid)
                                                <i class="fas fa-check-circle me-1"></i> CRC-16 Valid
                                            @else
                                                <i class="fas fa-exclamation-triangle me-1"></i> CRC-16 Invalid
                                            @endif
                                        </span>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <div
                                                class="merchant-meta-item d-flex justify-content-between align-items-center gap-2">
                                                <span class="text-muted small">Merchant Name</span>
                                                <span id="preview-merchant-name" class="fw-bold text-dark small text-end">
                                                    {{ $merchantMeta['merchant_name'] ?? '—' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="merchant-meta-item">
                                                <div class="text-muted" style="font-size: 10.5px;">City</div>
                                                <div id="preview-merchant-city"
                                                    class="fw-semibold text-dark small text-truncate">
                                                    {{ $merchantMeta['merchant_city'] ?? '—' }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="merchant-meta-item">
                                                <div class="text-muted" style="font-size: 10.5px;">Acquirer / NMID</div>
                                                <div id="preview-acquirer-nmid"
                                                    class="fw-semibold text-dark small text-truncate"
                                                    title="{{ ($merchantMeta['acquirer_label'] ?? '') . ' ' . ($merchantMeta['nmid'] ?? '') }}">
                                                    @if (!empty($merchantMeta['acquirer_label']) || !empty($merchantMeta['nmid']))
                                                        {{ $merchantMeta['acquirer_label'] ?? 'QRIS' }}@if (!empty($merchantMeta['nmid']))
                                                            · <span
                                                                class="font-monospace">{{ $merchantMeta['nmid'] }}</span>
                                                        @endif
                                                    @else
                                                        —
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="form-label mb-1 text-dark small fw-bold" for="payg_sgpay_static_qr">
                                            <i class="fas fa-qrcode text-warning me-1"></i> Static QRIS String
                                        </label>
                                        <textarea id="payg_sgpay_static_qr" name="payg_sgpay_static_qr"
                                            class="form-control form-control-sm font-monospace @error('payg_sgpay_static_qr') is-invalid @enderror"
                                            rows="5" placeholder="0002010102112659...6304XXXX">{{ $currentStaticQrValue }}</textarea>
                                        @error('payg_sgpay_static_qr')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PayG HMAC Secret -->
                        <div class="border rounded p-3 mb-3 bg-light shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0 text-dark small fw-bold" for="payg_secret">PayG HMAC
                                    Secret</label>
                                <span
                                    class="badge {{ $paygConfig['is_secret_configured'] ? 'bg-success' : 'bg-secondary' }}"
                                    style="font-size: 10px;">
                                    {{ $paygConfig['is_secret_configured'] ? 'Configured' : 'Using Default' }}
                                </span>
                            </div>
                            <div class="input-group input-group-sm">
                                <input type="password" id="payg_secret" name="payg_secret"
                                    class="form-control form-control-sm font-monospace"
                                    value="{{ old('payg_secret', $paygConfig['secret']) }}" autocomplete="new-password"
                                    placeholder="Enter custom webhook secret">
                                <button class="btn btn-outline-secondary" type="button" id="btn-toggle-payg-secret"
                                    title="Toggle visibility" aria-label="Toggle PayG HMAC Secret visibility">
                                    <i class="fas fa-eye" id="icon-toggle-payg-secret"></i>
                                </button>
                                <button class="btn btn-outline-primary" type="button" id="btn-copy-payg-secret"
                                    title="Copy PayG Secret" aria-label="Copy PayG HMAC Secret">
                                    <i class="fas fa-copy me-1"></i> Copy Secret
                                </button>
                            </div>
                        </div>

                        <!-- Internal Notification Webhook Callback URL + MacroDroid Guide -->
                        <div class="border rounded p-3 mb-3 bg-light shadow-sm">
                            <div
                                class="mb-2 pb-1 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <label class="small fw-bold text-dark mb-0" for="payg_webhook_url">
                                    <i class="fas fa-satellite-dish me-1 text-primary"></i> Internal Notification Webhook
                                    Endpoint
                                </label>
                                <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 fw-semibold"
                                    id="btn-open-macrodroid-guide" data-bs-toggle="modal"
                                    data-bs-target="#modalMacroDroidGuide">
                                    <i class="fab fa-android me-1"></i> Panduan MacroDroid
                                </button>
                            </div>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted"><i class="fas fa-link"></i></span>
                                <input type="text" id="payg_webhook_url"
                                    class="form-control font-monospace text-dark bg-white"
                                    value="{{ $paygConfig['webhook_url'] }}" readonly style="font-size: 11px;"
                                    aria-label="Internal Notification Webhook Endpoint URL">
                                <button class="btn btn-primary" type="button" id="btn-copy-payg-webhook">
                                    <i class="fas fa-copy me-1"></i> Copy URL
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-start align-items-center pt-1">
                            <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold shadow-sm">
                                <i class="fas fa-save me-1"></i> Save PayG Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Email Delivery Engine -->
        <div class="col-lg-6 mb-4 align-self-start">
            <div class="card shadow border-start-success">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-envelope me-1"></i> Email Delivery
                        </h6>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span id="badge-mail-driver" class="badge bg-secondary">Driver:
                            {{ strtoupper($mailConfig['mail_mailer']) }}</span>
                        @if ($mailConfig['mail_mailer'] === 'log')
                            <span id="badge-mail-status" class="badge bg-info text-dark">
                                <i class="fas fa-file-alt me-1"></i> Local Log Active
                            </span>
                        @else
                            <span id="badge-mail-status"
                                class="badge {{ $mailConfig['is_password_configured'] ? 'bg-success' : 'bg-danger' }}">
                                <i
                                    class="fas {{ $mailConfig['is_password_configured'] ? 'fa-check-circle' : 'fa-exclamation-triangle' }} me-1"></i>
                                {{ $mailConfig['is_password_configured'] ? 'API Key Configured' : 'API Key Not Set' }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-body p-3 p-lg-4">
                    <form method="POST" action="{{ route('admin.settings.update', [], false) }}">
                        @csrf
                        @method('PUT')

                        <!-- Mail Driver & AI Integration Selection Row -->
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted mb-1" for="mail_mailer">
                                    <i class="fas fa-paper-plane me-1 text-primary"></i> Mail Delivery
                                </label>
                                <select id="mail_mailer" name="mail_mailer" class="form-select form-select-sm">
                                    <option value="smtp"
                                        {{ old('mail_mailer', $mailConfig['mail_mailer']) === 'smtp' ? 'selected' : '' }}>
                                        SMTP</option>
                                    <option value="log"
                                        {{ old('mail_mailer', $mailConfig['mail_mailer']) === 'log' ? 'selected' : '' }}>
                                        Local Log</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted mb-1" for="mail_ai_provider">
                                    <i class="fas fa-robot me-1 text-primary"></i> AI Integration
                                </label>
                                <select id="mail_ai_provider" name="mail_ai_provider" class="form-select form-select-sm">
                                    @php
                                        $currentMailAi = old('mail_ai_provider', $mailAiProvider ?? 'default');
                                        $knownMailValues = ['disabled', 'default', 'gemini', 'custom'];
                                        foreach ($aiModelsRegistry['google_gemini_api'] ?? [] as $gm) {
                                            $knownMailValues[] = 'google_gemini_api::' . $gm['model'];
                                        }
                                        foreach ($aiModelsRegistry['openai_compatible'] ?? [] as $cm) {
                                            $knownMailValues[] = 'openai_compatible::' . $cm['model'];
                                        }
                                    @endphp
                                    <option value="disabled" {{ $currentMailAi === 'disabled' ? 'selected' : '' }}>
                                        Disabled</option>
                                    <option value="default" {{ $currentMailAi === 'default' ? 'selected' : '' }}>Auto
                                        (Active: {{ $aiConfig['active_label'] }})</option>
                                    @foreach ($aiModelsRegistry['google_gemini_api'] ?? [] as $m)
                                        <option value="google_gemini_api::{{ $m['model'] }}"
                                            {{ $currentMailAi === 'google_gemini_api::' . $m['model'] ? 'selected' : '' }}>
                                            [Google Gemini API] {{ $m['model'] }}
                                        </option>
                                    @endforeach
                                    @foreach ($aiModelsRegistry['openai_compatible'] ?? [] as $m)
                                        <option value="openai_compatible::{{ $m['model'] }}"
                                            {{ $currentMailAi === 'openai_compatible::' . $m['model'] ? 'selected' : '' }}>
                                            [OpenAI Compatible] {{ $m['model'] }}
                                        </option>
                                    @endforeach
                                    @if (in_array($currentMailAi, ['gemini', 'custom'], true))
                                        <option value="{{ $currentMailAi }}" selected>
                                            [Legacy]
                                            {{ $currentMailAi === 'gemini' ? 'Gemini 2.0 Flash' : $aiConfig['custom_ai_provider_name'] ?? 'Custom AI' }}
                                        </option>
                                    @endif
                                    @if (!in_array($currentMailAi, $knownMailValues, true) && !empty($currentMailAi))
                                        <option value="{{ $currentMailAi }}" selected>[Unlisted] {{ $currentMailAi }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                        </div>

                        <!-- Group 1: Server Connection (Hidden when Local Log) -->
                        <div id="smtpServerGroup"
                            class="border rounded p-3 mb-3 bg-light shadow-sm {{ $mailConfig['mail_mailer'] === 'log' ? 'd-none' : '' }}">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                <span class="small fw-bold text-uppercase text-dark" style="font-size: 10.5px;">
                                    <i class="fas fa-server me-1 text-primary"></i> 1. Server Connection
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2"
                                    id="btn-preset-resend" style="font-size: 11px;">
                                    <i class="fas fa-magic me-1"></i> Auto fill
                                </button>
                            </div>

                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                        for="mail_host">SMTP Host</label>
                                    <input id="mail_host" type="text" name="mail_host"
                                        value="{{ old('mail_host', $mailConfig['mail_host']) }}"
                                        class="form-control form-control-sm" placeholder="smtp.resend.com">
                                </div>
                                <div class="col-sm-3 col-6">
                                    <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                        for="mail_port">Port</label>
                                    <input id="mail_port" type="number" name="mail_port"
                                        value="{{ old('mail_port', $mailConfig['mail_port']) }}"
                                        class="form-control form-control-sm" placeholder="465">
                                </div>
                                <div class="col-sm-3 col-6">
                                    <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                        for="mail_encryption">Encryption</label>
                                    <select id="mail_encryption" name="mail_encryption"
                                        class="form-select form-select-sm">
                                        <option value="tls"
                                            {{ $mailConfig['mail_encryption'] === 'tls' ? 'selected' : '' }}>TLS (587)
                                        </option>
                                        <option value="ssl"
                                            {{ $mailConfig['mail_encryption'] === 'ssl' ? 'selected' : '' }}>SSL (465)
                                        </option>
                                        <option value="none"
                                            {{ $mailConfig['mail_encryption'] === 'none' ? 'selected' : '' }}>None
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Authentication & Sender Identity -->
                        <div class="border rounded p-3 mb-3 bg-white shadow-sm">
                            <div class="mb-2 pb-1 border-bottom">
                                <span class="small fw-bold text-uppercase text-dark" style="font-size: 10.5px;">
                                    <i class="fas fa-shield-alt me-1 text-primary"></i> Sender Identity
                                </span>
                            </div>

                            <!-- Credentials Row (Hidden when Local Log) -->
                            <div id="smtpCredentialsRow"
                                class="row g-2 mb-2 {{ $mailConfig['mail_mailer'] === 'log' ? 'd-none' : '' }}">
                                <div class="col-sm-6">
                                    <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                        for="mail_username">SMTP Username</label>
                                    <input id="mail_username" type="text" name="mail_username"
                                        value="{{ old('mail_username', $mailConfig['mail_username']) }}"
                                        class="form-control form-control-sm" placeholder="resend">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                        for="mail_password">Resend API Key (Password)</label>
                                    <input id="mail_password" type="password" name="mail_password"
                                        value="{{ old('mail_password', $mailConfig['mail_password'] ?? '') }}"
                                        class="form-control form-control-sm" autocomplete="new-password"
                                        placeholder="re_xxxxxxxxxxxx">
                                </div>
                            </div>

                            <!-- Sender Identity Row -->
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                        for="mail_from_address">From Address</label>
                                    <input id="mail_from_address" type="email" name="mail_from_address"
                                        value="{{ old('mail_from_address', $mailConfig['mail_from_address']) }}"
                                        class="form-control form-control-sm" placeholder="noreply@leonproject.my.id">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label mb-1 text-muted" style="font-size: 11px;"
                                        for="mail_from_name">From Sender Name</label>
                                    <input id="mail_from_name" type="text" name="mail_from_name"
                                        value="{{ old('mail_from_name', $mailConfig['mail_from_name']) }}"
                                        class="form-control form-control-sm" placeholder="FastCargo">
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn btn-success btn-sm px-4 fw-bold shadow-sm">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Unsaved AI Changes Warning Modal -->
    <div class="modal fade" id="unsavedAiChangesModal" tabindex="-1" aria-labelledby="unsavedAiChangesModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning bg-opacity-10 border-bottom border-warning border-opacity-25 py-3">
                    <h6 class="modal-title text-warning-emphasis fw-bold mb-0" id="unsavedAiChangesModalLabel">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i> Unsaved Configuration Changes
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2 text-dark fw-bold">You have unsaved AI configuration changes that have not been verified
                        yet.</p>
                    <p class="mb-0 text-muted small">If you switch engines now, your edited values will be discarded.
                        Please run <strong>"Test Connection"</strong> and click <strong>"Save Settings"</strong> first to
                        persist your configuration.</p>
                </div>
                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-primary btn-sm fw-bold" data-bs-dismiss="modal"
                        onclick="focusTestButton()">
                        <i class="fas fa-satellite-dish me-1"></i> Back to Test &amp; Save
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnConfirmSwitchAnyway">
                        Switch Engine Anyway
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New AI Model Modal -->
    <div class="modal fade" id="modalAddAiModel" tabindex="-1" aria-labelledby="modalAddAiModelLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light py-3">
                    <h6 class="modal-title fw-bold mb-0" id="modalAddAiModelLabel">
                        <i class="fas fa-plus-circle me-2 text-primary"></i>
                        <span id="modalAddAiTitleText">Add New AI Model
                            ({{ $isGemini ? 'Google Gemini API' : 'OpenAI Compatible' }})</span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div id="modalAddAiAlert" class="alert alert-danger py-2 px-3 small mb-3 d-none"></div>
                    <form id="formAddAiModel">
                        <input type="hidden" id="add_model_target_engine" name="engine"
                            value="{{ $isGemini ? 'google_gemini_api' : 'openai_compatible' }}">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1" for="new_model_name">Model
                                Identifier</label>
                            <input type="text" id="new_model_name" class="form-control form-control-sm"
                                placeholder="e.g. gpt-4o-mini, gemini-2.5-pro" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;"
                                    for="new_model_completion_tokens">Max Completion</label>
                                <input type="number" id="new_model_completion_tokens"
                                    class="form-control form-control-sm" value="4096" min="1">
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;"
                                    for="new_model_output_tokens">Max Output</label>
                                <input type="number" id="new_model_output_tokens" class="form-control form-control-sm"
                                    value="2048" min="1">
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;"
                                    for="new_model_max_tokens">Max Token</label>
                                <input type="number" id="new_model_max_tokens" class="form-control form-control-sm"
                                    value="8192" min="1">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm fw-bold" id="btnSubmitAddAiModel">
                        <i class="fas fa-save me-1"></i> Save Model
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MacroDroid Free Android Notification Listener Guide Modal -->
    <div class="modal fade" id="modalMacroDroidGuide" tabindex="-1" aria-labelledby="modalMacroDroidGuideLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-bottom">
                    <h5 class="modal-title fw-bold text-dark" id="modalMacroDroidGuideLabel">
                        <i class="fab fa-android text-success me-2"></i> Panduan MacroDroid (Rp 0) &mdash; Auto-Settlement
                        Notifikasi QRIS
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border small mb-3">
                        <strong>Zero-Cost Listener (Rp 0):</strong> MacroDroid Free di Android dapat meneruskan notifikasi
                        pembayaran QRIS masuk (GoPay Merchant / GoBiz, DANA Bisnis, ShopeePay, BCA, BRI) langsung ke
                        endpoint FastCargo secara real-time tanpa biaya API bulanan.
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold text-dark small mb-1">1. Trigger (Pemicu di MacroDroid)</div>
                        <div class="p-2 bg-light border rounded small">
                            Pilih <strong>Device Events &rarr; Notification &rarr; Notification Received</strong> &rarr;
                            centang aplikasi merchant Anda (<em>GoBiz / GoPay Merchant</em>, <em>DANA Bisnis</em>,
                            <em>ShopeePay Merchant</em>, atau aplikasi QRIS bank terkait).
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold text-dark small mb-1">2. Action &rarr; HTTP Request (POST)</div>
                        <label class="form-label text-muted mb-1" style="font-size: 11px;"
                            for="macrodroid-modal-url">Endpoint URL</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="macrodroid-modal-url" class="form-control font-monospace bg-white"
                                value="{{ $paygConfig['webhook_url'] }}" readonly>
                            <button type="button" class="btn btn-outline-primary" id="btn-copy-macrodroid-url">
                                <i class="fas fa-copy me-1"></i> Copy URL
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold text-dark small mb-1">3. HTTP Headers (Wajib)</div>
                        <div class="p-2 bg-light border rounded small">
                            <div class="mb-2 font-monospace" style="font-size: 12px;">
                                Content-Type: application/json
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                <div class="font-monospace text-dark" style="font-size: 12px;">
                                    X-PayG-Secret: <span id="macrodroid-secret-preview"
                                        class="fw-bold text-primary">Loading...</span>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2"
                                    id="btn-copy-macrodroid-secret">
                                    <i class="fas fa-key me-1"></i> Copy Secret
                                </button>
                            </div>
                            <div class="text-muted mt-1" style="font-size: 11px;">
                                Alternatif untuk developer: gunakan header HMAC <code>X-PayG-Signature:
                                    sha256=&lt;hmac&gt;</code> menggunakan secret yang sama.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="fw-bold text-dark small">4. Request Body (Content Type: application/json)</div>
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                id="btn-copy-macrodroid-json">
                                <i class="fas fa-copy me-1"></i> Copy JSON
                            </button>
                        </div>
                        <pre id="macrodroid-json-template" class="p-2 bg-dark text-light rounded font-monospace mb-1"
                            style="font-size: 12px; white-space: pre-wrap;">{"notification_text": "[not_text]", "app_package": "[not_app_package]", "title": "[not_title]"}</pre>
                        <div class="text-muted" style="font-size: 11px;">
                            Contoh format teks nominal yang otomatis dikenali: <code>Rp150.342</code>, <code>Rp
                                150.342,00</code>, <code>IDR 1.250.342</code>, <code>Rp50,001</code>. Pembayaran dicocokkan
                            dengan nominal tepat + kode unik dalam 24 jam terakhir.
                        </div>
                    </div>

                    <hr class="my-3">

                    <div>
                        <div class="fw-bold text-dark small mb-1">
                            <i class="fas fa-vial text-primary me-1"></i> Live Webhook Dry-Run / Test Matcher
                        </div>
                        <p class="text-muted mb-2" style="font-size: 11px;">
                            Uji ekstraksi nominal Rupiah dan cek apakah ada invoice UNPAID dengan nominal sama (read-only,
                            tidak mengubah status pembayaran).
                        </p>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" id="macrodroid-dryrun-text" class="form-control"
                                value="Kamu menerima pembayaran QRIS sebesar Rp150.342"
                                aria-label="Sample notification text for dry run">
                            <button type="button" class="btn btn-primary fw-semibold" id="btn-run-macrodroid-dryrun">
                                <i class="fas fa-play me-1"></i> Test Matcher
                            </button>
                        </div>
                        <div id="macrodroid-dryrun-result" class="alert alert-light border py-2 px-3 small mb-0 d-none"
                            role="status"></div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/dashboard/js/jsQR.js"></script>
    <script src="/assets/dashboard/js/qris-decoder.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Email Driver Dynamic Toggler & Resend Preset
            const mailMailerSelect = document.getElementById('mail_mailer');
            const smtpServerGroup = document.getElementById('smtpServerGroup');
            const smtpCredentialsRow = document.getElementById('smtpCredentialsRow');
            const badgeMailDriver = document.getElementById('badge-mail-driver');
            const badgeMailStatus = document.getElementById('badge-mail-status');
            const presetBtn = document.getElementById('btn-preset-resend');

            function toggleMailDriverFields() {
                if (!mailMailerSelect) return;
                const isLog = mailMailerSelect.value === 'log';
                if (smtpServerGroup) {
                    smtpServerGroup.classList.toggle('d-none', isLog);
                }
                if (smtpCredentialsRow) {
                    smtpCredentialsRow.classList.toggle('d-none', isLog);
                }
                if (presetBtn) {
                    presetBtn.classList.toggle('d-none', isLog);
                }
                if (badgeMailDriver) {
                    badgeMailDriver.textContent = 'Driver: ' + (isLog ? 'LOG' : 'SMTP');
                }
                if (badgeMailStatus && isLog) {
                    badgeMailStatus.className = 'badge bg-info text-dark';
                    badgeMailStatus.innerHTML = '<i class="fas fa-file-alt me-1"></i> Local Log Active';
                }
            }

            if (mailMailerSelect) {
                mailMailerSelect.addEventListener('change', toggleMailDriverFields);
                toggleMailDriverFields();
            }

            if (presetBtn) {
                presetBtn.addEventListener('click', function() {
                    if (mailMailerSelect) {
                        mailMailerSelect.value = 'smtp';
                        toggleMailDriverFields();
                    }
                    document.getElementById('mail_host').value = 'smtp.resend.com';
                    document.getElementById('mail_port').value = '465';
                    document.getElementById('mail_encryption').value = 'ssl';
                    document.getElementById('mail_username').value = 'resend';
                    document.getElementById('mail_from_address').value = 'noreply@leonproject.my.id';
                    document.getElementById('mail_from_name').value = 'FastCargo';
                    document.getElementById('mail_password').focus();
                });
            }

            // AI Provider Dynamic Switcher
            const providerSelect = document.getElementById('ai_provider_select');
            const geminiSection = document.getElementById('aiSectionGemini');
            const customSection = document.getElementById('aiSectionCustom');
            const btnSwitchEngine = document.getElementById('btnSwitchEngine');
            const btnSaveAi = document.getElementById('btnSaveAiSettings');
            const btnTestAi = document.getElementById('btnTestAiConnection');
            const testFeedback = document.getElementById('aiTestFeedback');
            const aiSwitchForm = document.getElementById('aiSwitchForm');
            const btnConfirmSwitch = document.getElementById('btnConfirmSwitchAnyway');
            const formAiSettings = document.getElementById('formAiSettings');

            let aiFormIsDirty = false;
            let aiConnectionVerified = true;

            try {
                const savedView = localStorage.getItem('fastcargo_ai_selected_view');
                if (savedView && providerSelect && (savedView === 'gemini' || savedView === 'custom')) {
                    providerSelect.value = savedView;
                    if (savedView === 'gemini') {
                        geminiSection.classList.remove('d-none');
                        customSection.classList.add('d-none');
                    } else {
                        geminiSection.classList.add('d-none');
                        customSection.classList.remove('d-none');
                    }
                }
            } catch (e) {}

            if (providerSelect) {
                providerSelect.addEventListener('change', function() {
                    try {
                        localStorage.setItem('fastcargo_ai_selected_view', this.value);
                    } catch (e) {}

                    if (this.value === 'gemini') {
                        geminiSection.classList.remove('d-none');
                        customSection.classList.add('d-none');
                    } else {
                        geminiSection.classList.add('d-none');
                        customSection.classList.remove('d-none');
                    }
                    testFeedback.classList.add('d-none');
                });
            }

            document.querySelectorAll('.ai-dirty-tracker').forEach(function(el) {
                el.addEventListener('input', function() {
                    aiFormIsDirty = true;
                    aiConnectionVerified = false;
                    updateSaveButtonState();
                });
            });

            function updateSaveButtonState() {
                if (aiFormIsDirty && !aiConnectionVerified) {
                    btnSaveAi.classList.remove('btn-primary');
                    btnSaveAi.classList.add('btn-secondary');
                    btnSaveAi.setAttribute('disabled', 'disabled');
                    btnSaveAi.setAttribute('title', 'Run Test Connection first to verify configuration changes');
                } else {
                    btnSaveAi.classList.remove('btn-secondary');
                    btnSaveAi.classList.add('btn-primary');
                    btnSaveAi.removeAttribute('disabled');
                    btnSaveAi.removeAttribute('title');
                }
            }

            if (btnSwitchEngine) {
                btnSwitchEngine.addEventListener('click', function(e) {
                    if (aiFormIsDirty) {
                        e.preventDefault();
                        const modalEl = document.getElementById('unsavedAiChangesModal');
                        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    }
                });
            }

            if (btnConfirmSwitch) {
                btnConfirmSwitch.addEventListener('click', function() {
                    aiFormIsDirty = false;
                    aiSwitchForm.submit();
                });
            }

            window.focusTestButton = function() {
                if (btnTestAi) {
                    btnTestAi.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    btnTestAi.focus();
                }
            };

            if (formAiSettings) {
                formAiSettings.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const originalSaveHtml = btnSaveAi.innerHTML;
                    btnSaveAi.disabled = true;
                    btnSaveAi.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';

                    const formData = new FormData(formAiSettings);
                    const payload = {};
                    formData.forEach(function(value, key) {
                        payload[key] = value;
                    });

                    fetch('{{ route('admin.settings.update', [], false) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            btnSaveAi.disabled = false;
                            btnSaveAi.innerHTML = originalSaveHtml;

                            if (data.success) {
                                testFeedback.className = 'alert alert-success py-2 px-3 small mb-3';
                                testFeedback.innerHTML =
                                    '<i class="fas fa-check-circle me-2"></i> <strong>Saved:</strong> ' +
                                    (data.message || 'AI configuration settings saved successfully.');
                                testFeedback.classList.remove('d-none');
                                aiFormIsDirty = false;
                                updateSaveButtonState();
                            } else {
                                testFeedback.className = 'alert alert-danger py-2 px-3 small mb-3';
                                testFeedback.innerHTML =
                                    '<i class="fas fa-times-circle me-2"></i> <strong>Failed:</strong> ' +
                                    (data.message || 'Failed to save configuration.');
                                testFeedback.classList.remove('d-none');
                            }
                        })
                        .catch(function(err) {
                            btnSaveAi.disabled = false;
                            btnSaveAi.innerHTML = originalSaveHtml;
                            testFeedback.className = 'alert alert-danger py-2 px-3 small mb-3';
                            testFeedback.innerHTML =
                                '<i class="fas fa-exclamation-triangle me-2"></i> <strong>Error:</strong> ' +
                                (err.message || 'Failed to contact server.');
                            testFeedback.classList.remove('d-none');
                        });
                });
            }

            if (btnTestAi) {
                btnTestAi.addEventListener('click', function() {
                    const originalHtml = btnTestAi.innerHTML;
                    btnTestAi.disabled = true;
                    btnTestAi.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Testing...';

                    testFeedback.className = 'alert alert-info py-2 px-3 small mb-3';
                    testFeedback.innerHTML =
                        '<i class="fas fa-spinner fa-spin me-2"></i> Contacting AI endpoint for real-time verification...';
                    testFeedback.classList.remove('d-none');

                    const selectedProvider = providerSelect ? providerSelect.value : 'gemini';
                    const payload = {
                        provider: selectedProvider,
                        gemini_api_key: document.getElementById('gemini_api_key') ? document
                            .getElementById('gemini_api_key').value : '',
                        gemini_model: document.getElementById('gemini_model') ? document.getElementById(
                            'gemini_model').value : '',
                        custom_ai_provider_name: document.getElementById('custom_ai_provider_name') ?
                            document.getElementById('custom_ai_provider_name').value : '',
                        custom_ai_base_url: document.getElementById('custom_ai_base_url') ? document
                            .getElementById('custom_ai_base_url').value : '',
                        custom_ai_api_key: document.getElementById('custom_ai_api_key') ? document
                            .getElementById('custom_ai_api_key').value : '',
                        custom_ai_model: document.getElementById('custom_ai_model') ? document
                            .getElementById('custom_ai_model').value : ''
                    };

                    fetch('{{ route('admin.settings.ai.test', [], false) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            btnTestAi.disabled = false;
                            btnTestAi.innerHTML = originalHtml;

                            if (data.success) {
                                testFeedback.className = 'alert alert-success py-2 px-3 small mb-3';
                                testFeedback.innerHTML =
                                    '<i class="fas fa-check-circle me-2"></i> <strong>Connected:</strong> ' +
                                    data.message;
                                aiConnectionVerified = true;
                                updateSaveButtonState();
                            } else {
                                testFeedback.className = 'alert alert-danger py-2 px-3 small mb-3';
                                testFeedback.innerHTML =
                                    '<i class="fas fa-times-circle me-2"></i> <strong>Connection Failed:</strong> ' +
                                    data.message;
                                aiConnectionVerified = false;
                                updateSaveButtonState();
                            }
                        })
                        .catch(function(err) {
                            btnTestAi.disabled = false;
                            btnTestAi.innerHTML = originalHtml;
                            testFeedback.className = 'alert alert-danger py-2 px-3 small mb-3';
                            testFeedback.innerHTML =
                                '<i class="fas fa-exclamation-triangle me-2"></i> <strong>Error:</strong> ' +
                                (err.message || 'Failed to contact server.');
                            aiConnectionVerified = false;
                            updateSaveButtonState();
                        });
                });
            }

            // AI Model Registry & Token Synchronization
            const inputMaxCompletion = document.getElementById('custom_ai_max_completion_tokens');
            const inputMaxOutput = document.getElementById('custom_ai_max_output_tokens');
            const inputMaxTokens = document.getElementById('custom_ai_max_tokens');

            function selectAiModel(engine, modelName, completion, output, max) {
                if (engine === 'google_gemini_api') {
                    const inputGemini = document.getElementById('gemini_model');
                    const labelGemini = document.getElementById('gemini_model_label');
                    if (inputGemini) {
                        inputGemini.value = modelName;
                        inputGemini.dispatchEvent(new Event('input', {
                            bubbles: true
                        }));
                        inputGemini.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    }
                    if (labelGemini) {
                        labelGemini.textContent = modelName;
                    }
                    const listEl = document.getElementById('dropdownGeminiModelList');
                    if (listEl) {
                        listEl.querySelectorAll('.model-item a').forEach(function(a) {
                            if (a.dataset.model === modelName) {
                                a.classList.add('active', 'fw-semibold');
                            } else {
                                a.classList.remove('active', 'fw-semibold');
                            }
                        });
                    }
                } else {
                    const inputCustom = document.getElementById('custom_ai_model');
                    const labelCustom = document.getElementById('custom_ai_model_label');
                    if (inputCustom) {
                        inputCustom.value = modelName;
                        inputCustom.dispatchEvent(new Event('input', {
                            bubbles: true
                        }));
                        inputCustom.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    }
                    if (labelCustom) {
                        labelCustom.textContent = modelName;
                    }
                    const listEl = document.getElementById('dropdownCustomModelList');
                    if (listEl) {
                        listEl.querySelectorAll('.model-item a').forEach(function(a) {
                            if (a.dataset.model === modelName) {
                                a.classList.add('active', 'fw-semibold');
                            } else {
                                a.classList.remove('active', 'fw-semibold');
                            }
                        });
                    }
                    if (completion && inputMaxCompletion) inputMaxCompletion.value = completion;
                    if (output && inputMaxOutput) inputMaxOutput.value = output;
                    if (max && inputMaxTokens) inputMaxTokens.value = max;
                }

                aiFormIsDirty = true;
                aiConnectionVerified = false;
                updateSaveButtonState();
            }

            // Modal Add AI Model Handler
            const btnAddGemini = document.getElementById('btnAddGeminiModel');
            const btnAddCustom = document.getElementById('btnAddCustomModel');
            const modalAddAiEl = document.getElementById('modalAddAiModel');
            const formAddAi = document.getElementById('formAddAiModel');
            const inputTargetEngine = document.getElementById('add_model_target_engine');
            const inputNewModelName = document.getElementById('new_model_name');
            const inputNewCompletion = document.getElementById('new_model_completion_tokens');
            const inputNewOutput = document.getElementById('new_model_output_tokens');
            const inputNewMax = document.getElementById('new_model_max_tokens');
            const btnSubmitAddAi = document.getElementById('btnSubmitAddAiModel');
            const modalAddAiAlert = document.getElementById('modalAddAiAlert');

            if (formAddAi) {
                formAddAi.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (btnSubmitAddAi) btnSubmitAddAi.click();
                });
            }

            if (modalAddAiEl) {
                modalAddAiEl.addEventListener('shown.bs.modal', function() {
                    if (inputNewModelName) inputNewModelName.focus();
                });
            }

            function openAddAiModelModal(engine) {
                if (!engine) {
                    engine = (providerSelect && providerSelect.value === 'gemini') ? 'google_gemini_api' :
                        'openai_compatible';
                }
                const engineLabel = (engine === 'google_gemini_api') ? 'Google Gemini API' : 'OpenAI Compatible';
                const titleEl = document.getElementById('modalAddAiTitleText');
                if (titleEl) {
                    titleEl.textContent = `Add New AI Model (${engineLabel})`;
                }
                if (inputTargetEngine) inputTargetEngine.value = engine;
                if (inputNewModelName) inputNewModelName.value = '';
                if (inputNewCompletion) inputNewCompletion.value = '4096';
                if (inputNewOutput) inputNewOutput.value = '2048';
                if (inputNewMax) inputNewMax.value = '8192';
                if (modalAddAiAlert) {
                    modalAddAiAlert.classList.add('d-none');
                    modalAddAiAlert.textContent = '';
                }
                if (modalAddAiEl && window.bootstrap && window.bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalAddAiEl).show();
                }
            }

            if (btnAddGemini) {
                btnAddGemini.addEventListener('click', function() {
                    openAddAiModelModal('google_gemini_api');
                });
            }

            if (btnAddCustom) {
                btnAddCustom.addEventListener('click', function() {
                    openAddAiModelModal('openai_compatible');
                });
            }

            if (btnSubmitAddAi) {
                btnSubmitAddAi.addEventListener('click', function() {
                    const engine = inputTargetEngine ? inputTargetEngine.value : 'openai_compatible';
                    const modelName = inputNewModelName ? inputNewModelName.value.trim() : '';

                    if (!modelName) {
                        if (modalAddAiAlert) {
                            modalAddAiAlert.textContent = 'Model identifier is required.';
                            modalAddAiAlert.classList.remove('d-none');
                        }
                        return;
                    }

                    const originalSubmitText = btnSubmitAddAi.innerHTML;
                    btnSubmitAddAi.disabled = true;
                    btnSubmitAddAi.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

                    const completionVal = inputNewCompletion ? parseInt(inputNewCompletion.value, 10) ||
                        4096 : 4096;
                    const outputVal = inputNewOutput ? parseInt(inputNewOutput.value, 10) || 2048 : 2048;
                    const maxVal = inputNewMax ? parseInt(inputNewMax.value, 10) || 8192 : 8192;

                    fetch('{{ route('admin.settings.ai.models.add', [], false) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                engine: engine,
                                model: modelName,
                                completion_tokens: completionVal,
                                output_tokens: outputVal,
                                max_tokens: maxVal
                            })
                        })
                        .then(function(res) {
                            return res.json().then(function(data) {
                                return {
                                    status: res.status,
                                    ok: res.ok,
                                    data: data
                                };
                            });
                        })
                        .then(function(resObj) {
                            btnSubmitAddAi.disabled = false;
                            btnSubmitAddAi.innerHTML = originalSubmitText;

                            if (resObj.ok && resObj.data.success) {
                                const targetList = (engine === 'google_gemini_api') ?
                                    document.getElementById('dropdownGeminiModelList') :
                                    document.getElementById('dropdownCustomModelList');

                                if (targetList) {
                                    const li = document.createElement('li');
                                    li.className =
                                        'd-flex align-items-center justify-content-between px-2 py-1 model-item';
                                    li.dataset.engine = engine;
                                    li.dataset.model = modelName;

                                    const a = document.createElement('a');
                                    a.className =
                                        'dropdown-item py-1 px-2 text-truncate rounded flex-grow-1 me-1';
                                    a.style.minWidth = '0';
                                    a.href = '#';
                                    a.dataset.action = 'select-model';
                                    a.dataset.engine = engine;
                                    a.dataset.model = modelName;
                                    a.dataset.completion = completionVal;
                                    a.dataset.output = outputVal;
                                    a.dataset.max = maxVal;
                                    a.textContent = modelName;

                                    const btnDel = document.createElement('button');
                                    btnDel.type = 'button';
                                    btnDel.className =
                                        'btn btn-sm btn-link text-danger text-decoration-none p-1 ms-1 btn-delete-ai-model';
                                    btnDel.dataset.action = 'delete-model';
                                    btnDel.dataset.engine = engine;
                                    btnDel.dataset.model = modelName;
                                    btnDel.title = `Remove model ${modelName}`;
                                    btnDel.setAttribute('aria-label', `Remove model ${modelName}`);
                                    btnDel.innerHTML =
                                        '<i class="fas fa-trash-alt" aria-hidden="true"></i>';

                                    li.appendChild(a);
                                    li.appendChild(btnDel);
                                    targetList.appendChild(li);

                                    selectAiModel(engine, modelName, completionVal, outputVal, maxVal);
                                }

                                const optVal = `${engine}::${modelName}`;
                                const label =
                                    `[${engine === 'google_gemini_api' ? 'Google Gemini API' : 'OpenAI Compatible'}] ${modelName}`;
                                [document.getElementById('wa_ai_provider'), document.getElementById(
                                    'mail_ai_provider')].forEach(function(sel) {
                                    if (sel) {
                                        const opt = document.createElement('option');
                                        opt.value = optVal;
                                        opt.textContent = label;
                                        sel.appendChild(opt);
                                    }
                                });

                                if (modalAddAiEl && window.bootstrap && window.bootstrap.Modal) {
                                    bootstrap.Modal.getOrCreateInstance(modalAddAiEl).hide();
                                }
                            } else {
                                if (modalAddAiAlert) {
                                    modalAddAiAlert.textContent = (resObj.data && resObj.data.message) ?
                                        resObj.data.message : 'Failed to add model.';
                                    modalAddAiAlert.classList.remove('d-none');
                                }
                            }
                        })
                        .catch(function(err) {
                            btnSubmitAddAi.disabled = false;
                            btnSubmitAddAi.innerHTML = originalSubmitText;
                            if (modalAddAiAlert) {
                                modalAddAiAlert.textContent = err.message || 'System error occurred.';
                                modalAddAiAlert.classList.remove('d-none');
                            }
                        });
                });
            }

            // Delete Model Handler
            function deleteAiModel(engine, modelName) {
                if (!engine || !modelName) return;

                const targetList = (engine === 'google_gemini_api') ?
                    document.getElementById('dropdownGeminiModelList') :
                    document.getElementById('dropdownCustomModelList');

                if (!targetList) return;

                const items = targetList.querySelectorAll('.model-item');
                if (items.length <= 1) {
                    alert('Cannot delete the last remaining model for this engine.');
                    return;
                }

                if (!confirm(`Remove model "${modelName}" from registry?`)) {
                    return;
                }

                const deleteBtns = targetList.querySelectorAll('button[data-action="delete-model"]');
                deleteBtns.forEach(function(btn) {
                    btn.disabled = true;
                });

                fetch('{{ route('admin.settings.ai.models.delete', [], false) }}', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            engine: engine,
                            model: modelName
                        })
                    })
                    .then(function(res) {
                        return res.json().then(function(data) {
                            return {
                                status: res.status,
                                ok: res.ok,
                                data: data
                            };
                        });
                    })
                    .then(function(resObj) {
                        deleteBtns.forEach(function(btn) {
                            btn.disabled = false;
                        });
                        if (resObj.ok && resObj.data.success) {
                            let removedItemWasActive = false;
                            const currentHiddenInput = (engine === 'google_gemini_api') ?
                                document.getElementById('gemini_model') :
                                document.getElementById('custom_ai_model');

                            if (currentHiddenInput && currentHiddenInput.value === modelName) {
                                removedItemWasActive = true;
                            }

                            targetList.querySelectorAll('.model-item').forEach(function(item) {
                                if (item.dataset.model === modelName) item.remove();
                            });

                            if (removedItemWasActive) {
                                const remainingItems = targetList.querySelectorAll('.model-item');
                                if (remainingItems.length > 0) {
                                    const firstRemaining = remainingItems[0];
                                    const firstLink = firstRemaining.querySelector(
                                        '[data-action="select-model"]');
                                    const nextModel = firstRemaining.dataset.model;
                                    const completion = firstLink ? firstLink.dataset.completion : 4096;
                                    const output = firstLink ? firstLink.dataset.output : 2048;
                                    const max = firstLink ? firstLink.dataset.max : 8192;
                                    selectAiModel(engine, nextModel, completion, output, max);
                                }
                            }

                            const optVal = `${engine}::${modelName}`;
                            [document.getElementById('wa_ai_provider'), document.getElementById(
                                'mail_ai_provider')].forEach(function(sel) {
                                if (sel) {
                                    const wasSelected = (sel.value === optVal);
                                    for (let i = 0; i < sel.options.length; i++) {
                                        if (sel.options[i].value === optVal) {
                                            sel.remove(i);
                                            break;
                                        }
                                    }
                                    if (wasSelected) {
                                        sel.value = 'default';
                                        sel.dispatchEvent(new Event('change'));
                                    }
                                }
                            });
                        } else {
                            alert((resObj.data && resObj.data.message) ? resObj.data.message :
                                'Failed to remove model.');
                        }
                    })
                    .catch(function(err) {
                        deleteBtns.forEach(function(btn) {
                            btn.disabled = false;
                        });
                        alert(err.message || 'Server communication error.');
                    });
            }

            function handleDropdownMenuClick(e) {
                const deleteBtn = e.target.closest('[data-action="delete-model"]');
                if (deleteBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    deleteAiModel(deleteBtn.dataset.engine, deleteBtn.dataset.model);
                    return;
                }

                const selectLink = e.target.closest('[data-action="select-model"]');
                if (selectLink) {
                    e.preventDefault();
                    selectAiModel(
                        selectLink.dataset.engine,
                        selectLink.dataset.model,
                        selectLink.dataset.completion,
                        selectLink.dataset.output,
                        selectLink.dataset.max
                    );

                    const toggleBtn = (selectLink.dataset.engine === 'google_gemini_api') ?
                        document.getElementById('dropdownGeminiModelBtn') :
                        document.getElementById('dropdownCustomModelBtn');
                    if (toggleBtn && window.bootstrap && window.bootstrap.Dropdown) {
                        const inst = bootstrap.Dropdown.getInstance(toggleBtn);
                        if (inst) inst.hide();
                    }
                }
            }

            const geminiListEl = document.getElementById('dropdownGeminiModelList');
            if (geminiListEl) geminiListEl.addEventListener('click', handleDropdownMenuClick);

            const customListEl = document.getElementById('dropdownCustomModelList');
            if (customListEl) customListEl.addEventListener('click', handleDropdownMenuClick);

            const btnDeleteGemini = document.getElementById('btnDeleteGeminiModel');
            if (btnDeleteGemini) {
                btnDeleteGemini.addEventListener('click', function() {
                    const input = document.getElementById('gemini_model');
                    if (input) deleteAiModel('google_gemini_api', input.value);
                });
            }

            const btnDeleteCustom = document.getElementById('btnDeleteCustomModel');
            if (btnDeleteCustom) {
                btnDeleteCustom.addEventListener('click', function() {
                    const input = document.getElementById('custom_ai_model');
                    if (input) deleteAiModel('openai_compatible', input.value);
                });
            }

            // Real-time WhatsApp Gateway Polling & Live Status Update
            const wagStatusUrl = '{{ route('admin.wa.status', [], false) }}';
            const wagBadgeEl = document.getElementById('wag-status-badge');
            const wagLeftBox = document.getElementById('wag-left-box');
            const wagDetailStatus = document.getElementById('wag-detail-status');
            const wagDetailPhone = document.getElementById('wag-detail-phone');
            const wagDetailPairedAt = document.getElementById('wag-detail-paired-at');

            if (wagBadgeEl && wagLeftBox) {
                function checkWagStatus() {
                    fetch(wagStatusUrl, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            if (!data.reachable) {
                                wagBadgeEl.innerHTML =
                                    '<span class="badge bg-danger px-2 py-1 small"><i class="fas fa-plug me-1"></i> Offline</span>';
                                if (wagDetailStatus) {
                                    wagDetailStatus.className = 'fw-bold small text-danger';
                                    wagDetailStatus.innerHTML =
                                        '<i class="fas fa-times-circle me-1"></i> Offline / Disconnected';
                                }
                                if (wagDetailPhone) wagDetailPhone.innerHTML =
                                    '<span class="text-muted fst-italic">Not Connected</span>';
                                if (wagDetailPairedAt) wagDetailPairedAt.innerHTML =
                                    '<i class="far fa-clock me-1"></i> Awaiting scan';
                                wagLeftBox.innerHTML = `
                                    <div class="text-danger mb-2"><i class="fas fa-plug fa-3x"></i></div>
                                    <span class="badge bg-danger px-2 py-1 mb-1 small">waG Offline</span>
                                    <div class="text-muted" style="font-size: 10px;">Service unreachable</div>
                                `;
                            } else if (data.connected) {
                                wagBadgeEl.innerHTML =
                                    '<span class="badge bg-success px-2 py-1 small"><i class="fas fa-check-circle me-1"></i> Connected</span>';
                                const userName = (data.user && data.user.name) ? data.user.name : ((data.user &&
                                        data.user.phone) ? 'WhatsApp (+' + data.user.phone + ')' :
                                    'Device Connected');
                                const userPhone = (data.user && data.user.phone) ? '+' + data.user.phone : '';
                                if (wagDetailStatus) {
                                    wagDetailStatus.className = 'fw-bold small text-success';
                                    wagDetailStatus.innerHTML =
                                        '<i class="fas fa-check-circle me-1"></i> Online';
                                }
                                if (wagDetailPhone) {
                                    wagDetailPhone.innerHTML = userPhone ? `<code>${userPhone}</code>` :
                                        '<span class="text-muted fst-italic">Connected</span>';
                                }
                                if (wagDetailPairedAt && data.paired_at) {
                                    wagDetailPairedAt.innerHTML =
                                        `<i class="far fa-clock me-1"></i> ${data.paired_at}`;
                                }
                                wagLeftBox.innerHTML = `
                                    <div class="text-success mb-2"><i class="fab fa-whatsapp fa-3x"></i></div>
                                    <span class="badge bg-success px-2 py-1 mb-1 small"><i class="fas fa-check-circle me-1"></i> Session Connected</span>
                                    <div class="small fw-bold text-dark mb-0" style="font-size: 11px;">${userName}</div>
                                    <code class="text-muted" style="font-size: 10px;">${userPhone}</code>
                                `;
                            } else if (data.qr_svg) {
                                wagBadgeEl.innerHTML =
                                    '<span class="badge bg-warning text-dark px-2 py-1 small"><i class="fas fa-qrcode me-1"></i> Pairing Needed</span>';
                                if (wagDetailStatus) {
                                    wagDetailStatus.className = 'fw-bold small text-warning';
                                    wagDetailStatus.innerHTML =
                                        '<i class="fas fa-hourglass-half me-1"></i> Awaiting Pairing';
                                }
                                if (wagDetailPhone) wagDetailPhone.innerHTML =
                                    '<span class="text-muted fst-italic">Not Connected</span>';
                                if (wagDetailPairedAt) wagDetailPairedAt.innerHTML =
                                    '<i class="far fa-clock me-1"></i> Awaiting scan';
                                wagLeftBox.innerHTML = `
                                    <div class="p-1 bg-white rounded border mb-1 d-flex align-items-center justify-content-center" style="width: 122px; height: 122px;">
                                        ${data.qr_svg}
                                    </div>
                                    <div class="fw-bold text-dark" style="font-size: 11px;"><i class="fas fa-qrcode me-1 text-primary"></i> Scan WhatsApp QR</div>
                                    <div class="text-muted" style="font-size: 9.5px;">Open WhatsApp &gt; Linked Devices</div>
                                `;
                            }
                        })
                        .catch(function() {});
                }
                setInterval(checkWagStatus, 4000);
            }

            // PayG Payment Station Handlers
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                @json(csrf_token());
            const qrPreviewUrl = @json(route('admin.settings.payment.qr-preview', [], false));
            const revealSecretUrl = @json(route('admin.settings.payment.reveal-secret', [], false));
            const webhookTestUrl = @json(route('admin.settings.payment.webhook-test', [], false));

            const btnTogglePaygSecret = document.getElementById('btn-toggle-payg-secret');
            const btnCopyPaygSecret = document.getElementById('btn-copy-payg-secret');
            const paygSecretInput = document.getElementById('payg_secret');
            const iconTogglePaygSecret = document.getElementById('icon-toggle-payg-secret');
            const btnCopyPaygWebhook = document.getElementById('btn-copy-payg-webhook');
            const paygWebhookInput = document.getElementById('payg_webhook_url');

            const staticQrTextarea = document.getElementById('payg_sgpay_static_qr');
            const qrPreviewFrame = document.getElementById('qris-preview-frame');
            const qrisDropzone = document.getElementById('qris-dropzone');
            const qrisImageInput = document.getElementById('qris-image-input');
            const qrisDecodeStatus = document.getElementById('qris-decode-status');
            const badgeStaticQrState = document.getElementById('badge-static-qr-state');
            const previewCrcBadge = document.getElementById('preview-crc-badge');
            const previewMerchantName = document.getElementById('preview-merchant-name');
            const previewMerchantCity = document.getElementById('preview-merchant-city');
            const previewAcquirerNmid = document.getElementById('preview-acquirer-nmid');

            let cachedRevealedSecret = null;

            async function resolveActivePaygSecret() {
                const currentVal = paygSecretInput ? paygSecretInput.value.trim() : '';
                if (currentVal && !currentVal.startsWith('••••')) {
                    return currentVal;
                }
                if (cachedRevealedSecret) {
                    return cachedRevealedSecret;
                }
                const resp = await fetch(revealSecretUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({})
                });
                if (!resp.ok) {
                    throw new Error('Could not load PayG secret.');
                }
                const data = await resp.json();
                cachedRevealedSecret = data.secret || '';
                return cachedRevealedSecret;
            }

            if (btnTogglePaygSecret && paygSecretInput && iconTogglePaygSecret) {
                btnTogglePaygSecret.addEventListener('click', async function() {
                    if (paygSecretInput.type === 'password') {
                        if (paygSecretInput.value.trim().startsWith('••••')) {
                            try {
                                const realSecret = await resolveActivePaygSecret();
                                if (realSecret) {
                                    paygSecretInput.value = realSecret;
                                }
                            } catch (err) {
                                console.error(err);
                            }
                        }
                        paygSecretInput.type = 'text';
                        iconTogglePaygSecret.className = 'fas fa-eye-slash';
                    } else {
                        paygSecretInput.type = 'password';
                        iconTogglePaygSecret.className = 'fas fa-eye';
                    }
                });
            }

            if (btnCopyPaygSecret && paygSecretInput) {
                btnCopyPaygSecret.addEventListener('click', async function() {
                    try {
                        const secretToCopy = await resolveActivePaygSecret();
                        if (!secretToCopy) return;
                        copyToClipboardSecure(secretToCopy, function() {
                            const orig = btnCopyPaygSecret.innerHTML;
                            btnCopyPaygSecret.className = 'btn btn-success';
                            btnCopyPaygSecret.innerHTML =
                                '<i class="fas fa-check me-1"></i> Copied';
                            setTimeout(function() {
                                btnCopyPaygSecret.className = 'btn btn-outline-primary';
                                btnCopyPaygSecret.innerHTML = orig;
                            }, 2000);
                        });
                    } catch (err) {
                        console.error('Copy secret failed:', err);
                    }
                });
            }

            function copyToClipboardSecure(text, onSuccess) {
                if (!text) return;
                const executeFallback = function() {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    ta.style.top = '-9999px';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    try {
                        const successful = document.execCommand('copy');
                        if (successful && typeof onSuccess === 'function') {
                            onSuccess();
                        }
                    } catch (err) {
                        console.error('Clipboard copy failed:', err);
                    }
                    document.body.removeChild(ta);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text)
                        .then(function() {
                            if (typeof onSuccess === 'function') onSuccess();
                        })
                        .catch(function() {
                            executeFallback();
                        });
                } else {
                    executeFallback();
                }
            }

            if (btnCopyPaygWebhook && paygWebhookInput) {
                btnCopyPaygWebhook.addEventListener('click', function() {
                    copyToClipboardSecure(paygWebhookInput.value, function() {
                        const orig = btnCopyPaygWebhook.innerHTML;
                        btnCopyPaygWebhook.className = 'btn btn-success';
                        btnCopyPaygWebhook.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
                        setTimeout(function() {
                            btnCopyPaygWebhook.className = 'btn btn-primary';
                            btnCopyPaygWebhook.innerHTML = orig;
                        }, 2000);
                    });
                });
            }

            function escapeHtml(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function setDecodeBanner(variant, htmlMessage) {
                if (!qrisDecodeStatus) return;
                qrisDecodeStatus.className = 'alert alert-' + variant + ' border py-2 px-2 mt-2 mb-0';
                qrisDecodeStatus.innerHTML = htmlMessage;
            }

            function renderEmptyQrPreview(message) {
                if (!qrPreviewFrame) return;
                const msg = escapeHtml(message ||
                    'Upload your GoBiz / DANA / Bank QR poster or paste an EMVCo QRIS string.');
                qrPreviewFrame.innerHTML =
                    '<div class="text-center text-muted py-3 px-2">' +
                    '<i class="fas fa-qrcode fa-2x mb-2 opacity-50"></i>' +
                    '<div class="small fw-semibold">No Static QRIS Configured</div>' +
                    '<div class="text-muted" style="font-size: 11px;">' + msg + '</div>' +
                    '</div>';
            }

            function updateMerchantMetadataUi(rawValue) {
                const cleaned = window.FastCargoQris ?
                    window.FastCargoQris.cleanQrisString(rawValue) :
                    String(rawValue || '').replace(/[\r\n]+/g, '').trim();

                if (!cleaned) {
                    if (previewMerchantName) previewMerchantName.textContent = '—';
                    if (previewMerchantCity) previewMerchantCity.textContent = '—';
                    if (previewAcquirerNmid) previewAcquirerNmid.textContent = '—';
                    if (previewCrcBadge) {
                        previewCrcBadge.className = 'badge bg-secondary';
                        previewCrcBadge.textContent = 'CRC-16 Unconfigured';
                    }
                    if (badgeStaticQrState) {
                        badgeStaticQrState.className = 'badge bg-secondary';
                        badgeStaticQrState.textContent = 'Not Set';
                    }
                    return null;
                }

                if (!window.FastCargoQris) return null;

                const parsed = window.FastCargoQris.parseEmvcoQris(cleaned);
                if (previewMerchantName) {
                    previewMerchantName.textContent = parsed.merchantName || '—';
                }
                if (previewMerchantCity) {
                    previewMerchantCity.textContent = parsed.merchantCity || '—';
                }
                if (previewAcquirerNmid) {
                    const acq = parsed.acquirerLabel || (parsed.acquirerDomain || '');
                    const nmid = parsed.nmid || '';
                    if (acq || nmid) {
                        previewAcquirerNmid.textContent = acq && nmid ? (acq + ' · ' + nmid) : (acq || nmid);
                    } else {
                        previewAcquirerNmid.textContent = '—';
                    }
                }
                if (previewCrcBadge) {
                    if (parsed.crcValid && parsed.valid) {
                        previewCrcBadge.className = 'badge bg-success';
                        previewCrcBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i> CRC-16 Valid';
                    } else {
                        previewCrcBadge.className = 'badge bg-danger';
                        previewCrcBadge.innerHTML =
                            '<i class="fas fa-exclamation-triangle me-1"></i> CRC-16 Invalid';
                    }
                }
                if (badgeStaticQrState) {
                    badgeStaticQrState.className = parsed.valid ? 'badge bg-success' : 'badge bg-danger';
                    badgeStaticQrState.textContent = parsed.valid ? 'Ready to Save' : 'Invalid';
                }
                return parsed;
            }

            async function refreshServerQrSvgPreview(rawValue) {
                const cleaned = window.FastCargoQris ?
                    window.FastCargoQris.cleanQrisString(rawValue) :
                    String(rawValue || '').replace(/[\r\n]+/g, '').trim();

                if (!cleaned) {
                    renderEmptyQrPreview();
                    return;
                }

                try {
                    const resp = await fetch(qrPreviewUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            qris: cleaned
                        })
                    });
                    const data = await resp.json();
                    if (data.valid && data.svg && qrPreviewFrame) {
                        qrPreviewFrame.innerHTML = data.svg;
                    } else {
                        renderEmptyQrPreview(data.metadata?.error ||
                            'Invalid EMVCo QRIS format or CRC-16 checksum.');
                    }
                } catch (err) {
                    console.error('QR preview render error:', err);
                }
            }

            let previewTimer = null;
            if (staticQrTextarea) {
                staticQrTextarea.addEventListener('input', function() {
                    const parsed = updateMerchantMetadataUi(staticQrTextarea.value);
                    clearTimeout(previewTimer);
                    previewTimer = setTimeout(function() {
                        refreshServerQrSvgPreview(staticQrTextarea.value);
                    }, 260);

                    if (parsed && parsed.raw && !parsed.valid) {
                        setDecodeBanner('warning', '<i class="fas fa-exclamation-triangle me-1"></i> ' +
                            escapeHtml(parsed.error || 'Invalid EMVCo QRIS string.'));
                    } else if (parsed && parsed.valid && qrisDecodeStatus && !qrisDecodeStatus.classList
                        .contains('d-none')) {
                        setDecodeBanner('success',
                            '<i class="fas fa-check-circle me-1"></i> Valid EMVCo QRIS detected (' +
                            escapeHtml(parsed.merchantName || 'Merchant') +
                            '). Click <strong>Save PayG Settings</strong> to apply.');
                    }
                });
            }

            function isSupportedImageFile(file) {
                if (!file) return false;
                if (file.type && file.type.startsWith('image/')) return true;
                return /\.(png|jpe?g|webp|gif|bmp)$/i.test(file.name || '');
            }

            async function handleQrImageFile(file) {
                if (!file) return;
                if (!isSupportedImageFile(file)) {
                    setDecodeBanner('warning',
                        '<i class="fas fa-exclamation-triangle me-1"></i> Please select a valid image file (PNG, JPG, or WEBP).'
                    );
                    return;
                }
                if (!window.FastCargoQris) {
                    setDecodeBanner('danger',
                        '<i class="fas fa-times-circle me-1"></i> Local QR decoder script failed to load.');
                    return;
                }
                setDecodeBanner('light',
                    '<i class="fas fa-spinner fa-spin me-1"></i> Decoding QR image locally in your browser...'
                );

                try {
                    const decoded = await window.FastCargoQris.decodeQrFromFile(file);
                    if (staticQrTextarea) {
                        staticQrTextarea.value = decoded.rawText;
                    }
                    const parsed = updateMerchantMetadataUi(decoded.rawText);
                    await refreshServerQrSvgPreview(decoded.rawText);

                    if (parsed && parsed.valid) {
                        setDecodeBanner(
                            'success',
                            '<i class="fas fa-check-circle me-1"></i> Decoded via <strong>' + escapeHtml(
                                decoded.method) + '</strong>' +
                            (parsed.acquirerLabel ? ' (' + escapeHtml(parsed.acquirerLabel) + ')' : '') +
                            '. Review merchant identity &amp; click <strong>Save PayG Settings</strong>.'
                        );
                    } else {
                        setDecodeBanner(
                            'warning',
                            '<i class="fas fa-exclamation-triangle me-1"></i> QR read via <strong>' +
                            escapeHtml(decoded.method) + '</strong>, but EMVCo QRIS validation failed: ' +
                            escapeHtml((parsed && parsed.error) ? parsed.error : 'Not an EMVCo QRIS code.')
                        );
                    }
                } catch (err) {
                    setDecodeBanner('danger', '<i class="fas fa-times-circle me-1"></i> ' + escapeHtml(err
                        .message || 'Unable to decode QR code from image.'));
                }
            }

            if (qrisDropzone && qrisImageInput) {
                qrisDropzone.addEventListener('click', function() {
                    qrisImageInput.click();
                });
                qrisDropzone.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        qrisImageInput.click();
                    }
                });
                qrisDropzone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    qrisDropzone.classList.add('is-dragover');
                });
                qrisDropzone.addEventListener('dragleave', function() {
                    qrisDropzone.classList.remove('is-dragover');
                });
                qrisDropzone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    qrisDropzone.classList.remove('is-dragover');
                    const file = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
                    if (file) handleQrImageFile(file);
                });
                qrisImageInput.addEventListener('change', function() {
                    const file = qrisImageInput.files ? qrisImageInput.files[0] : null;
                    if (file) handleQrImageFile(file);
                    qrisImageInput.value = '';
                });
            }

            // MacroDroid Modal Handlers
            const modalMacroDroidGuide = document.getElementById('modalMacroDroidGuide');
            const macrodroidSecretPreview = document.getElementById('macrodroid-secret-preview');
            const macrodroidModalUrl = document.getElementById('macrodroid-modal-url');
            const btnCopyMacrodroidUrl = document.getElementById('btn-copy-macrodroid-url');
            const btnCopyMacrodroidSecret = document.getElementById('btn-copy-macrodroid-secret');
            const btnCopyMacrodroidJson = document.getElementById('btn-copy-macrodroid-json');
            const macrodroidJsonTemplate = document.getElementById('macrodroid-json-template');
            const macrodroidDryrunText = document.getElementById('macrodroid-dryrun-text');
            const btnRunMacrodroidDryrun = document.getElementById('btn-run-macrodroid-dryrun');
            const macrodroidDryrunResult = document.getElementById('macrodroid-dryrun-result');

            if (modalMacroDroidGuide && macrodroidSecretPreview) {
                modalMacroDroidGuide.addEventListener('show.bs.modal', async function() {
                    try {
                        const sec = await resolveActivePaygSecret();
                        macrodroidSecretPreview.textContent = sec || 'fastcargo-payg-secret-2026';
                    } catch (err) {
                        macrodroidSecretPreview.textContent = '<configured payg_secret>';
                    }
                });
            }

            if (btnCopyMacrodroidUrl && macrodroidModalUrl) {
                btnCopyMacrodroidUrl.addEventListener('click', function() {
                    copyToClipboardSecure(macrodroidModalUrl.value, function() {
                        const orig = btnCopyMacrodroidUrl.innerHTML;
                        btnCopyMacrodroidUrl.className = 'btn btn-success';
                        btnCopyMacrodroidUrl.innerHTML = '<i class="fas fa-check me-1"></i> Copied';
                        setTimeout(function() {
                            btnCopyMacrodroidUrl.className = 'btn btn-outline-primary';
                            btnCopyMacrodroidUrl.innerHTML = orig;
                        }, 2000);
                    });
                });
            }

            if (btnCopyMacrodroidSecret) {
                btnCopyMacrodroidSecret.addEventListener('click', async function() {
                    try {
                        const sec = await resolveActivePaygSecret();
                        if (!sec) return;
                        copyToClipboardSecure(sec, function() {
                            const orig = btnCopyMacrodroidSecret.innerHTML;
                            btnCopyMacrodroidSecret.className =
                                'btn btn-success btn-sm py-0 px-2';
                            btnCopyMacrodroidSecret.innerHTML =
                                '<i class="fas fa-check me-1"></i> Copied';
                            setTimeout(function() {
                                btnCopyMacrodroidSecret.className =
                                    'btn btn-outline-secondary btn-sm py-0 px-2';
                                btnCopyMacrodroidSecret.innerHTML = orig;
                            }, 2000);
                        });
                    } catch (err) {
                        console.error(err);
                    }
                });
            }

            if (btnCopyMacrodroidJson && macrodroidJsonTemplate) {
                btnCopyMacrodroidJson.addEventListener('click', function() {
                    copyToClipboardSecure(macrodroidJsonTemplate.textContent.trim(), function() {
                        const orig = btnCopyMacrodroidJson.innerHTML;
                        btnCopyMacrodroidJson.className = 'btn btn-success btn-sm py-0 px-2';
                        btnCopyMacrodroidJson.innerHTML =
                            '<i class="fas fa-check me-1"></i> Copied';
                        setTimeout(function() {
                            btnCopyMacrodroidJson.className =
                                'btn btn-outline-primary btn-sm py-0 px-2';
                            btnCopyMacrodroidJson.innerHTML = orig;
                        }, 2000);
                    });
                });
            }

            if (btnRunMacrodroidDryrun && macrodroidDryrunText && macrodroidDryrunResult) {
                btnRunMacrodroidDryrun.addEventListener('click', async function() {
                    const text = macrodroidDryrunText.value.trim();
                    if (!text) return;

                    btnRunMacrodroidDryrun.disabled = true;
                    macrodroidDryrunResult.className = 'alert alert-light border py-2 px-3 small mb-0';
                    macrodroidDryrunResult.innerHTML =
                        '<i class="fas fa-spinner fa-spin me-1"></i> Testing amount extraction...';

                    try {
                        const resp = await fetch(webhookTestUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                notification_text: text
                            })
                        });
                        const data = await resp.json();
                        if (resp.ok && data.success) {
                            const variant = data.matching_invoices > 0 ? 'success' : 'info';
                            macrodroidDryrunResult.className = 'alert alert-' + variant +
                                ' border py-2 px-3 small mb-0';
                            macrodroidDryrunResult.innerHTML =
                                '<strong>IDR ' + Number(data.amount).toLocaleString('id-ID') +
                                '</strong> extracted &mdash; ' +
                                escapeHtml(data.message || '') +
                                (data.matching_invoices > 0 ? ' <span class="badge bg-success ms-1">' +
                                    Number(data.matching_invoices) + ' pending match</span>' : '');
                        } else {
                            macrodroidDryrunResult.className =
                                'alert alert-warning border py-2 px-3 small mb-0';
                            macrodroidDryrunResult.textContent = data.message ||
                                'Could not extract a positive IDR amount.';
                        }
                    } catch (err) {
                        macrodroidDryrunResult.className =
                            'alert alert-danger border py-2 px-3 small mb-0';
                        macrodroidDryrunResult.textContent = 'Dry-run request failed: ' + err.message;
                    } finally {
                        btnRunMacrodroidDryrun.disabled = false;
                    }
                });
            }
        });
    </script>
@endsection
