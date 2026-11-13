<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use App\Services\Catalog\ProductMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MoonShine\Laravel\Applies\Fields\FileModelApply;
use MoonShine\UI\Fields\Image;
use Throwable;

class ProductPhoto extends Image
{
    protected function booted(): void
    {
        parent::booted();

        $this->disk(ProductMedia::uploadDisk())
            ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
            ->disableDeleteFiles()
            ->customName(function (UploadedFile $file): string {
                Validator::make(['photo' => $file], ['photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240']])->validate();

                return ProductMedia::uploadDirectory().'/'.$file->hashName();
            })
            ->onApply(function (mixed $item, mixed $value, ProductPhoto $field): mixed {
                try {
                    return (new FileModelApply)->apply($field)($item);
                } catch (ValidationException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    Log::warning('Product image upload failed', ['exception_type' => $exception::class]);

                    throw ValidationException::withMessages([
                        $field->getRequestNameDot() => 'Не удалось сохранить изображение. Попробуйте загрузить его ещё раз.',
                    ]);
                }
            });
    }

    public function getStorageUrl(string $value): string
    {
        return ProductMedia::url($value);
    }
}
