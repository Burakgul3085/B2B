@extends('errors.layout')
@section('code', '403')
@section('title', 'Bu sayfaya erişiminiz yok')
@section('message', $exception?->getMessage() && ! str_contains($exception->getMessage(), 'This action') && $exception->getMessage() !== 'Forbidden' ? $exception->getMessage() : 'Bu sayfayı görüntüleme yetkiniz bulunmuyor. Farklı bir hesapla giriş yapmanız gerekiyor olabilir.')
