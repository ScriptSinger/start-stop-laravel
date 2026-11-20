<?php

namespace App\Models;

use Database\Factories\GuideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guide extends Model
{
    /** @use HasFactory<GuideFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'meta_title',
        'meta_description',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Разделы гайда для оглавления: заголовки <h2 id="…"> из текста.
     *
     * @return list<array{id: string, title: string}>
     */
    public function sections(): array
    {
        preg_match_all('~<h2[^>]*\sid="([^"]+)"[^>]*>(.*?)</h2>~su', (string) $this->body, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match): array => [
            'id' => $match[1],
            'title' => trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5)),
        ], $matches);
    }

    /**
     * Сколько минут читать гайд (около 180 слов в минуту).
     */
    public function readingMinutes(): int
    {
        $words = str_word_count(strip_tags((string) $this->body), 0, 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ0123456789');

        return max(1, (int) ceil($words / 180));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
