@extends('layout.app')

@section('titulo', 'Facturación y caja')

@section('contenido')
    <x-landing titulo="Facturación y caja" icono="cash-stack"
               desc="Facturas, cobros, caja y timbrados."
               :subs="$subs" />
@endsection
