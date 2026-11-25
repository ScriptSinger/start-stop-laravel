<?php

namespace App\View\Composers;

use App\Models\StoreReview;
use Illuminate\View\View;

class StoreReviewComposer
{
    public function compose(View $view): void
    {
        $view->with('storeReviews', StoreReview::query()->active()->ordered()->limit(30)->get());
    }
}
