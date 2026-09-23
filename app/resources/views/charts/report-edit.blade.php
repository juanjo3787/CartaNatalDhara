@extends('layouts.app')

@section('title', 'Editar informe Fase 1 - ' . $report['name'])

@php
    $blockTitles = [
        'intro' => 'Tu primera lectura', 'states' => 'Estados de expresión', 'conclusions' => 'Conclusiones importantes',
        'shared_intro' => 'Función y posición', 'shared_states' => 'Estados de expresión', 'shared_conclusions' => 'Conclusiones',
        'function' => 'Función y posición', 'sign' => 'Qué necesita este signo', 'house' => 'La casa y el territorio de experiencia',
        'ruler' => 'El regente y su posición', 'integration' => 'Integración de las piezas', 'harmony' => 'Expresión Armónica',
        'deficit' => 'Expresión Des-Armónica por defecto', 'excess' => 'Expresión Des-Armónica por exceso', 'harmonization' => 'Armonización e integración final', 'closing' => 'Preguntas y frases de integración',
    ];
@endphp

@section('content')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
    .report-editor { max-width: 1100px; margin: 0 auto; padding-bottom: 6rem; }
    .editor-toolbar { display: flex; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 2rem; }
    .editor-section { margin: 2rem 0; padding: 1.5rem; border: 1px solid #ded7cf; background: #fffdf9; }
    .editor-section h2 { margin-top: 0; }
    .editor-door { margin: 2rem 0 0; padding: 1rem 1.25rem; background: #f8dfcc; }
    .editor-block { margin: 1.25rem 0; }
    .editor-block label { display: block; margin-bottom: .45rem; font-weight: 700; color: #674b39; }
    .editor-toolbar-actions { display: flex; gap: .35rem; margin-bottom: .35rem; }
    .editor-toolbar-actions button { margin: 0; padding: .35rem .55rem; border: 1px solid #b58b67; border-radius: 4px; background: #fffaf5; color: #674b39; font-size: .78rem; box-shadow: none; }
    .ai-generation-status { margin: .5rem 0 0; color: #674b39; font-size: .84rem; }
    .ai-generation-status.is-error { color: #9f2d2d; }
    .quill-toolbar { border: 1px solid #d7d1ca !important; border-bottom: 0 !important; border-radius: 4px 4px 0 0; background: #fffaf5; }
    .rich-editor { min-height: 150px; border: 1px solid #d7d1ca !important; border-radius: 0 0 4px 4px; background: #fff; }
    .rich-editor .ql-editor { min-height: 150px; font: 1rem/1.6 Aptos, 'Segoe UI', sans-serif; color: #202020; }
    .rich-editor .ql-editor p { margin: 0 0 .8rem; }
    .editor-actions { position: sticky; bottom: 1rem; display: flex; justify-content: flex-end; padding: 1rem 0; background: rgba(255,255,255,.94); }
</style>

<div class="report-editor">
    <div class="editor-toolbar">
        <div><span class="badge">Edición del informe</span><h1>Editar informe Fase 1</h1></div>
        <form id="generate-all-ai-form" method="POST" action="{{ route('charts.report.ai.all', $chart) }}" data-confirm-message="¿Generar las cuatro puertas con IA en orden de continuidad?">
            @csrf
            <button type="submit" style="margin:0; padding:.65rem .9rem; font-size:.78rem;">Generar las cuatro puertas con IA</button>
        </form>
    </div>
    <p class="ai-generation-status" data-ai-generation-status role="status" aria-live="polite"></p>

    @if ($errors->any())
        <div class="note"><strong>Revisa el formulario:</strong><ul>@foreach ($errors->all() as $error)<li class="error">{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form id="report-edit-form" method="POST" action="{{ route('charts.report.save', $chart) }}">
        @csrf
        @method('PUT')

        <section class="editor-section">
            <h2>Texto compartido del dossier</h2>
            @foreach ($editable['shared'] as $block => $content)
                    <div class="editor-block"><label for="shared-{{ $block }}">{{ $blockTitles[$block] ?? ucfirst(str_replace('_', ' ', $block)) }}</label><div id="toolbar-shared-{{ $block }}" class="quill-toolbar"><span class="ql-formats"><button class="ql-bold"></button><button class="ql-italic"></button><button class="ql-underline"></button></span><span class="ql-formats"><button class="ql-list" value="ordered"></button><button class="ql-list" value="bullet"></button><button class="ql-blockquote"></button></span><span class="ql-formats"><button class="ql-link"></button><button class="ql-clean"></button></span></div><div id="shared-{{ $block }}" class="rich-editor" data-rich-editor>{!! old('shared.' . $block, $content) !!}</div><textarea name="shared[{{ $block }}]" data-editor-value="shared-{{ $block }}" hidden></textarea></div>
            @endforeach
        </section>

        @foreach ($report['doors'] as $door)
            <section class="editor-section">
                <h2 class="editor-door">{{ $door['title'] }}<small style="display:block;font: .9rem Aptos, 'Segoe UI', sans-serif;margin-top:.4rem;">{{ $door['question'] }}</small><button type="button" class="generate-door-ai" data-door="{{ $door['key'] }}" style="margin-top:.75rem; padding:.55rem .8rem; font-size:.78rem;">Generar esta puerta con IA</button></h2>
                @foreach ($editable['doors'][$door['key']] as $block => $content)
                    <div class="editor-block"><label for="{{ $door['key'] }}-{{ $block }}">{{ $blockTitles[$block] ?? ucfirst(str_replace('_', ' ', $block)) }}</label><div id="toolbar-{{ $door['key'] }}-{{ $block }}" class="quill-toolbar"><span class="ql-formats"><button class="ql-bold"></button><button class="ql-italic"></button><button class="ql-underline"></button></span><span class="ql-formats"><button class="ql-list" value="ordered"></button><button class="ql-list" value="bullet"></button><button class="ql-blockquote"></button></span><span class="ql-formats"><button class="ql-link"></button><button class="ql-clean"></button></span></div><div id="{{ $door['key'] }}-{{ $block }}" class="rich-editor" data-rich-editor>{!! old('doors.' . $door['key'] . '.' . $block, $content) !!}</div><textarea name="doors[{{ $door['key'] }}][{{ $block }}]" data-editor-value="{{ $door['key'] }}-{{ $block }}" hidden></textarea></div>
                @endforeach
            </section>
        @endforeach

        <div class="editor-actions" aria-hidden="true"></div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const editors = new Map();
        document.querySelectorAll('[data-rich-editor]').forEach((element) => {
            const quill = new Quill(element, {
                theme: 'snow',
                modules: { toolbar: `#toolbar-${element.id}` },
            });
            editors.set(element.id, quill);
        });

        const form = document.getElementById('report-edit-form');
        form?.addEventListener('submit', () => {
            document.querySelectorAll('[data-editor-value]').forEach((field) => {
                const editor = editors.get(field.dataset.editorValue);
                field.value = editor?.root.innerHTML || '';
            });
        });

        const generateAllForm = document.getElementById('generate-all-ai-form');
        const generationStatus = document.querySelector('[data-ai-generation-status]');
        const generationProgress = document.querySelector('[data-app-loading-progress]');
        const progressBar = document.querySelector('[data-app-loading-progress-bar]');
        const progressValue = document.querySelector('[data-app-loading-progress-value]');
        const setProgress = (value) => {
            const percentage = Math.max(0, Math.min(100, value));
            progressBar.value = percentage;
            progressValue.textContent = `${percentage}%`;
        };

        // One small HTTP request per stage instead of chaining several OpenAI calls behind a single
        // request: a single big request used to exceed Cloudflare's 120s proxy read timeout (524).
        const STAGES = @json(\App\Services\Doors\AbstractDoorPipeline::STAGES);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const stageUrlTemplate = @json(route('charts.report.ai.stage', [$chart, 'DOOR_PLACEHOLDER', 'STAGE_PLACEHOLDER']));

        async function generateDoorByStages(door, onStageComplete) {
            for (let index = 0; index < STAGES.length; index += 1) {
                const stage = STAGES[index];
                const url = stageUrlTemplate.replace('DOOR_PLACEHOLDER', door).replace('STAGE_PLACEHOLDER', stage);
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = typeof body.message === 'string' ? body.message.replace(/^Error de IA:\s*/, '') : null;
                    throw new Error(message || `No se pudo generar ${door} (etapa ${stage}). HTTP ${response.status}.`);
                }
                onStageComplete?.(stage, index);
            }
        }

        document.querySelectorAll('.generate-door-ai').forEach((button) => {
            button.addEventListener('click', async () => {
                const door = button.dataset.door;
                button.disabled = true;
                generationStatus.classList.remove('is-error');
                generationProgress.classList.add('is-visible');
                generationProgress.setAttribute('aria-hidden', 'false');
                setProgress(0);

                try {
                    await generateDoorByStages(door, (stage, index) => {
                        const percentage = Math.round(((index + 1) / STAGES.length) * 100);
                        setProgress(percentage);
                        generationStatus.textContent = `Generando ${door} · etapa ${stage} (${index + 1} de ${STAGES.length}) · ${percentage}% completado...`;
                    });
                    generationStatus.textContent = `Puerta ${door} generada · 100%. Actualizando el informe...`;
                    window.location.assign('{{ route('charts.report.edit', $chart) }}');
                } catch (error) {
                    generationStatus.textContent = error.message || `No se pudo generar ${door} con IA.`;
                    generationStatus.classList.add('is-error');
                    generationProgress.setAttribute('aria-hidden', 'false');
                    button.disabled = false;
                }
            });
        });

        generateAllForm?.addEventListener('submit', async (event) => {
            if (event.defaultPrevented) return;

            event.preventDefault();
            const button = generateAllForm.querySelector('button[type="submit"]');
            const doors = ['sol', 'luna', 'ascendente', 'descendente'];
            const totalSteps = doors.length * STAGES.length;
            let completedSteps = 0;
            button.disabled = true;
            generationStatus.classList.remove('is-error');
            generationProgress.classList.add('is-visible');
            generationProgress.setAttribute('aria-hidden', 'false');
            setProgress(0);

            try {
                for (const door of doors) {
                    await generateDoorByStages(door, (stage, index) => {
                        completedSteps += 1;
                        const percentage = Math.round((completedSteps / totalSteps) * 100);
                        setProgress(percentage);
                        generationStatus.textContent = `Generando ${door} · etapa ${stage} (${index + 1} de ${STAGES.length}) · ${percentage}% completado...`;
                    });
                }

                generationStatus.textContent = 'Las cuatro puertas se han generado · 100%. Actualizando el informe...';
                window.location.assign('{{ route('charts.report.edit', $chart) }}');
            } catch (error) {
                generationStatus.textContent = error.message || 'No se pudo completar la generación con IA.';
                generationStatus.classList.add('is-error');
                generationProgress.setAttribute('aria-hidden', 'false');
                button.disabled = false;
            }
        });
    });
</script>
@endsection

@section('floating_actions')
    <nav class="app-action-bar" aria-label="Acciones de edición">
        <button class="app-action app-action-primary" type="submit" form="report-edit-form">Guardar informe</button>
        <a class="app-action" href="{{ route('charts.report', $chart) }}">Volver al informe</a>
        <a class="app-action" href="{{ route('charts.index') }}">Ver cartas</a>
    </nav>
@endsection
