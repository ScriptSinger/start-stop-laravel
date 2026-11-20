@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="breadcrumb-h1">
            <ul class="breadcrumb">
                <li><a href="{{ route('home') }}"><i class="fa fa-home"></i></a></li>
                <li>{{ $title }}</li>
            </ul>
            <h1>{{ $title }}</h1>
        </div>
        <div class="row">
            <div class="col-sm-8">
                @include('hint.fragment')
            </div>
        </div>
    </div>
@endsection
