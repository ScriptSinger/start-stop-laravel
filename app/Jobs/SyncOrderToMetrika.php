<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SyncOrderToMetrika implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $orderId)
    {
        $this->onConnection('database');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->orderId))->releaseAfter(5)->expireAfter(120)];
    }

    public function handle(): void
    {
        $order = Order::query()->find($this->orderId);
        $token = config('services.metrika.oauth_token');
        $counter = config('shop.metrika_counter_id');

        if (! $order || ! $order->metrika_client_id || blank($token) || blank($counter)) {
            return;
        }

        $status = match ($order->status) {
            OrderStatus::Completed => 'PAID',
            OrderStatus::Cancelled => 'CANCELLED',
            default => 'IN_PROGRESS',
        };

        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['id', 'create_date_time', 'client_ids', 'order_status', 'revenue', 'currency'], ',', '"', '');
        fputcsv($csv, [(string) $order->id, $order->created_at->copy()->setTimezone('Asia/Yekaterinburg')->format('d.m.Y H:i:s'), $order->metrika_client_id, $status, (string) $order->total, 'RUB'], ',', '"', '');
        rewind($csv);
        $data = stream_get_contents($csv);
        fclose($csv);

        $response = Http::timeout(20)
            ->withHeaders(['Authorization' => 'OAuth '.$token])
            ->attach('file', $data, 'orders.csv', ['Content-Type' => 'text/csv'])
            ->post('https://api-metrika.yandex.net/cdp/api/v1/counter/'.rawurlencode((string) $counter).'/data/simple_orders?merge_mode=SAVE&delimiter_type=COMMA');

        $response->throw();

        if ($response->json('uploading.api_validation_status') !== 'PASSED') {
            throw new RuntimeException('Metrika rejected the order upload.');
        }
    }
}
