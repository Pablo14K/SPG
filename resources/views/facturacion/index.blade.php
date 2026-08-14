@extends('layout.app')

@section('titulo', 'Tesorería')

@section('contenido')
    <x-landing titulo="Tesorería" icono="cash-stack"
               desc="Facturas, cobros, caja y timbrados."
               :subs="$subs" />
@endsection
