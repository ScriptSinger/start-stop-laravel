<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Guide;
use App\Services\Catalog\BatteryGuideContent;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Подсказка к характеристике или её значению: в окне ($store.modal) по значку
 * «i» на карточке товара и отдельной страницей, если JavaScript недоступен.
 */
class AttributeHintController extends Controller
{
    public function attribute(Request $request, Attribute $attribute): View
    {
        return $this->hint($request, $attribute->name, $attribute->hint, $attribute->guide, $attribute->guide_anchor);
    }

    public function caseType(Request $request): View
    {
        [$hint, $anchor] = BatteryGuideContent::caseTypeHint();

        return $this->hint($request, 'Тип корпуса', $hint, Guide::query()->where('slug', BatteryGuideContent::SLUG)->first(), $anchor);
    }

    public function value(Request $request, AttributeValue $attributeValue): View
    {
        $attribute = $attributeValue->attribute;

        return $this->hint(
            $request,
            $attributeValue->value,
            $attributeValue->hint,
            $attribute->guide,
            $attributeValue->guide_anchor ?: $attribute->guide_anchor,
        );
    }

    private function hint(Request $request, string $title, ?string $hint, ?Guide $guide, ?string $anchor): View
    {
        abort_if(blank($hint), 404);

        SEOTools::setTitle($title);

        return view($request->ajax() ? 'hint.fragment' : 'hint.page', [
            'title' => $title,
            'hint' => $hint,
            'guideUrl' => $guide?->status ? route('guide.show', $guide).($anchor ? '#'.ltrim($anchor, '#') : '') : null,
        ]);
    }
}
