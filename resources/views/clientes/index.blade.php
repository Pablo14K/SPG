@extends('layout.app')

@section('titulo', 'Clientes')

@section('contenido')
    <x-landing titulo="Clientes" icono="people"
               desc="Gestioná el registro de clientes, su fidelización y valoraciones."
               :subs="$subs" />
@endsection
