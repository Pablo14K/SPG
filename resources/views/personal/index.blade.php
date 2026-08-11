@extends('layout.app')

@section('titulo', 'Personal')

@section('contenido')
    <x-landing titulo="Personal" icono="person-badge"
               desc="Usuarios, turnos, comisiones y asistencia."
               :subs="$subs" />
@endsection
