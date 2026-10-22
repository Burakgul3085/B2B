@extends('errors.layout')
@section('code', 'Hata')
@section('title', 'Beklenmeyen bir hata oluştu')
@section('message', 'İşleminiz tamamlanamadı. Lütfen birkaç dakika sonra tekrar deneyin.')
@if (\App\Domain\Platform\ErrorRecorder::$lastReference)
    @section('reference', \App\Domain\Platform\ErrorRecorder::$lastReference)
@endif
