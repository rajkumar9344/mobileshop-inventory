@extends('errors.base')

@section('page-title', 'Session Expired')
@section('page-class', 'error-page-419')

@section('error-icon')
    <i class="bi bi-clock-history text-white" style="font-size: 32px;"></i>
@endsection

@section('error-code', '419')
@section('error-title', 'Session Expired')

@section('error-message')
    Your session has expired due to inactivity. Please refresh the page and try again.
@endsection

@section('error-actions')
    <a href="javascript:location.reload()" class="btn-error btn-primary-error">
        <i class="bi bi-arrow-clockwise"></i>
        <span>Refresh Page</span>
    </a>
    <a href="{{ url('/') }}" class="btn-error btn-secondary-error">
        <i class="bi bi-house-door"></i>
        <span>Go Home</span>
    </a>
@endsection
