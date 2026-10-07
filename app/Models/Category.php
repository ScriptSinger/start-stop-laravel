<?php

namespace App\Models;

use App\Models\Concerns\HasHtmlDescription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Category extends Model
{
    use HasHtmlDescription;

    protected $fillable = [
        'parent_id',
        'name',
        'heading',
        'meta_title',
        'meta_description',
        'slug',
        'description',
        'image',
        'icon',
        'home_wall_sort',
        'show_on_parent_wall',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'show_on_parent_wall' => 'boolean',
    ];

    /**
     * Иконка задана классом Font Awesome ("fas fa-tools"), а не картинкой.
     */
    public function hasFontIcon(): bool
    {
        return $this->icon !== null && preg_match('/^fa[srlbd]?\s/', $this->icon) === 1;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Не attributes(): это имя занято свойством Eloquent-модели.
     */
    public function productAttributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class);
    }

    /**
     * Производители-ссылки на плитке раздела в «Популярных категориях».
     */
    public function wallManufacturers(): BelongsToMany
    {
        return $this->belongsToMany(Manufacturer::class, 'category_wall_manufacturer')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * Сама категория и все её включённые подкатегории (любой глубины):
     * раздел показывает товары подразделов — товар привязывают к самой
     * точной категории, а не ставят ещё и галочку на раздел.
     *
     * @return list<int>
     */
    public function treeIds(): array
    {
        $children = once(fn (): Collection => self::query()->where('status', true)->get(['id', 'parent_id'])->groupBy('parent_id'));

        $ids = [$this->id];

        for ($i = 0; $i < count($ids); $i++) {
            foreach ($children->get($ids[$i], collect()) as $child) {
                $ids[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Название с разделами: «Автомасла › Трансмиссионное масло» — для
     * списков выбора в админке, где одно название без раздела неясно.
     */
    public function pathName(): string
    {
        $all = once(fn (): Collection => self::query()->get(['id', 'parent_id', 'name'])->keyBy('id'));

        $names = [$this->name];
        $parentId = $this->parent_id;

        while ($parentId !== null && ($parent = $all->get($parentId)) !== null && count($names) < 10) {
            array_unshift($names, $parent->name);
            $parentId = $parent->parent_id;
        }

        return implode(' › ', $names);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
