@extends('layouts.app')
@section('title', 'Editar datos de registro')
@section('content')
<h1>Editar datos de registro</h1>
<form id="registration-form" method="POST" action="{{ route('charts.registration.update', $chart) }}">
@csrf @method('PUT')
<label>Alias<input name="alias" value="{{ old('alias', $chart->person->alias) }}" required></label>
<label>Nombre completo<input name="full_name" value="{{ old('full_name', $chart->person->full_name) }}"></label>
<label>Ciudad<input name="city" value="{{ old('city', $chart->birthData->place->city) }}" required></label>
<label>País<input name="country" value="{{ old('country', $chart->birthData->place->country) }}" required></label>
<div class="row"><div><label>Latitud<input type="number" step="0.000001" name="latitude" value="{{ old('latitude', $chart->birthData->place->latitude) }}" required></label></div><div><label>Longitud<input type="number" step="0.000001" name="longitude" value="{{ old('longitude', $chart->birthData->place->longitude) }}" required></label></div></div>
<div class="row"><div><label>Fecha de nacimiento<input name="local_date" value="{{ old('local_date', $chart->birthData->local_date->format('d/m/Y')) }}" required></label></div><div><label>Hora de nacimiento<input name="local_time" value="{{ old('local_time', substr((string) $chart->birthData->local_time, 0, 5)) }}" required></label></div></div>
<label>Zona horaria<input name="timezone_identifier" value="{{ old('timezone_identifier', $chart->birthData->timezone_identifier) }}" required></label>
<label>Notas privadas<textarea name="notes" rows="4" style="resize:none">{{ old('notes', $chart->person->notes) }}</textarea></label>
</form>
@endsection
@section('floating_actions')
<nav class="app-action-bar"><button class="app-action app-action-primary" type="submit" form="registration-form">Guardar cambios</button><a class="app-action" href="{{ route('charts.index') }}">Ver cartas</a></nav>
@endsection
