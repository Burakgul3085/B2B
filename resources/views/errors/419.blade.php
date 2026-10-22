@extends('errors.layout')
@section('code', '419')
@section('title', 'Sayfanın süresi doldu')
@section('message', 'Güvenliğiniz için bir süre işlem yapılmayan sayfalar yenilenmelidir. Sayfayı yenileyip işleminizi tekrar deneyin; formdaki bilgileri yeniden girmeniz gerekebilir.')
@section('actions')<a href="{{ url()->previous() }}" class="inline-flex items-center rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-slate-50">Geri dön</a>@endsection
