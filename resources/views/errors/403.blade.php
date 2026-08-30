@extends('errors.layout')

@section('title', '403 Access Forbidden')
@section('code', '403 Forbidden')
@section('heading', 'Access restricted')

@section('illustration')
<img 
    src="{{ asset('error-page-illustration/403 error.webp') }}" 
    alt="403 Access Restricted Illustration" 
    class="max-h-56 sm:max-h-64 w-auto object-contain mx-auto transition-transform duration-300 hover:scale-105"
    loading="eager"
/>
@endsection

@section('message')
{{ $exception->getMessage() ?: 'You do not have the required permissions to view this page or resource.' }}
@endsection
