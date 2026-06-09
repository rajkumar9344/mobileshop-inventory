@extends('errors.base')

@section('page-title', 'Page Not Found')
@section('page-class', 'error-page-404')

@section('error-icon')
    <i class="bi bi-search text-white" style="font-size: 32px;"></i>
@endsection

@section('error-code', '404')
@section('error-title', 'Page Not Found')

@section('error-message')
    Sorry, the page you are looking for could not be found. It might have been moved, deleted, or you entered the wrong URL.
@endsection

@section('error-actions')
    <a href="{{ url('/') }}" class="btn-error btn-primary-error">
        <i class="bi bi-house-door"></i>
        <span>Go Home</span>
    </a>
    <a href="javascript:history.back()" class="btn-error btn-secondary-error">
        <i class="bi bi-arrow-left"></i>
        <span>Go Back</span>
    </a>
@endsection
