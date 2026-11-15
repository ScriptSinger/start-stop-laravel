@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Подтверждение почты</h1>
        <p>Перейдите по ссылке в письме, чтобы подтвердить адрес электронной почты.</p>
        @if (session('status') === 'verification-link-sent')
            <p>Письмо отправлено. Проверьте почту и папку «Спам».</p>
        @endif
        <form method="post" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit">Отправить письмо повторно</button>
        </form>
    </div>
@endsection
