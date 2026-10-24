@extends('layouts.app')

@section('title', 'Editar informe Fase 1 - ' . $report['name'])

@php
    $blockTitles = \App\Services\InteriorMapMaster::TITLES;
    $editorChapters = [];
    foreach ($report['doors'] as $door) {
        $editorChapters[] = ['title' => $door['title'], 'prefix' => 'doors[' . $door['key'] . ']', 'old_prefix' => 'doors.' . $door['key'], 'id' => $door['key'], 'blocks' => $editable['doors'][$door['key']]];
    }
    $editorChapters[] = ['title' => 'V — Los opuestos complementarios', 'prefix' => 'shared', 'old_prefix' => 'shared', 'id' => 'shared', 'blocks' => $editable['shared']];
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
        <form id="generate-all-ai-form" data-report-job method="POST" action="{{ route('charts.report.ai.all', $chart) }}">
            @csrf
            <button type="submit" style="margin:0; padding:.65rem .9rem; font-size:.78rem;">Generar los cinco bloques con IA</button>
        </form>
    </div>
    <p class="ai-generation-status" data-ai-generation-status role="status" aria-live="polite"></p>

    @if ($errors->any())
        <div class="note"><strong>Revisa el formulario:</strong><ul>@foreach ($errors->all() as $error)<li class="error">{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form id="report-edit-form" method="POST" action="{{ route('charts.report.save', $chart) }}">
        @csrf
        @method('PUT')

        @foreach ($editorChapters as $chapter)
            <section class="editor-section">
                <h2 class="editor-door">{{ $chapter['title'] }}</h2>
                @foreach ($chapter['blocks'] as $block => $content)
                    <div class="editor-block">
                        <label for="{{ $chapter['id'] }}-{{ $block }}">{{ $blockTitles[$block] }}</label>
                        <div id="toolbar-{{ $chapter['id'] }}-{{ $block }}" class="quill-toolbar">
                            <span class="ql-formats"><select class="ql-header"><option value="3">Concepto</option><option value="4">Característica</option><option selected>Párrafo</option></select></span>
                            <span class="ql-formats"><button class="ql-bold"></button><button class="ql-italic"></button><button class="ql-underline"></button></span>
                        </div>
                        <div id="{{ $chapter['id'] }}-{{ $block }}" class="rich-editor" data-rich-editor>{!! old($chapter['old_prefix'] . '.' . $block, $content) !!}</div>
                        <textarea name="{{ $chapter['prefix'] }}[{{ $block }}]" data-editor-value="{{ $chapter['id'] }}-{{ $block }}" hidden></textarea>
                    </div>
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
