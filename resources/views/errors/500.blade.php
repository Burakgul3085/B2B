@extends('errors.layout')
@section('code', '500')
@section('title', 'Beklenmeyen bir hata oluştu')
@section('message', 'İşleminiz tamamlanamadı. Sorun kaydedildi ve incelenecek. Tekrar denemeden önce birkaç dakika bekleyin; sorun sürerse aşağıdaki referans koduyla bize ulaşın.')
@if (\App\Domain\Platform\ErrorRecorder::$lastReference)
    @section('reference', \App\Domain\Platform\ErrorRecorder::$lastReference)
@endif
