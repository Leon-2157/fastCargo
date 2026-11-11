<div class="card shadow mb-4 border-start-info">
  <div class="card-header py-3 bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2" style="min-width: 0; max-width: 100%;">
      <i class="fas fa-brain text-info flex-shrink-0" aria-hidden="true"></i>
      <h6 class="text-primary m-0 fw-bold">{{ $title }}</h6>
      <span class="badge bg-light text-dark border small text-wrap text-break" style="max-width: 100%;">{{ $activeEngine }}</span>
    </div>
    <button
      type="button"
      wire:click="generateInsight"
      wire:loading.attr="disabled"
      class="btn btn-sm btn-outline-primary cockpit-touch-btn"
    >
      <span wire:loading.remove wire:target="generateInsight">
        <i class="fas fa-magic me-1" aria-hidden="true"></i> {{ $insight ? 'Refresh AI Insight' : 'Generate AI Insight' }}
      </span>
      <span wire:loading wire:target="generateInsight">
        <i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i> Analyzing...
      </span>
    </button>
  </div>
  <div class="card-body">
    @if($errorMessage)
      <div class="alert alert-warning mb-0 small">{{ $errorMessage }}</div>
    @elseif($insight)
      @php
        $safeHtml = e($insight);
        $safeHtml = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safeHtml) ?? $safeHtml;
        $safeHtml = preg_replace('/`([^`]+)`/', '<code>$1</code>', $safeHtml) ?? $safeHtml;
      @endphp
      <div class="text-dark small">{!! nl2br($safeHtml) !!}</div>
      @if($generatedAt)
        <div class="mt-2" style="color: #475569; font-size: 11px;">Generated at {{ $generatedAt }} using {{ $activeEngine }}</div>
      @endif
    @else
      <p class="mb-0 small" style="color: #475569;">
        Click <strong>Generate AI Insight</strong> for an instant summary tailored to your current {{ $role }} operational context.
      </p>
    @endif
  </div>
</div>

