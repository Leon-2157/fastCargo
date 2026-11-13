<?php

namespace Tests\Feature;

use App\Livewire\AiChatWidget;
use App\Livewire\AiInsightCard;
use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\AI\Providers\CustomAiProvider;
use App\Modules\Shared\AI\Providers\GeminiProvider;
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

        // Switching back to custom takes effect immediately without restart
        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/settings/ai/switch', [
                'ai_provider' => 'custom',
            ])
            ->assertRedirect();

        $this->assertInstanceOf(CustomAiProvider::class, AiManager::provider());
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
        $this->assertSame('custom', AiManager::activeProviderKey());
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
        $page->assertSee('secret-token-should-never-render');

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

    public function test_ai_manager_channel_helpers_resolve_providers_and_disabled_state(): void
    {
        AdmSystemSetting::updateOrCreate(['key' => 'ai_provider'], ['value' => 'custom', 'type' => 'string']);
        AiManager::reset();

        // Default: follows active provider (custom)
        $this->assertTrue(AiManager::isWaAiEnabled());
        $this->assertTrue(AiManager::isMailAiEnabled());
        $this->assertInstanceOf(CustomAiProvider::class, AiManager::forWhatsApp());
        $this->assertInstanceOf(CustomAiProvider::class, AiManager::forEmail());

        // Explicit Gemini
        AdmSystemSetting::updateOrCreate(['key' => 'wa_ai_provider'], ['value' => 'gemini', 'type' => 'string']);
        AiManager::reset();
        $this->assertTrue(AiManager::isWaAiEnabled());
        $this->assertInstanceOf(GeminiProvider::class, AiManager::forWhatsApp());

        // Explicit Custom
        AdmSystemSetting::updateOrCreate(['key' => 'mail_ai_provider'], ['value' => 'custom', 'type' => 'string']);
        AiManager::reset();
        $this->assertTrue(AiManager::isMailAiEnabled());
        $this->assertInstanceOf(CustomAiProvider::class, AiManager::forEmail());

        // Disabled
        AdmSystemSetting::updateOrCreate(['key' => 'wa_ai_provider'], ['value' => 'disabled', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'mail_ai_provider'], ['value' => 'disabled', 'type' => 'string']);
        AiManager::reset();

        $this->assertFalse(AiManager::isWaAiEnabled());
        $this->assertFalse(AiManager::isMailAiEnabled());
        $this->assertNull(AiManager::forWhatsApp());
        $this->assertNull(AiManager::forEmail());

        // Case insensitivity & whitespace normalization
        AdmSystemSetting::updateOrCreate(['key' => 'wa_ai_provider'], ['value' => ' DISABLED ', 'type' => 'string']);
        AdmSystemSetting::updateOrCreate(['key' => 'mail_ai_provider'], ['value' => ' Gemini ', 'type' => 'string']);
        AiManager::reset();

        $this->assertFalse(AiManager::isWaAiEnabled());
        $this->assertNull(AiManager::forWhatsApp());
        $this->assertTrue(AiManager::isMailAiEnabled());
        $this->assertInstanceOf(GeminiProvider::class, AiManager::forEmail());
    }

    public function test_admin_settings_updates_wa_and_mail_ai_providers(): void
    {
        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();

        $update = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->put('http://admin.fastcargo.local/settings', [
                'wa_ai_provider' => 'gemini',
                'mail_ai_provider' => 'disabled',
            ]);

        $update->assertRedirect();
        $update->assertSessionHas('success');

        $this->assertSame('gemini', AdmSystemSetting::where('key', 'wa_ai_provider')->value('value'));
        $this->assertSame('disabled', AdmSystemSetting::where('key', 'mail_ai_provider')->value('value'));

        $page = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/settings');

        $page->assertStatus(200);
        $page->assertSee('name="wa_ai_provider"', false);
        $page->assertSee('name="mail_ai_provider"', false);
        $page->assertSee('id="formWaAiSettings"', false);

        // Submitting only wa_ai_provider preserves other settings
        $waOnly = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->put('http://admin.fastcargo.local/settings', [
                'wa_ai_provider' => 'default',
            ]);
        $waOnly->assertRedirect();
        $this->assertSame('default', AdmSystemSetting::where('key', 'wa_ai_provider')->value('value'));
        $this->assertSame('disabled', AdmSystemSetting::where('key', 'mail_ai_provider')->value('value'));

        // Validation rejects invalid provider values
        $invalid = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->put('http://admin.fastcargo.local/settings', [
                'wa_ai_provider' => 'invalid-provider',
            ]);

        $invalid->assertSessionHasErrors('wa_ai_provider');
    }
}
