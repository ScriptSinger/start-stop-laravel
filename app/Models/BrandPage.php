<?php

namespace App\Models;

use App\Models\Concerns\HasHtmlDescription;
use Database\Factories\BrandPageFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Свои тексты страницы «раздел + производитель» (/category/akkumulyatori/titan).
 */
#[UseFactory(BrandPageFactory::class)]
class BrandPage extends Model
{
    use HasFactory;
    use HasHtmlDescription;

    protected $fillable = [
        'category_id',
        'manufacturer_id',
        'heading',
        'meta_title',
        'meta_description',
        'description',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }
}
