<div class="p-dialog-mask" data-ai-error-modal aria-hidden="true">
    <section class="p-dialog p-dialog-ai-error" role="alertdialog" aria-modal="true" aria-labelledby="ai-error-modal-title">
        <header class="p-dialog-header">
            <span id="ai-error-modal-title">Error de generación con IA</span>
            <button class="p-dialog-close" type="button" data-ai-error-close aria-label="Cerrar">×</button>
        </header>
        <div class="p-dialog-content">
            <p data-ai-error-message></p>
            <dl class="p-dialog-detail-list" data-ai-error-details hidden>
                <div data-ai-error-row-door hidden><dt>Puerta</dt><dd data-ai-error-door></dd></div>
                <div data-ai-error-row-stage hidden><dt>Etapa</dt><dd data-ai-error-stage></dd></div>
                <div data-ai-error-row-code hidden><dt>Código</dt><dd data-ai-error-code></dd></div>
            </dl>
        </div>
        <footer class="p-dialog-footer">
            <button class="p-dialog-button p-dialog-button-primary" type="button" data-ai-error-close>Cerrar</button>
        </footer>
    </section>
</div>
