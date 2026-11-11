<?php

namespace Tests\Feature;

use App\Livewire\AiChatWidget;
use App\Livewire\AiInsightCard;
use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\AI\Providers\GeminiProvider;
use App\Modules\Shared\AI\Providers\NineRouterProvider;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class Phase2AiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        AiManager::reset();
    }

    public function test_admin_can_switch_ai_provider_and_audit_trail_records_change(): void
    {
        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/settings/ai/switch', [
                'ai_provider' => 'gemini',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame('gemini', AdmSystemSetting::where('key', 'ai_provider')->value('value'));
        $this->assertSame('gemini', AiManager::activeProviderKey());
        $this->assertInstanceOf(GeminiProvider::class, AiManager::provider());

        $audit = DB::table('adm_immutable_audit_trails')
            ->where('action', 'AI_PROVIDER_SWITCHED')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('gemini', (string) $audit->after);

        // Switching back to 9router takes effect immediately without restart
        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/settings/ai/switch', [
                'ai_provider' => '9router',
            ])
            ->assertRedirect();

        $this->assertInstanceOf(NineRouterProvider::class, AiManager::provider());
    }

    public function test_admin_switch_ai_provider_rejects_invalid_engine(): void
    {
        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/settings/ai/switch', [
                'ai_provider' => 'invalid-engine',
            ]);

        $response->assertSessionHasErrors('ai_provider');
        $this->assertSame('9router', AiManager::activeProviderKey());
    }

    public function test_admin_settings_masks_secrets_in_html_and_preserves_existing_keys_when_blank(): void
    {
        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();

        AdmSystemSetting::updateOrCreate(
            ['key' => 'ninerouter_api_key'],
            ['value' => 'secret-token-should-never-render', 'type' => 'string']
        );
        AiManager::reset();

        $page = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/settings');

        $page->assertStatus(200);
        $page->assertSee('Configured');
        $page->assertDontSee('secret-token-should-never-render');

        $update = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->put('http://admin.fastcargo.local/settings', [
                'ninerouter_base_url' => 'http://127.0.0.1:20128/v1',
                'ninerouter_api_key' => '',
                'ninerouter_model' => 'ag/gemini-3.8-flash-high',
                'gemini_model' => 'gemini-2.5-flash',
            ]);

        $update->assertRedirect();
        $update->assertSessionHas('success');

        $this->assertSame(
            'secret-token-should-never-render',
            AdmSystemSetting::where('key', 'ninerouter_api_key')->value('value')
        );
        $this->assertSame(
            'http://127.0.0.1:20128/v1',
            AdmSystemSetting::where('key', 'ninerouter_base_url')->value('value')
        );
        $this->assertSame(
            'gemini-2.5-flash',
            AdmSystemSetting::where('key', 'gemini_model')->value('value')
        );
    }

    public function test_ai_manager_routes_to_ninerouter_and_falls_back_to_gemini_on_error(): void
    {
        AdmSystemSetting::updateOrCreate(['key' => 'ai_provider'], ['value' => '9router', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'ninerouter_base_url'], ['value' => 'http://9router.test/v1', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'ninerouter_api_key'], ['value' => 'test-9r-key', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'gemini_api_key'], ['value' => 'test-gemini-key', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'gemini_model'], ['value' => 'gemini-2.0-flash', 'type' => 'string']);
        AiManager::reset();

        Http::fake([
            '9router.test/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => 'Route IDJKT to SGSIN takes ~2 days via SEA.']]]], 200)
                ->push(['error' => 'upstream down'], 503),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Fallback Gemini summary ready.']]]]],
            ], 200),
        ]);

        $reply = AiManager::chat(
            'How long is Jakarta to Singapore?',
            ['active_shipments' => 1, 'history' => [['role' => 'user', 'content' => 'Hello']]],
            'You are FastCargo AI.'
        );

        $this->assertSame('Route IDJKT to SGSIN takes ~2 days via SEA.', $reply);

        $fallbackReply = AiManager::summarize(['active_shipments' => 3], 'Customer summary');
        $this->assertSame('Fallback Gemini summary ready.', $fallbackReply);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }

            return ! str_contains($request->url(), '?key=')
                && $request->hasHeader('x-goog-api-key', 'test-gemini-key');
        });
    }

    public function test_livewire_ai_chat_widget_and_insight_card_render_and_respond_cleanly(): void
    {
        AdmSystemSetting::updateOrCreate(['key' => 'ai_provider'], ['value' => '9router', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'ninerouter_base_url'], ['value' => 'http://9router.test/v1', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'ninerouter_api_key'], ['value' => 'test-9r-key', 'type' => 'string']);
        AiManager::reset();

        Http::fake([
            '9router.test/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => 'You have 1 active shipment RPN-20261106-001.']]]], 200)
                ->push(['choices' => [['message' => ['content' => '- 1 active manifest preparing at IDJKT.']]]], 200)
                ->push(['error' => 'failed'], 500),
        ]);

        $customer = User::where('email', 'customer@example.com')->firstOrFail();

        Livewire::actingAs($customer, 'customer')
            ->test(AiChatWidget::class, [
                'role' => 'customer',
                'pageContext' => ['active_shipments' => 1],
            ])
            ->assertSee('FastCargo AI Copilot')
            ->set('userMessage', 'Summarize my booking')
            ->call('sendMessage')
            ->assertSet('userMessage', '')
            ->assertSee('You have 1 active shipment RPN-20261106-001.');

        $operator = User::where('email', 'operator@fastcargo.com')->firstOrFail();

        Livewire::actingAs($operator, 'operator')
            ->test(AiInsightCard::class, [
                'role' => 'operator',
                'pageContext' => ['hub_id' => 'IDJKT', 'active_manifests' => 1],
            ])
            ->assertSee('AI Operations & Manifest Insight')
            ->call('generateInsight')
            ->assertSee('1 active manifest preparing at IDJKT.');

        // Error case never leaks raw HTTP/exception strings to the user UI
        Livewire::actingAs($customer, 'customer')
            ->test(AiChatWidget::class, ['role' => 'customer'])
            ->set('userMessage', 'Trigger failure')
            ->call('sendMessage')
            ->assertSee('AI Assistant is temporarily unavailable')
            ->assertDontSee('HTTP 500');
    }
}
