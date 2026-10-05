<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-sm-6">
                <div class="footer__column-heading">{{ config('shop.name') }}</div>
                <p>
                    <a href="tel:{{ preg_replace('/\D/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a>
                </p>
            </div>
            <div class="col-sm-6 text-right">
                <p>&copy; {{ date('Y') }} {{ config('shop.name') }}</p>
            </div>
        </div>
    </div>
</footer>
