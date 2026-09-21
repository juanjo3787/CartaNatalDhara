@if ($errors->any())
    <div class="p-message p-message-error" role="alert" aria-live="polite">
        <div class="p-message-wrapper">
            <span class="p-message-icon" aria-hidden="true">!</span>
            <div>
                <strong>Revisa los datos del formulario</strong>
                <ul class="p-message-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

@if (session('success'))
    <div class="p-message p-message-success" role="status" aria-live="polite">
        <div class="p-message-wrapper">
            <span class="p-message-icon" aria-hidden="true">✓</span>
            <span>{{ session('success') }}</span>
        </div>
    </div>
@endif

@if (session('error'))
    <div class="p-message p-message-error" role="alert" aria-live="polite">
        <div class="p-message-wrapper">
            <span class="p-message-icon" aria-hidden="true">!</span>
            <span>{{ session('error') }}</span>
        </div>
    </div>
@endif