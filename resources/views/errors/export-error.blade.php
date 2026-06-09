@extends('errors.base')

@section('page-title', $title ?? 'Export Error')
@section('page-class', 'error-page-500')

@section('error-icon')
    <i class="bi bi-file-earmark-x-fill text-white" style="font-size: 32px;"></i>
@endsection

@section('error-code', '')
@section('error-title', $title ?? 'Export Error')

@section('error-message')
    {{ $message ?? 'An error occurred while generating the export.' }}
    @if(!empty($suggestion))
        <br><br><strong>{{ $suggestion }}</strong>
    @endif
@endsection

@section('error-actions')
    <a href="#" onclick="window.history.length > 1 ? history.back() : window.close(); return false;" class="btn-error btn-primary-error">
        <i class="bi bi-arrow-left"></i>
        <span>Go Back</span>
    </a>
    <a href="{{ url('/') }}" class="btn-error btn-secondary-error">
        <i class="bi bi-house-door"></i>
        <span>Go Home</span>
    </a>
@endsection
