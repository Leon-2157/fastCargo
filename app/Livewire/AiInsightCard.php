<?php

namespace App\Livewire;

use App\Modules\Shared\AI\AiManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * Reusable on-demand AI Summary & Operational Insight card for Customer, Operator, and Admin dashboards.
 */
class AiInsightCard extends Component
{
    public string $role = 'customer';

    /** @var array<string, mixed> */
    public array $pageContext = [];

    public ?string $insight = null;

    public ?string $generatedAt = null;

    public ?string $errorMessage = null;

    /**
     * @param  array<string, mixed>  $pageContext
     */
    public function mount(string $role = 'customer', array $pageContext = []): void
    {
        $this->role = in_array($role, ['customer', 'operator', 'admin'], true) ? $role : 'customer';
        $this->pageContext = $pageContext;
    }

    public function generateInsight(): void
    {
        $this->errorMessage = null;

        try {
            $systemContext = match ($this->role) {
                'operator' => 'You are FastCargo Operations AI. Provide 3 short, practical bullet points summarizing dispatch readiness, manifests, approvals, and incidents.',
                'admin' => 'You are FastCargo Executive Governance AI. Provide 3 short, practical bullet points covering platform KYC backlog, operator onboarding, and escrow/revenue health.',
                default => 'You are FastCargo Customer Cargo Advisor. Provide 3 short, practical bullet points summarizing active bookings, delivery status, and unpaid invoices.',
            };

            $this->insight = AiManager::summarize($this->pageContext, $systemContext);
            $this->generatedAt = now()->format('H:i:s');
        } catch (Throwable $e) {
            Log::warning('AiInsightCard summarize failed', [
                'role' => $this->role,
                'error' => $e->getMessage(),
            ]);

            $this->errorMessage = 'AI summary is temporarily unavailable. Check AI engine configuration or try again.';
        }
    }

    public function render(): View
    {
        $title = match ($this->role) {
            'operator' => 'AI Operations & Manifest Insight',
            'admin' => 'AI Governance & Risk Briefing',
            default => 'AI Shipment & Invoice Summary',
        };

        return view('livewire.ai-insight-card', [
            'title' => $title,
            'activeEngine' => AiManager::activeProviderLabel(),
        ]);
    }
}
