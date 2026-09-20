@extends('layouts.app')
@section('title', 'Editar datos de registro')
@section('content')
<h1>Editar datos de registro</h1>
@include('components.validation-alert')
<form id="registration-form" method="POST" action="{{ route('charts.registration.update', $chart) }}">
@csrf @method('PUT')
<label>Alias<input name="alias" value="{{ old('alias', $chart->person->alias) }}" required></label>
@error('alias')<small class="field-error">{{ $message }}</small>@enderror
<label>Nombre completo (opcional)<input name="full_name" value="{{ old('full_name', $chart->person->full_name) }}"></label>
@error('full_name')<small class="field-error">{{ $message }}</small>@enderror
<label>Ciudad<input name="city" value="{{ old('city', $chart->birthData->place->city) }}" required></label>
@error('city')<small class="field-error">{{ $message }}</small>@enderror
<label>País<input name="country" value="{{ old('country', $chart->birthData->place->country) }}" required></label>
@error('country')<small class="field-error">{{ $message }}</small>@enderror
<div class="row"><div><label>Latitud<input type="number" step="0.000001" name="latitude" value="{{ old('latitude', $chart->birthData->place->latitude) }}" required></label>@error('latitude')<small class="field-error">{{ $message }}</small>@enderror</div><div><label>Longitud<input type="number" step="0.000001" name="longitude" value="{{ old('longitude', $chart->birthData->place->longitude) }}" required></label>@error('longitude')<small class="field-error">{{ $message }}</small>@enderror</div></div>
<div class="row"><div><label>Fecha de nacimiento<input name="local_date" value="{{ old('local_date', $chart->birthData->local_date->format('d/m/Y')) }}" required></label>@error('local_date')<small class="field-error">{{ $message }}</small>@enderror</div><div><label>Hora de nacimiento<input name="local_time" value="{{ old('local_time', substr((string) $chart->birthData->local_time, 0, 5)) }}" required></label>@error('local_time')<small class="field-error">{{ $message }}</small>@enderror</div></div>
<label>Zona horaria<input name="timezone_identifier" value="{{ old('timezone_identifier', $chart->birthData->timezone_identifier) }}" required></label>
@error('timezone_identifier')<small class="field-error">{{ $message }}</small>@enderror
<div class="row"><div><label>Fuente de la hora<select name="time_source" required><option value="document" @selected(old('time_source', $chart->birthData->time_source) === 'document')>Documento oficial</option><option value="family" @selected(old('time_source', $chart->birthData->time_source) === 'family')>Familia</option><option value="estimated" @selected(old('time_source', $chart->birthData->time_source) === 'estimated')>Estimada</option><option value="unknown" @selected(old('time_source', $chart->birthData->time_source) === 'unknown')>Desconocida</option></select></label>@error('time_source')<small class="field-error">{{ $message }}</small>@enderror</div><div><label>Precisión de la hora<select name="time_precision" required><option value="exact" @selected(old('time_precision', $chart->birthData->time_precision) === 'exact')>Exacta</option><option value="approximate" @selected(old('time_precision', $chart->birthData->time_precision) === 'approximate')>Aproximada</option><option value="unknown" @selected(old('time_precision', $chart->birthData->time_precision) === 'unknown')>Desconocida</option></select></label>@error('time_precision')<small class="field-error">{{ $message }}</small>@enderror</div></div>
<label>Notas privadas<textarea name="notes" rows="4" style="resize:none">{{ old('notes', $chart->person->notes) }}</textarea></label>
</form>
@endsection
@section('floating_actions')
<nav class="app-action-bar"><button class="app-action app-action-primary" type="submit" form="registration-form">Guardar cambios</button><a class="app-action" href="{{ route('charts.index') }}">Ver cartas</a></nav>
@endsection
