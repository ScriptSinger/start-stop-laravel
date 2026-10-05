<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DecodesLegacyText;
use App\Enums\CustomerRequestType;
use App\Models\CustomerRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;

#[Signature('import:legacy-requests')]
#[Description('Импорт заявок «Заказ звонка» и вопросов (oc_uni_request) из старого проекта')]
class ImportLegacyRequests extends LegacyImportCommand
{
    use DecodesLegacyText;

    private const TYPES = [
        'Заказ звонка' => CustomerRequestType::Callback,
        'Задать вопрос' => CustomerRequestType::Question,
        'Вопрос о товаре' => CustomerRequestType::ProductQuestion,
    ];

    protected function import(): int
    {
        $knownProductIds = DB::table('products')->pluck('id')->flip();
        $rows = DB::connection('legacy')->table('oc_uni_request')->orderBy('request_id')->get();

        DB::transaction(function () use ($rows, $knownProductIds): void {
            CustomerRequest::query()->whereKey($rows->pluck('request_id'))->delete();

            foreach ($rows as $row) {
                $createdAt = $row->date_added === '0000-00-00' ? now() : $row->date_added;

                $request = new CustomerRequest([
                    'type' => self::TYPES[trim($row->type)] ?? CustomerRequestType::Question,
                    // Неразрывные пробелы (&nbsp;) обычный trim не убирает.
                    'name' => preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', (string) $this->legacyText($row->name)) ?: 'Без имени',
                    'phone' => $this->legacyText(trim($row->phone)),
                    'email' => $this->legacyText(trim($row->mail)),
                    'product_id' => isset($knownProductIds[$row->product_id]) ? $row->product_id : null,
                    'comment' => $this->legacyText($row->comment),
                    'admin_comment' => $this->legacyText($row->admin_comment),
                    // История старого сайта — не новые заявки, на панели не висят.
                    'is_processed' => true,
                ]);
                $request->id = $row->request_id;
                $request->created_at = $createdAt;
                $request->updated_at = $createdAt;
                $request->save();
            }
        });

        $this->info("Импортировано заявок: {$rows->count()}");

        return self::SUCCESS;
    }
}
