@extends('errors.base')

@section('page-title', 'Server Error')
@section('page-class', 'error-page-500')

@section('error-icon')
    <i class="bi bi-exclamation-triangle-fill text-white" style="font-size: 32px;"></i>
@endsection

@section('error-code', '500')
@section('error-title', 'Server Error')

@section('error-message')
    Oops! Something went wrong on our end. Please try again later or contact support if the problem persists.
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
