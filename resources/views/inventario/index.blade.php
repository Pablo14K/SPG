@extends('layout.app')

@section('titulo', 'Inventario')

@section('contenido')
    <x-landing titulo="Inventario" icono="box-seam"
               desc="Productos, proveedores, stock y compras."
               :subs="$subs" />
@endsection
