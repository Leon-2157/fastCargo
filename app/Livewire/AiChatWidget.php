<?php

namespace App\Livewire;

use App\Modules\Shared\AI\AiManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class AiChatWidget extends Component
{
    public bool $isOpen = false;

    public string $userMessage = '';

    /** @var list<array{role: string, content: string, time: string}> */
    public array $messages = [];

    /** @var array<string, mixed> */
    public array $pageContext = [];

    public string $role = 'customer';

    /**
     * @param  array<string, mixed>  $pageContext
     */
    public function mount(string $role = 'customer', array $pageContext = []): void
    {
        $this->role = in_array($role, ['customer', 'operator', 'admin'], true) ? $role : 'customer';
        $this->pageContext = $pageContext;

        $user = auth($this->role)->user();
        $userName = $user ? $user->name : 'User';

        $greeting = match ($this->role) {
            'operator' => "Hello {$userName}! I am your FastCargo Operations AI Copilot. Ask me about active manifests, vessel telemetry, port schedules, or weather holds.",
            'admin' => "Greetings Administrator {$userName}. I am the FastCargo Governance & Analytics AI. Ask me about revenue escrow, KYC status, or operator compliance.",
            default => "Hello {$userName}! FastCargo AI Assistant here. How can I assist with your shipments, waybill tracking, or freight quotes today?",
        };

        $this->messages = [
            ['role' => 'assistant', 'content' => $greeting, 'time' => now()->format('H:i')],
        ];
    }

    public function toggleChat(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    public function runQuickPrompt(string $preset): void
    {
        $prompts = $this->quickPrompts();
        if (! isset($prompts[$preset])) {
            return;
        }

        $this->isOpen = true;
        $this->dispatchPrompt($prompts[$preset]['prompt']);
    }

    public function sendMessage(): void
    {
        $trimmed = trim($this->userMessage);
        if ($trimmed === '') {
            return;
        }

        $this->userMessage = '';
        $this->dispatchPrompt(mb_substr($trimmed, 0, 1000));
    }

    /**
     * @return array<string, array{label: string, prompt: string}>
     */
    public function quickPrompts(): array
    {
        return match ($this->role) {
            'operator' => [
                'ops_brief' => [
                    'label' => 'Ops Brief',
                    'prompt' => 'Summarize current hub dispatch workload, manifests, and any immediate action items based on the dashboard context.',
                ],
                'seal_check' => [
                    'label' => 'Seal & Hold Check',
                    'prompt' => 'What priority checks should a terminal operator verify before sealing containers or clearing customs holds?',
                ],
            ],
            'admin' => [
                'gov_brief' => [
                    'label' => 'Governance Brief',
                    'prompt' => 'Give an executive governance brief on escrow funds, pending KYC, and operator onboarding backlog.',
                ],
                'risk_scan' => [
                    'label' => 'Risk Scan',
                    'prompt' => 'Highlight top compliance or operational risks visible from the current governance metrics.',
                ],
            ],
            default => [
                'ship_summary' => [
                    'label' => 'Summarize Shipments',
                    'prompt' => 'Summarize my active shipments, completed deliveries, and any unpaid invoices from the dashboard context.',
                ],
                'hs_guidance' => [
                    'label' => 'Customs & HS Tips',
                    'prompt' => 'Give brief guidance on preparing HS codes and packing lists for ASEAN cross-border LCL/FCL bookings.',
                ],
            ],
        };
    }

    public function render(): View
    {
        return view('livewire.ai-chat-widget', [
            'activeEngine' => AiManager::activeProviderLabel(),
            'quickPrompts' => $this->quickPrompts(),
        ]);
    }

    private function dispatchPrompt(string $prompt): void
    {
        $this->isOpen = true;
        $history = array_slice($this->messages, 1);

        $this->messages[] = [
            'role' => 'user',
            'content' => $prompt,
            'time' => now()->format('H:i'),
        ];

        try {
            $user = auth($this->role)->user();
            $systemPrompt = "You are the FastCargo AI Assistant for role [{$this->role}]. "
                .'User Name: '.($user ? $user->name : 'Guest').'. '
                .'Respond concisely in plain markdown/bullet points grounded strictly in FastCargo ASEAN cargo logistics and provided dashboard context. Never invent tracking numbers or fabricate external secrets.';

            $context = array_merge($this->pageContext, ['history' => $history]);
            $reply = AiManager::chat($prompt, $context, $systemPrompt);

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $reply,
                'time' => now()->format('H:i'),
            ];
        } catch (Throwable $e) {
            Log::error('AiChatWidget sendMessage failed', [
                'role' => $this->role,
                'error' => $e->getMessage(),
            ]);

            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'AI Assistant is temporarily unavailable. Please verify AI engine settings or try again in a moment.',
                'time' => now()->format('H:i'),
            ];
        }

        $this->dispatch('ai-chat-updated');
    }
}
