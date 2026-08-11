@extends('layout.app')

@section('titulo', 'Configuración')

@section('contenido')
    <x-landing titulo="Configuración" icono="gear"
               desc="Sucursales, roles, contacto y auditoría."
               :subs="$subs" />
@endsection
