@auth
<style>
    #report-jobs .report-row { display: flex; align-items: center; flex-wrap: wrap; gap: .25rem .75rem; margin-block: .25rem; }
    #report-jobs .report-row[hidden] { display: none; }
    #report-jobs .report-actions { display: inline-flex; align-items: center; gap: .75rem; }
    #report-jobs .report-actions a { white-space: nowrap; }
    #report-jobs .dismiss-report-button {
        display: inline-flex; align-items: center; justify-content: center;
        flex: 0 0 24px; width: 24px; height: 24px; min-width: 0; min-height: 0;
        padding: 0; margin: 0; border: 0; border-radius: 50%;
        background: transparent; color: #795c48; box-shadow: none;
        font: 20px/1 Arial, sans-serif; cursor: pointer; transform: none;
        transition: background-color .15s ease, color .15s ease;
    }
    #report-jobs .dismiss-report-button:hover { background: #f1e9e1; color: #513b2b; box-shadow: none; transform: none; }
    #report-jobs .dismiss-report-button:focus-visible { outline: 2px solid #795c48; outline-offset: 2px; }
</style>
<aside id="report-jobs" aria-live="polite" style="margin:1rem 0"></aside>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('report-jobs');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const labels = new Map();
    const dismissing = new Set();
    const dismissed = new Set();
    let revision = 0;
    function display(job) {
        if (dismissing.has(job.job_id) || dismissed.has(job.job_id)) return;
        let row = labels.get(job.job_id);
        if (!row) {
            row = document.createElement('div');
            row.className = 'report-row';
            panel.append(row);
            labels.set(job.job_id, row);
        }
        row.replaceChildren();
        const text = document.createElement('span');
        text.textContent = `Carta ${job.report_id}: ${job.message} — ${job.progress} %. `;
        row.append(text);
        const actions = document.createElement('span');
        actions.className = 'report-actions';
        let close;
        if (['completed', 'failed'].includes(job.status)) {
            close = document.createElement('button');
            close.type = 'button';
            close.textContent = '×';
            close.setAttribute('aria-label', `Cerrar aviso de Carta ${job.report_id}`);
            close.className = 'dismiss-report-button';
            close.onclick = async () => {
                revision++;
                dismissing.add(job.job_id);
                row.hidden = true;
                try {
                    const response = await fetch(job.status_url + '/dismiss', {
                        method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}
                    });
                    if (!response.ok) throw new Error('No se pudo cerrar el aviso. Inténtalo de nuevo.');
                    dismissed.add(job.job_id);
                    row.remove();
                    labels.delete(job.job_id);
                } catch (error) {
                    row.hidden = false;
                    showError(error);
                } finally {
                    dismissing.delete(job.job_id);
                    revision++;
                }
            };
        }
        if (job.status === 'completed') {
            for (const [label, url] of [['Abrir informe', job.report_url], ['Descargar PDF', job.pdf_url]]) {
                const link = document.createElement('a'); link.href = url; link.textContent = label; actions.append(link);
            }
        } else if (job.status === 'failed') {
            const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Reintentar etapa pendiente';
            button.onclick = () => send(job.retry_url, {}).catch(showError); actions.append(button);
            const error = document.createElement('span'); error.textContent = job.error_message; row.append(error);
        } else {
            const hint = document.createElement('span'); hint.textContent = 'Puedes navegar o cerrar esta página.'; row.append(hint);
        }
        if (close) actions.append(close);
        if (actions.children.length) row.append(actions);
    }
    function showError(error) { const text = document.createElement('p'); text.textContent = error.message; panel.append(text); }
    async function send(url, data) {
        const response = await fetch(url, {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify(data)});
        const job = await response.json();
        if (!response.ok) throw new Error(job.message || 'No se pudo crear el trabajo.');
        revision++;
        dismissed.delete(job.job_id);
        display(job); return job;
    }
    window.startReportJob = async (url, doors) => {
        const data = doors === undefined ? {} : {doors};
        const image = await window.getNatalWheelImage?.();
        if (image) data.wheel_image = image;
        return send(url, data);
    };
    document.querySelectorAll('form[data-report-job]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            try { await window.startReportJob(form.action); } catch (error) { showError(error); }
            finally { if (button) button.disabled = false; }
        });
    });
    document.querySelectorAll('[data-report-job-url]').forEach(button => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            try { await window.startReportJob(button.dataset.reportJobUrl, JSON.parse(button.dataset.doors)); }
            catch (error) { showError(error); }
            finally { button.disabled = false; }
        });
    });
    async function poll() {
        const requestedRevision = revision;
        try {
            const response = await fetch(@json(route('reports.jobs.index')), {headers: {'Accept': 'application/json'}});
            if (response.ok) {
                const jobs = await response.json();
                if (requestedRevision === revision) {
                    const ids = new Set(jobs.map(job => job.job_id));
                    for (const [id, row] of labels) {
                        if (!ids.has(id) && !dismissing.has(id)) { row.remove(); labels.delete(id); }
                    }
                    for (const job of jobs) {
                        if (!['completed', 'failed'].includes(job.status)) dismissed.delete(job.job_id);
                        display(job);
                    }
                }
            }
        } catch (_) { /* Resume polling after temporary loss of connectivity. */ }
        setTimeout(poll, 4000);
    }
    poll();
});
</script>
@endauth
