@extends('layouts.default')
@section('title', 'dashboard')
@section('content')
    <!-- begin breadcrumb -->
    <ol class="breadcrumb float-xl-end">
        <li class="breadcrumb-item"><a href="javascript:;">Home</a></li>
        <li class="breadcrumb-item"><a href="javascript:;">Library</a></li>
        <li class="breadcrumb-item active"><a href="javascript:;">Data</a></li>
    </ol>
    <!-- end breadcrumb -->
    <!-- begin page-header -->
    <h1 class="page-header">Selamat datang <small>{{ Auth::user()->name }}</small></h1>
    <!-- end page-header -->

    @yield('module-content')
@endsection
