<div>
  <!-- Floating Launcher Button -->
  <button
    wire:click="toggleChat"
    type="button"
    class="btn btn-primary rounded-circle shadow-lg position-fixed d-flex align-items-center justify-content-center"
    style="bottom: 24px; right: 24px; width: 60px; height: 60px; z-index: 1050; border: 2px solid rgba(255,255,255,0.2);"
    aria-label="Open FastCargo AI Copilot"
  >
    @if($isOpen)
      <i class="fas fa-times fs-4"></i>
    @else
      <i class="fas fa-robot fs-4"></i>
    @endif
  </button>

  <!-- Slide-up Chat Panel -->
  @if($isOpen)
    <div
      class="card shadow-lg position-fixed border-0 overflow-hidden"
      style="bottom: 96px; right: 24px; width: 390px; max-width: calc(100vw - 32px); height: 540px; z-index: 1050; border-radius: 16px; background: #0E1526; color: #fff; box-shadow: 0 20px 40px rgba(0,0,0,0.4);"
    >
      <!-- Chat Header -->
      <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center py-3 px-3">
        <div class="d-flex align-items-center">
          <div class="bg-white text-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
            <i class="fas fa-brain small"></i>
          </div>
          <div>
            <h6 class="mb-0 fw-bold">FastCargo AI Copilot</h6>
            <small class="text-white-50" style="font-size: 11px;">Engine: {{ $activeEngine }}</small>
          </div>
        </div>
        <button wire:click="toggleChat" type="button" class="btn btn-sm btn-link text-white p-0" aria-label="Close">
          <i class="fas fa-chevron-down"></i>
        </button>
      </div>

      <!-- Quick Prompt Chips -->
      <div class="px-3 py-2 border-bottom border-secondary border-opacity-25 d-flex flex-wrap gap-1" style="background: #11192E;">
        @foreach($quickPrompts as $key => $preset)
          <button
            type="button"
            wire:click="runQuickPrompt('{{ $key }}')"
            wire:loading.attr="disabled"
            class="btn btn-outline-info btn-sm py-0 px-2"
            style="font-size: 11px;"
          >
            {{ $preset['label'] }}
          </button>
        @endforeach
      </div>

      <!-- Messages Body -->
      <div class="card-body p-3 overflow-y-auto" style="height: 355px; background: #0A0F1D;" id="aiMessageContainer">
        @foreach($messages as $msg)
          @if($msg['role'] === 'assistant')
            <div class="d-flex mb-3">
              <div class="bg-primary text-white rounded-circle me-2 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 12px;">
                <i class="fas fa-robot"></i>
              </div>
              <div class="bg-dark p-2 px-3 rounded-3 border border-secondary border-opacity-25 text-white-50" style="font-size: 13px; max-width: 82%;">
                @php
                  $safeChatHtml = e($msg['content']);
                  $safeChatHtml = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safeChatHtml) ?? $safeChatHtml;
                  $safeChatHtml = preg_replace('/`([^`]+)`/', '<code>$1</code>', $safeChatHtml) ?? $safeChatHtml;
                @endphp
                <div class="text-white">{!! nl2br($safeChatHtml) !!}</div>
                <div class="text-end text-white-50 mt-1" style="font-size: 10px;">{{ $msg['time'] }}</div>
              </div>
            </div>
          @else
            <div class="d-flex justify-content-end mb-3">
              <div class="bg-primary text-white p-2 px-3 rounded-3" style="font-size: 13px; max-width: 82%;">
                <div>{{ $msg['content'] }}</div>
                <div class="text-end text-white-50 mt-1" style="font-size: 10px;">{{ $msg['time'] }}</div>
              </div>
            </div>
          @endif
        @endforeach

        <div wire:loading.flex wire:target="sendMessage,runQuickPrompt" class="mb-2">
          <div class="bg-dark p-2 px-3 rounded-3 text-info small">
            <i class="fas fa-spinner fa-spin me-1"></i> FastCargo AI is analyzing logistics data...
          </div>
        </div>
      </div>

      <!-- Chat Footer / Input -->
      <div class="card-footer bg-dark border-top border-secondary border-opacity-25 p-2 px-3">
        <form wire:submit.prevent="sendMessage" class="d-flex align-items-center gap-2">
          <input
            id="aiChatInput"
            name="userMessage"
            type="text"
            wire:model.defer="userMessage"
            class="form-control form-control-sm bg-black text-white border-secondary border-opacity-50"
            placeholder="Ask anything about your operations..."
            aria-label="Ask anything about your operations"
            autocomplete="off"
            style="font-size: 13px;"
            required
          >
          <button type="submit" class="btn btn-sm btn-primary px-3" wire:loading.attr="disabled" aria-label="Send message">
            <i class="fas fa-paper-plane"></i>
          </button>
        </form>
      </div>
    </div>
  @endif

  <script>
    document.addEventListener('livewire:init', () => {
      Livewire.on('ai-chat-updated', () => {
        setTimeout(() => {
          const el = document.getElementById('aiMessageContainer');
          if (el) {
            el.scrollTop = el.scrollHeight;
          }
        }, 50);
      });
    });
  </script>
</div>
