@if (session('success') || session('error'))
    <div class="p-toast" data-toast-container aria-live="polite" aria-atomic="true">
        @if (session('success'))
            <div class="p-toast-message p-toast-message-success" data-toast role="status">
                <span class="p-toast-icon" aria-hidden="true">✓</span>
                <div class="p-toast-detail">
                    <strong>Correcto</strong>
                    <span>{{ session('success') }}</span>
                </div>
                <button class="p-toast-close" type="button" data-toast-close aria-label="Cerrar">×</button>
            </div>
        @endif

        @if (session('error'))
            <div class="p-toast-message p-toast-message-error" data-toast role="alert">
                <span class="p-toast-icon" aria-hidden="true">!</span>
                <div class="p-toast-detail">
                    <strong>Error</strong>
                    <span>{{ session('error') }}</span>
                </div>
                <button class="p-toast-close" type="button" data-toast-close aria-label="Cerrar">×</button>
            </div>
        @endif
    </div>
@endif