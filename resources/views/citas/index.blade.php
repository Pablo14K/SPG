@extends('layout.app')

@section('titulo', 'Citas')

@section('contenido')
    <x-landing titulo="Citas" icono="calendar-event"
               desc="Agenda, nuevas citas y excepciones de disponibilidad."
               :subs="$subs" />
@endsection
