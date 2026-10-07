<?php

namespace Tests\Feature;

use App\Models\BatteryFitment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class BatteryFitmentImportExportTest extends TestCase
{
    use RefreshDatabase;

    private BatteryFitment $vesta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(MoonshineUser::query()->create([
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
        ]), 'moonshine');

        $this->vesta = BatteryFitment::query()->create(['brand' => 'Lada', 'model' => 'Vesta', 'capacity' => '60 Ач', 'polarity' => 'Обратная', 'terminals' => 'standard']);
        BatteryFitment::query()->create(['brand' => 'Kia', 'model' => 'Rio', 'capacity' => '45 Ач', 'terminals' => 'thin']);
    }

    public function test_export_respects_filters_and_shows_terminal_labels(): void
    {
        $response = $this->get('/admin/resource/battery-fitment-resource/handler/export-handler?'.http_build_query(['filter' => ['model' => 'Vest']]));

        $response->assertOk();
        $rows = (new FastExcel)->import($response->baseResponse->getFile()->getPathname());

        $this->assertCount(1, $rows);
        $this->assertSame('Vesta', $rows[0]['Модель']);
        $this->assertSame('Стандартные', $rows[0]['Клеммы']);
        $this->assertArrayHasKey('Габариты', $rows[0]);
    }

    public function test_import_updates_rows_and_adds_new_cars(): void
    {
        $this->import([
            ['ID' => $this->vesta->id, 'Марка' => 'Lada', 'Модель' => 'Vesta', 'Ёмкость' => '60 Ач, 62 Ач', 'Полярность' => 'Обратная', 'Клеммы' => 'Узкие (азиатские)'],
            ['ID' => '', 'Марка' => 'Lada', 'Модель' => 'Granta', 'Ёмкость' => '55 Ач', 'Полярность' => 'Прямая', 'Клеммы' => ''],
        ])->assertRedirect();

        $this->vesta->refresh();
        $this->assertSame(['60 Ач, 62 Ач', 'thin'], [$this->vesta->capacity, $this->vesta->terminals]);
        $granta = BatteryFitment::query()->where('model', 'Granta')->sole();
        $this->assertSame(['55 Ач', 'Прямая', null], [$granta->capacity, $granta->polarity, $granta->terminals]);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidRows(): array
    {
        return [
            'чужой ID' => [['ID' => 99999, 'Марка' => 'Lada', 'Модель' => 'Niva']],
            'без модели' => [['ID' => '', 'Марка' => 'Lada', 'Модель' => '']],
            'неизвестные клеммы' => [['ID' => '', 'Марка' => 'Lada', 'Модель' => 'Niva', 'Клеммы' => 'Квадратные']],
        ];
    }

    #[DataProvider('invalidRows')]
    public function test_file_with_invalid_row_changes_nothing(array $row): void
    {
        $this->import([
            ['ID' => $this->vesta->id, 'Марка' => 'Lada', 'Модель' => 'Vesta', 'Ёмкость' => '1 Ач', 'Клеммы' => 'Стандартные'],
            ['Ёмкость' => '', 'Клеммы' => '', ...$row],
        ])->assertRedirect()->assertSessionHas('toast');

        $this->assertSame('60 Ач', $this->vesta->refresh()->capacity);
        $this->assertSame(2, BatteryFitment::query()->count());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function import(array $rows): TestResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new FastExcel(collect($rows)))->export($path);

        return $this->post('/admin/resource/battery-fitment-resource/handler/safe-import-handler', [
            'import_file' => new UploadedFile($path, 'podbor.xlsx', null, null, true),
        ]);
    }
}
