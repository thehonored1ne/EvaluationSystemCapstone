@extends('errors.layout')

@section('title', '419 Session Expired')
@section('code', '419 Expired')
@section('heading', 'Session expired')

@section('illustration')
<img 
    src="{{ asset('error-page-illustration/419 error.webp') }}" 
    alt="419 Session Expired Illustration" 
    class="max-h-56 sm:max-h-64 w-auto object-contain mx-auto transition-transform duration-300 hover:scale-105"
    loading="eager"
/>
@endsection

@section('message')
Your session timed out due to inactivity. Please go back or log in again to continue.
@endsection
