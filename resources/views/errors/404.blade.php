@extends('errors::illustrated-layout')

@section('title', __('Not Found'))
@section('code', '404')

@if($exception->getMessage())
    @section('message', $exception->getMessage())
@else
    @section('message', __('Not Found'))
@endif
