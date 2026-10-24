<?php

namespace Tests\Feature;

use App\Support\AccessLogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class AccessLogResourceTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/access-log-test-'.uniqid();
        mkdir($this->dir);

        $lines = [
            ['2026-10-14T10:00:00+00:00', 'GET', '/', 200, false, false],
            ['2026-10-14T10:01:00+00:00', 'GET', '/wp-login.php', 404, true, true],
            ['2026-10-14T10:02:00+00:00', 'POST', '/admin/authenticate', 302, false, false],
        ];

        $content = '';
        foreach ($lines as $i => [$time, $method, $path, $status, $bot, $sus]) {
            $content .= json_encode([
                'message' => 'access',
                'context' => [
                    'request_id' => "req-$i", 'method' => $method, 'path' => $path, 'status' => $status,
                    'duration_ms' => 12, 'slow_request' => false, 'response_size' => 100, 'ip' => '198.51.100.'.$i,
                    'user_agent' => 'curl/8', 'referer' => $i === 1 ? 'https://yandex.ru/search/?text=remont' : null, 'is_bot' => $bot, 'is_suspicious' => $sus,
                ],
                'datetime' => $time,
            ])."\n\n"; // blank line on purpose: line numbers must still match
        }
        file_put_contents($this->dir.'/access-2026-10-14.log', $content);

        $this->app->instance(AccessLogReader::class, new AccessLogReader($this->dir));

        // Role 1 is the seeded Admin role created by the package migration.
        $role = MoonshineUserRole::query()->findOrFail(MoonshineUserRole::DEFAULT_ROLE_ID);
        $user = MoonshineUser::query()->create([
            'moonshine_user_role_id' => $role->getKey(),
            'email' => 'a@example.com',
            'name' => 'A',
            'password' => bcrypt('x'),
        ]);
        $this->actingAs($user, 'moonshine');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);

        parent::tearDown();
    }

    public function test_reader_lists_entries_and_finds_by_id_despite_blank_lines(): void
    {
        $reader = new AccessLogReader($this->dir);

        $this->assertCount(3, $reader->entries());
        $this->assertCount(1, $reader->entries(['only_suspicious' => '1']));

        foreach ($reader->entries() as $row) {
            $this->assertSame($row['path'], $reader->find($row['id'])['path']);
        }

        $this->assertSame(
            ['/wp-login.php', '/admin/authenticate'],
            $reader->entries(['exclude_ip' => '198.51.100.0, 203.0.113.9'])->pluck('path')->all(),
        );

        // Row 1 (/wp-login.php) is the only bot in the fixture.
        $this->assertSame(
            ['/', '/admin/authenticate'],
            $reader->entries(['no_bots' => '1'])->pluck('path')->all(),
        );
        $this->assertSame(['/wp-login.php'], $reader->entries(['only_bots' => '1'])->pluck('path')->all());

        $this->assertNull($reader->find('access-2026-10-14.log:1'));
        $this->assertNull($reader->find('../../etc/passwd:0'));
    }

    // One request per test: MoonShine keeps the resolved request and query
    // params on its singletons, so a second request in the same app instance
    // would still see the first one's filters.
    public function test_index_page_renders_all_rows(): void
    {
        $this->get('/admin/resource/access-log-resource/access-log-index-page')
            ->assertOk()
            ->assertSee('198.51.100.0', false)
            ->assertSee('198.51.100.1', false)
            ->assertSee('198.51.100.2', false)
            ->assertSee('https://yandex.ru/search/?text=remont', false)
            ->assertSee('data-column-selection="is_slow"', false)
            ->assertSee('data-column-selection="is_bot"', false)
            ->assertSee('data-column-selection="is_suspicious"', false)
            ->assertSee('data-widths="9,5,16,5,4,4,4,6,8,12,22,5"', false);
    }

    public function test_status_tab_narrows_the_rows(): void
    {
        $this->get('/admin/resource/access-log-resource/access-log-index-page?query-tag=404')
            ->assertOk()
            ->assertSee('198.51.100.1', false)
            ->assertDontSee('198.51.100.0', false)
            ->assertDontSee('198.51.100.2', false);
    }

    public function test_filter_form_narrows_the_rows(): void
    {
        $this->get('/admin/resource/access-log-resource/access-log-index-page?filter[path]=authenticate')
            ->assertOk()
            ->assertSee('198.51.100.2', false)
            ->assertDontSee('198.51.100.1', false);
    }

    public function test_detail_page_renders_the_entry(): void
    {
        $id = (new AccessLogReader($this->dir))->entries()->firstWhere('path', '/wp-login.php')['id'];

        $this->get('/admin/resource/access-log-resource/access-log-detail-page/'.$id)
            ->assertOk()
            ->assertSee('req-1', false);
    }

    public function test_hide_my_ip_removes_the_viewers_own_requests(): void
    {
        // The test client's address: its rows must disappear, others stay.
        $mine = '127.0.0.1';
        $log = $this->dir.'/access-2026-10-14.log';
        file_put_contents($log, json_encode([
            'datetime' => '2026-10-14T10:03:00+00:00',
            'context' => ['path' => '/mine', 'status' => 200, 'ip' => $mine],
        ])."\n", FILE_APPEND);

        $this->get('/admin/resource/access-log-resource/access-log-index-page?filter[hide_my_ip]=1')
            ->assertOk()
            ->assertSee('198.51.100.1', false)
            ->assertDontSee('/mine', false);
    }
}
