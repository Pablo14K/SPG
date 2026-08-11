@extends('layout.app')

@section('titulo', 'Servicios')

@section('contenido')
    <x-landing titulo="Servicios" icono="scissors"
               desc="Catálogo de servicios, sus categorías y los descuentos."
               :subs="$subs" />
@endsection
