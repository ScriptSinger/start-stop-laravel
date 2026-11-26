<?php

namespace App\MoonShine\Fields;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use MoonShine\Laravel\Applies\Fields\FileModelApply;
use MoonShine\UI\Fields\Image;

class ReviewPhoto extends Image
{
    protected function booted(): void
    {
        parent::booted();

        $this->disk('media')
            ->dir('media/reviews')
            ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
            ->disableDeleteFiles()
            ->customName(function (UploadedFile $file): string {
                Validator::make(['photo' => $file], ['photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240']])->validate();

                return $file->hashName();
            })
            ->onApply(function (mixed $item, mixed $value, ReviewPhoto $field): mixed {
                $column = $field->getColumn();
                $previous = data_get($item, $column);
                $result = (new FileModelApply)->apply($field)($item);
                if ($column === 'photo_paths' && $previous !== data_get($result, $column)) {
                    data_set($result, 'photo_preview_paths', null);
                }
                if (filled($previous) && blank(data_get($result, $column))) {
                    data_set($result, $column === 'avatar_path' ? 'avatar_url' : 'photo_urls', null);
                }

                return $result;
            });
    }
}
