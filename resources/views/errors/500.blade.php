@extends('errors.layout')

@section('title', '500 Server Error')
@section('code', '500 Error')
@section('heading', 'Internal server error')

@section('illustration')
<img 
    src="{{ asset('error-page-illustration/500 error.webp') }}" 
    alt="500 Internal Server Error Illustration" 
    class="max-h-56 sm:max-h-64 w-auto object-contain mx-auto transition-transform duration-300 hover:scale-105"
    loading="eager"
/>
@endsection

@section('message')
Something went wrong on our servers while processing this request. Please try again in a few moments.
@endsection
