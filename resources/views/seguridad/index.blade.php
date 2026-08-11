@extends('layout.app')

@section('titulo', 'Seguridad')

@section('contenido')
    <x-landing titulo="Seguridad" icono="shield-lock"
               desc="Quién entra al sistema, qué puede hacer cada uno y qué quedó registrado."
               :subs="$subs" />
@endsection
