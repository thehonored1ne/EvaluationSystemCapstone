@extends('errors.layout')

@section('title', '503 Service Unavailable')
@section('code', '503 Maintenance')
@section('heading', 'Under maintenance')

@section('illustration')
<img 
    src="{{ asset('error-page-illustration/503 error.webp') }}" 
    alt="503 System Under Maintenance Illustration" 
    class="max-h-56 sm:max-h-64 w-auto object-contain mx-auto transition-transform duration-300 hover:scale-105"
    loading="eager"
/>
@endsection

@section('message')
The evaluation portal is currently undergoing scheduled maintenance. We'll be back shortly.
@endsection
