@extends('layout.app')

@section('titulo', 'Citas y agenda')

@section('contenido')
    <x-landing titulo="Citas y agenda" icono="calendar-event"
               desc="Agenda, nuevas citas y excepciones de disponibilidad."
               :subs="$subs" />
@endsection
