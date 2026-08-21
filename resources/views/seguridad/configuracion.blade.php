@extends('layout.app')

@section('titulo', 'Configuración')

@section('contenido')
    <x-landing titulo="Configuración" icono="sliders"
               desc="Tu cuenta y cómo está armado el salón: los locales y por dónde te contactan."
               :subs="$subs" />
@endsection
