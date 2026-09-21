@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
    <h1>Iniciar sesión</h1>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">Usuario</label>
        <input type="text" id="email" name="email" value="{{ old('email') }}" autofocus required>

        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Entrar</button>
    </form>
@endsection
