@extends('layout.app')

@section('titulo', 'Seguridad')

@section('contenido')
    <x-landing titulo="Seguridad" icono="shield-lock"
               desc="Quién entra al sistema, qué puede hacer cada uno y qué quedó registrado. El equipo y los turnos están en Personal."
               :subs="$subs" />
@endsection
