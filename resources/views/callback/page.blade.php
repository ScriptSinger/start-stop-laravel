@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>Заказать звонок</li>
            </ul>
            <h1>Заказать звонок</h1>
        </div>
        <div class="row">
            <div class="col-sm-6">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @else
                    @include('callback.form')
                @endif
            </div>
        </div>
    </div>
@endsection
