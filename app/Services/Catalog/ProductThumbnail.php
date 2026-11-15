<?php

namespace App\Services\Catalog;

use RuntimeException;

class ProductThumbnail
{
    public const MAX_BYTES = 10485760;

    public const MAX_PIXELS = 16000000;

    public function make(string $contents, int $maxWidth = 440): string
    {
        if ($maxWidth < 1 || strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException('Изображение превышает допустимые ограничения.');
        }

        $info = @getimagesizefromstring($contents);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Изображение повреждено или имеет неподдерживаемый формат.');
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new RuntimeException('Не удалось прочитать изображение.');
        }

        $width = min($maxWidth, $info[0]);
        $height = max(1, (int) round($info[1] * $width / $info[0]));
        $thumbnail = imagecreatetruecolor($width, $height);
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagefill($thumbnail, 0, 0, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);

        ob_start();
        try {
            if (! imagewebp($thumbnail, null, 80)) {
                throw new RuntimeException('Не удалось создать миниатюру.');
            }

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($thumbnail);
            imagedestroy($source);
        }
    }
}
