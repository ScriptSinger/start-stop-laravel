<?php

namespace Tests\Feature;

use App\Support\AuthLogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

class AuthLogResourceTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/auth-log-test-'.uniqid();
        mkdir($this->dir);

        $entries = [
            ['login_failed', ['login' => 'probe@example.com', 'ip' => '198.51.100.7', 'user_exists' => false]],
            ['login', ['user_id' => 1, 'ip' => '198.51.100.8']],
            ['logout', ['user_id' => 1, 'ip' => '198.51.100.9']],
        ];

        $content = '';
        foreach ($entries as $i => [$message, $context]) {
            $content .= json_encode([
                'message' => $message,
                'context' => $context + ['request_id' => "req-$i", 'user_agent' => 'curl/8'],
                'datetime' => '2026-10-15T10:0'.$i.':00+00:00',
            ])."\n\n"; // blank line on purpose: line numbers must still match
        }
        file_put_contents($this->dir.'/auth-2026-10-15.log', $content);

        $this->app->instance(AuthLogReader::class, new AuthLogReader($this->dir));

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => 1,
            'email' => 'a@example.com',
            'name' => 'A',
            'password' => bcrypt('x'),
        ]), 'moonshine');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);

        parent::tearDown();
    }

    public function test_reader_parses_events_and_counts_failed_logins(): void
    {
        $reader = new AuthLogReader($this->dir);

        $this->assertSame(['login_failed', 'login', 'logout'], $reader->entries()->pluck('event')->all());
        $this->assertSame(1, $reader->failedLoginsSince(strtotime('2026-10-15T00:00:00+00:00')));
        $this->assertSame(0, $reader->failedLoginsSince(strtotime('2026-10-16T00:00:00+00:00')));

        foreach ($reader->entries() as $row) {
            $this->assertSame($row['event'], $reader->find($row['id'])['event']);
        }
    }

    public function test_index_page_lists_every_event(): void
    {
        $this->get('/admin/resource/auth-log-resource/auth-log-index-page')
            ->assertOk()
            ->assertSee('probe@example.com', false)
            ->assertSee('198.51.100.8', false)
            ->assertSee('198.51.100.9', false)
            ->assertSee('data-widths="15,15,22,13,30,5"', false);
    }

    public function test_failed_tab_shows_only_failed_attempts(): void
    {
        $this->get('/admin/resource/auth-log-resource/auth-log-index-page?query-tag=failed')
            ->assertOk()
            ->assertSee('198.51.100.7', false)
            ->assertDontSee('198.51.100.8', false)
            ->assertDontSee('198.51.100.9', false);
    }

    public function test_detail_page_shows_the_entry(): void
    {
        $id = (new AuthLogReader($this->dir))->entries()->firstWhere('event', 'login_failed')['id'];

        $this->get('/admin/resource/auth-log-resource/auth-log-detail-page/'.$id)
            ->assertOk()
            ->assertSee('req-0', false)
            ->assertSee('probe@example.com', false);
    }
}
