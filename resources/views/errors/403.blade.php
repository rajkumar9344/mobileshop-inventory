@extends('errors.base')

@section('page-title', 'Access Forbidden')
@section('page-class', 'error-page-403')

@section('error-icon')
    <i class="bi bi-shield-lock text-white" style="font-size: 32px;"></i>
@endsection

@section('error-code', '403')
@section('error-title', 'Access Forbidden')

@section('error-message')
    Sorry, you don't have the permission to visit this page. Please contact your administrator if you believe this is an error.
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
