@extends('layout.app')

@section('titulo', 'Personal')

@section('contenido')
    <x-landing titulo="Personal" icono="person-badge"
               desc="El equipo del salón: quién trabaja, en qué horario y cuánto gana por servicio."
               :subs="$subs" />
@endsection
