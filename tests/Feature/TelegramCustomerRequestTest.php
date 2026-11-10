<?php

namespace Tests\Feature;

use App\Enums\CustomerRequestType;
use App\Jobs\SendCustomerRequestToTelegram;
use App\Jobs\UpdateCustomerRequestTelegramMessage;
use App\Models\CustomerRequest;
use App\Models\Product;
use App\Services\Telegram\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class TelegramCustomerRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '-1001234',
            'services.telegram.topic_id' => null,
            'services.telegram.proxy' => null,
            'services.telegram.webhook_secret' => 'test-webhook-secret',
        ]);
    }

    public function test_site_requests_queue_telegram_notifications(): void
    {
        Queue::fake([SendCustomerRequestToTelegram::class]);
        $product = Product::query()->create(['name' => 'Аккумулятор', 'slug' => 'battery', 'price' => 5000, 'status' => true]);

        $this->postJson(route('callback.store'), ['name' => 'Анна', 'phone' => '89870000000'])->assertOk();
        $this->postJson(route('product-question.store', $product), [
            'name' => 'Пётр', 'phone' => '89870000001', 'comment' => 'Подойдёт?',
        ])->assertOk();

        $this->assertSame(2, CustomerRequest::query()->count());
        Queue::assertPushed(SendCustomerRequestToTelegram::class, 2);
    }

    public function test_unconfigured_bot_does_not_queue_notification(): void
    {
        Queue::fake([SendCustomerRequestToTelegram::class]);
        config(['services.telegram.bot_token' => null]);

        $this->postJson(route('callback.store'), ['name' => 'Анна', 'phone' => '89870000000'])->assertOk();

        Queue::assertNotPushed(SendCustomerRequestToTelegram::class);
    }

    public function test_admin_status_change_queues_telegram_message_update(): void
    {
        Queue::fake([UpdateCustomerRequestTelegramMessage::class]);
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000',
        ]);
        $customerRequest->forceFill(['telegram_message_id' => 77])->save();
        $admin = MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com', 'name' => 'Admin', 'password' => bcrypt('secret'),
        ]);

        $this->actingAs($admin, 'moonshine')->put("/admin/resource/customer-request-resource/crud/{$customerRequest->id}", [
            'name' => 'Анна', 'phone' => '89870000000', 'type' => CustomerRequestType::Callback->value, 'is_processed' => '1',
        ])->assertRedirect();

        $this->assertTrue($customerRequest->fresh()->is_processed);
        Queue::assertPushed(UpdateCustomerRequestTelegramMessage::class, fn ($job): bool => $job->customerRequestId === $customerRequest->id && $job->afterCommit === true);
    }

    public function test_status_changes_without_a_telegram_message_do_not_queue_an_update(): void
    {
        Queue::fake([UpdateCustomerRequestTelegramMessage::class]);
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000',
        ]);

        $customerRequest->update(['is_processed' => true]);

        Queue::assertNotPushed(UpdateCustomerRequestTelegramMessage::class);
    }

    public function test_admin_list_switch_queues_telegram_message_update(): void
    {
        Queue::fake([UpdateCustomerRequestTelegramMessage::class]);
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000',
        ]);
        $customerRequest->forceFill(['telegram_message_id' => 77])->save();
        $admin = MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com', 'name' => 'Admin', 'password' => bcrypt('secret'),
        ]);

        $this->actingAs($admin, 'moonshine')->put("/admin/update-field/column/customer-request-resource/{$customerRequest->id}", [
            'field' => 'is_processed', 'value' => '1',
        ])->assertNoContent();

        $this->assertTrue($customerRequest->fresh()->is_processed);
        Queue::assertPushed(UpdateCustomerRequestTelegramMessage::class, fn ($job): bool => $job->customerRequestId === $customerRequest->id);
    }

    public function test_update_job_sends_latest_status_to_existing_telegram_message(): void
    {
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000', 'is_processed' => true,
        ]);
        $customerRequest->forceFill(['telegram_message_id' => 77])->save();
        Http::fake(['https://api.telegram.org/bottest-token/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);
        Http::preventStrayRequests();

        (new UpdateCustomerRequestTelegramMessage($customerRequest->id))->handle(app(TelegramBot::class));

        Http::assertSent(fn ($request): bool => $request['message_id'] === 77
            && str_contains($request['text'], 'Статус: Обработана')
            && $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'request:'.$customerRequest->id.':reopen');
    }

    public function test_job_sends_one_group_message_with_request_details_and_button(): void
    {
        $product = Product::query()->create(['name' => 'Аккумулятор', 'slug' => 'battery', 'price' => 5000, 'status' => true]);
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::ProductQuestion,
            'name' => '<Анна>',
            'phone' => '89870000000',
            'product_id' => $product->id,
            'comment' => 'Подойдёт на Rio?',
        ]);
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response([
            'ok' => true, 'result' => ['message_id' => 77],
        ])]);
        Http::preventStrayRequests();

        $job = new SendCustomerRequestToTelegram($customerRequest->id);
        $job->handle(app(TelegramBot::class));
        $job->handle(app(TelegramBot::class));

        $this->assertSame(77, $customerRequest->fresh()->telegram_message_id);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['chat_id'] === '-1001234'
            && ! isset($request['message_thread_id'])
            && str_contains($request['text'], 'Вопрос о товаре')
            && str_contains($request['text'], '<Анна>')
            && str_contains($request['text'], 'Аккумулятор')
            && $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'request:'.$customerRequest->id.':done'
            && ! isset($request['parse_mode']));
    }

    public function test_job_sends_the_request_to_the_configured_forum_topic(): void
    {
        config(['services.telegram.topic_id' => '123']);
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000',
        ]);
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response([
            'ok' => true, 'result' => ['message_id' => 77],
        ])]);
        Http::preventStrayRequests();

        (new SendCustomerRequestToTelegram($customerRequest->id))->handle(app(TelegramBot::class));

        Http::assertSent(fn ($request): bool => $request['message_thread_id'] === 123);
        $this->assertSame(77, $customerRequest->fresh()->telegram_message_id);
    }

    public function test_invalid_forum_topic_does_not_send_a_message(): void
    {
        config(['services.telegram.topic_id' => 'invalid']);
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000',
        ]);
        Http::fake();
        Http::preventStrayRequests();

        try {
            (new SendCustomerRequestToTelegram($customerRequest->id))->handle(app(TelegramBot::class));
            $this->fail('Invalid topic ID must prevent sending.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Invalid Telegram topic ID.', $exception->getMessage());
        }

        Http::assertNothingSent();
        $this->assertNull($customerRequest->fresh()->telegram_message_id);
    }

    public function test_telegram_requests_use_the_configured_proxy(): void
    {
        config(['services.telegram.proxy' => 'http://proxy.invalid:8080']);
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000',
        ]);
        $proxy = null;
        Http::fake(function ($request, array $options) use (&$proxy) {
            $proxy = $options['proxy'] ?? null;

            return Http::response(['ok' => true, 'result' => ['message_id' => 77]]);
        });

        (new SendCustomerRequestToTelegram($customerRequest->id))->handle(app(TelegramBot::class));

        $this->assertSame('http://proxy.invalid:8080', $proxy);
        $this->assertSame(77, $customerRequest->fresh()->telegram_message_id);
    }

    public function test_failed_telegram_send_leaves_request_for_queue_retry(): void
    {
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000',
        ]);
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => false], 503)]);
        Http::preventStrayRequests();

        try {
            (new SendCustomerRequestToTelegram($customerRequest->id))->handle(app(TelegramBot::class));
            $this->fail('Telegram failure must be retried by the queue.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('sendMessage failed', $exception->getMessage());
        }

        $this->assertNull($customerRequest->fresh()->telegram_message_id);
    }

    public function test_known_telegram_error_is_reported_without_exposing_unknown_response_content(): void
    {
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000',
        ]);
        Http::fakeSequence('https://api.telegram.org/bottest-token/sendMessage')
            ->push(['ok' => false, 'description' => 'Bad Request: message thread not found'], 400)
            ->push(['ok' => false, 'description' => 'private detail'], 400);

        foreach (['Bad Request: message thread not found', 'private detail'] as $index => $description) {
            try {
                (new SendCustomerRequestToTelegram($customerRequest->id))->handle(app(TelegramBot::class));
                $this->fail('Telegram failure must be reported.');
            } catch (\RuntimeException $exception) {
                if ($index === 0) {
                    $this->assertStringContainsString($description, $exception->getMessage());
                } else {
                    $this->assertStringNotContainsString($description, $exception->getMessage());
                }
            }
        }
    }

    public function test_webhook_requires_secret_before_changing_request(): void
    {
        $customerRequest = $this->customerRequest();
        Http::fake(['https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true])]);

        $this->postJson(route('telegram.webhook'), $this->callbackPayload($customerRequest), [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong',
        ])->assertForbidden();

        $this->assertFalse($customerRequest->fresh()->is_processed);
        Http::assertNothingSent();
    }

    public function test_webhook_rejects_another_group_or_message(): void
    {
        $customerRequest = $this->customerRequest();
        Http::fake(['https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true])]);
        $wrongGroup = $this->callbackPayload($customerRequest);
        $wrongGroup['callback_query']['message']['chat']['id'] = -1009999;

        $this->postJson(route('telegram.webhook'), $wrongGroup, $this->secretHeader())->assertOk();

        $wrongMessage = $this->callbackPayload($customerRequest);
        $wrongMessage['callback_query']['message']['message_id'] = 999;
        $this->postJson(route('telegram.webhook'), $wrongMessage, $this->secretHeader())->assertOk();

        $this->assertFalse($customerRequest->fresh()->is_processed);
        Http::assertSentCount(1);
    }

    public function test_only_current_group_member_can_process_request(): void
    {
        $customerRequest = $this->customerRequest();
        Http::fake([
            'https://api.telegram.org/bottest-token/getChatMember' => Http::response(['ok' => true, 'result' => ['status' => 'left']]),
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
        ]);
        Http::preventStrayRequests();

        $this->postJson(route('telegram.webhook'), $this->callbackPayload($customerRequest), $this->secretHeader())->assertOk();

        $this->assertFalse($customerRequest->fresh()->is_processed);
        Http::assertSentCount(2);
    }

    public function test_group_member_can_process_and_reopen_request(): void
    {
        $customerRequest = $this->customerRequest();
        $this->fakeMemberAndEdits();

        $this->postJson(route('telegram.webhook'), $this->callbackPayload($customerRequest), $this->secretHeader())->assertOk();
        $this->assertTrue($customerRequest->fresh()->is_processed);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/editMessageText')
            && str_contains($request['text'], 'Статус: Обработана')
            && $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'request:'.$customerRequest->id.':reopen');

        $this->postJson(route('telegram.webhook'), $this->callbackPayload($customerRequest, 'reopen'), $this->secretHeader())->assertOk();
        $this->assertFalse($customerRequest->fresh()->is_processed);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/editMessageText')
            && str_contains($request['text'], 'Статус: Новая')
            && $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'request:'.$customerRequest->id.':done');
    }

    public function test_replayed_button_does_not_toggle_status_back(): void
    {
        $customerRequest = $this->customerRequest();
        $this->fakeMemberAndEdits();

        $this->postJson(route('telegram.webhook'), $this->callbackPayload($customerRequest), $this->secretHeader())->assertOk();
        $this->postJson(route('telegram.webhook'), $this->callbackPayload($customerRequest), $this->secretHeader())->assertOk();

        $this->assertTrue($customerRequest->fresh()->is_processed);
    }

    public function test_webhook_command_registers_callback_updates_and_secret(): void
    {
        Http::fake(['https://api.telegram.org/bottest-token/setWebhook' => Http::response(['ok' => true, 'result' => true])]);
        Http::preventStrayRequests();

        $this->artisan('telegram:set-webhook', ['url' => 'https://example.ngrok-free.app/api/telegram/webhook'])
            ->assertSuccessful();

        Http::assertSent(fn ($request): bool => $request['url'] === 'https://example.ngrok-free.app/api/telegram/webhook'
            && $request['secret_token'] === 'test-webhook-secret'
            && $request['allowed_updates'] === ['callback_query']);
    }

    public function test_webhook_command_accepts_the_start_stop_relay_path(): void
    {
        Http::fake(['https://api.telegram.org/bottest-token/setWebhook' => Http::response(['ok' => true, 'result' => true])]);

        $this->artisan('telegram:set-webhook', ['url' => 'https://relay.example:8443/start-stop/api/telegram/webhook'])
            ->assertSuccessful();

        Http::assertSent(fn ($request): bool => $request['url'] === 'https://relay.example:8443/start-stop/api/telegram/webhook');
    }

    public function test_webhook_command_rejects_insecure_url(): void
    {
        Http::fake();

        $this->artisan('telegram:set-webhook', ['url' => 'http://example.test/api/telegram/webhook'])
            ->assertFailed();

        Http::assertNothingSent();
    }

    private function customerRequest(): CustomerRequest
    {
        $customerRequest = CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback, 'name' => 'Анна', 'phone' => '89870000000',
        ]);
        $customerRequest->forceFill(['telegram_message_id' => 77])->save();

        return $customerRequest;
    }

    /**
     * @return array<string, mixed>
     */
    private function callbackPayload(CustomerRequest $customerRequest, string $action = 'done'): array
    {
        return ['callback_query' => [
            'id' => 'callback-1',
            'data' => 'request:'.$customerRequest->id.':'.$action,
            'from' => ['id' => 42, 'is_bot' => false],
            'message' => ['message_id' => 77, 'chat' => ['id' => -1001234, 'type' => 'supergroup']],
        ]];
    }

    /**
     * @return array<string, string>
     */
    private function secretHeader(): array
    {
        return ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'];
    }

    private function fakeMemberAndEdits(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-token/getChatMember' => Http::response(['ok' => true, 'result' => ['status' => 'member']]),
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
            'https://api.telegram.org/bottest-token/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 77]]),
        ]);
        Http::preventStrayRequests();
    }
}
