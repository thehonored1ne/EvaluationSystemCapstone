@extends('errors.layout')

@section('title', '404 Page Not Found')
@section('code', '404 Error')
@section('heading', 'Page not found')

@section('illustration')
<img 
    src="{{ asset('error-page-illustration/404 error.webp') }}" 
    alt="404 Page Not Found Illustration" 
    class="max-h-56 sm:max-h-64 w-auto object-contain mx-auto transition-transform duration-300 hover:scale-105"
    loading="eager"
/>
@endsection

@section('message')
Sorry, we couldn't find the page you're looking for. It might have been moved or no longer exists.
@endsection
