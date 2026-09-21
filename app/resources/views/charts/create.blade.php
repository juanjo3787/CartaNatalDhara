@extends('layouts.app')

@section('title', 'Nueva carta')

@section('content')
    <h1>Nueva carta natal</h1>

    @include('components.validation-alert')

    <form id="new-chart-form" method="POST" action="{{ route('charts.store') }}">
        @csrf

        <label for="alias">Alias de la persona</label>
        <input type="text" id="alias" name="alias" value="{{ old('alias') }}" required>
        @error('alias')<small class="field-error">{{ $message }}</small>@enderror

        <label for="full_name">Nombre completo</label>
        <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
        @error('full_name')<small class="field-error">{{ $message }}</small>@enderror

        <div class="row">
            <div>
                <label for="local_date">Fecha de nacimiento (DD/MM/YYYY)</label>
                <input
                    type="text"
                    name="local_date"
                    value="{{ old('local_date') }}"
                    placeholder="DD/MM/YYYY"
                    inputmode="numeric"
                    pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/\d{4}"
                    required
                >
                @error('local_date')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div>
                <label for="local_time">Hora de nacimiento (24 horas)</label>
                <input
                    type="text"
                    id="local_time"
                    name="local_time"
                    value="{{ old('local_time') }}"
                    placeholder="HH:mm"
                    inputmode="numeric"
                    pattern="^(?:[01]\d|2[0-3]):[0-5]\d$"
                    required
                >
                @error('local_time')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>

        <label for="timezone_identifier">Zona horaria (identificador IANA, ej. Europe/Madrid)</label>
        <input type="text" id="timezone_identifier" name="timezone_identifier" value="{{ old('timezone_identifier', 'Europe/Madrid') }}" required>
        @error('timezone_identifier')<small class="field-error">{{ $message }}</small>@enderror

        <label for="address">Dirección de nacimiento (para calcular latitud y longitud)</label>
        <div class="row" style="align-items: end;">
            <div style="flex: 1;">
                <input type="text" id="address" name="address" value="{{ old('address') }}" placeholder="Calle, ciudad, país">
            </div>
            <div class="address-action">
                <button class="form-action" type="button" id="lookup-address-btn">Obtener coordenadas</button>
            </div>
        </div>

        <div class="row">
            <div>
                <label for="city">Ciudad de nacimiento</label>
                <input type="text" id="city" name="city" value="{{ old('city') }}" required>
                @error('city')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div>
                <label for="country">País</label>
                <input type="text" id="country" name="country" value="{{ old('country') }}" required>
                @error('country')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>

        <div class="row">
            <div>
                <label for="latitude">Latitud</label>
                <input type="number" step="0.000001" id="latitude" name="latitude" value="{{ old('latitude') }}" required>
                @error('latitude')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div>
                <label for="longitude">Longitud</label>
                <input type="number" step="0.000001" id="longitude" name="longitude" value="{{ old('longitude') }}" required>
                @error('longitude')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>

        <div class="row">
            <div>
                <label for="time_source">Fuente de la hora</label>
                <select id="time_source" name="time_source">
                    <option value="document" @selected(old('time_source') === 'document')>Documento oficial</option>
                    <option value="family" @selected(old('time_source') === 'family')>Familia</option>
                    <option value="estimated" @selected(old('time_source') === 'estimated')>Estimada</option>
                    <option value="unknown" @selected(old('time_source', 'unknown') === 'unknown')>Desconocida</option>
                </select>
            </div>
            <div>
                <label for="time_precision">Precisión de la hora</label>
                <select id="time_precision" name="time_precision">
                    <option value="exact" @selected(old('time_precision') === 'exact')>Exacta</option>
                    <option value="approximate" @selected(old('time_precision') === 'approximate')>Aproximada</option>
                    <option value="unknown" @selected(old('time_precision', 'unknown') === 'unknown')>Desconocida</option>
                </select>
            </div>
        </div>

        <label for="notes">Notas privadas (opcional)</label>
        <textarea id="notes" name="notes" rows="4" style="resize: none;">{{ old('notes') }}</textarea>

        <div class="note">
            La hora y la latitud/longitud se convertiran automaticamente a UTC real antes de calcular la carta,
            respetando el horario de verano historico de la zona horaria indicada.
        </div>

    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const button = document.getElementById('lookup-address-btn');
            const addressInput = document.getElementById('address');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            const cityInput = document.getElementById('city');
            const countryInput = document.getElementById('country');

            if (!button || !addressInput || !latitudeInput || !longitudeInput) {
                return;
            }

            button.addEventListener('click', function () {
                const value = addressInput.value.trim();

                if (!value) {
                    alert('Escribe una dirección para buscar sus coordenadas.');
                    return;
                }

                button.disabled = true;
                button.textContent = 'Buscando...';

                const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(value);

                fetch(url, { headers: { 'Accept-Language': 'es' }})
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('La búsqueda de coordenadas falló.');
                        }

                        return response.json();
                    })
                    .then((results) => {
                        if (!results || results.length === 0) {
                            throw new Error('No se encontraron coordenadas para esa dirección.');
                        }

                        const result = results[0];
                        latitudeInput.value = result.lat;
                        longitudeInput.value = result.lon;

                        const addressParts = result.address || {};
                        const displayParts = (result.display_name || '').split(',').map((part) => part.trim()).filter(Boolean);
                        const city = addressParts.city || addressParts.town || addressParts.village || addressParts.municipality || addressParts.city_district || displayParts[0] || '';
                        const country = addressParts.country || displayParts[displayParts.length - 1] || '';
                        cityInput.value = city;
                        countryInput.value = country;
                    })
                    .catch((error) => {
                        alert(error.message || 'No se pudo calcular la dirección.');
                    })
                    .finally(() => {
                        button.disabled = false;
                        button.textContent = 'Obtener coordenadas';
                    });
            });
        });
    </script>
@endsection

@section('floating_actions')
    <nav class="app-action-bar" aria-label="Acciones de nueva carta">
        <button class="app-action app-action-primary" type="submit" form="new-chart-form">Calcular carta</button>
        <a class="app-action" href="{{ route('charts.index') }}">Ver cartas</a>
    </nav>
@endsection
