@auth
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
            panel.append(row);
            labels.set(job.job_id, row);
        }
        row.replaceChildren();
        const text = document.createElement('span');
        text.textContent = `Carta ${job.report_id}: ${job.message} — ${job.progress} %. `;
        row.append(text);
        if (['completed', 'failed'].includes(job.status)) {
            const close = document.createElement('button');
            close.type = 'button';
            close.textContent = '×';
            close.setAttribute('aria-label', `Cerrar aviso de Carta ${job.report_id}`);
            close.style.cssText = 'float:right;min-width:44px;min-height:44px;margin-left:1rem';
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
            row.append(close);
        }
        if (job.status === 'completed') {
            for (const [label, url] of [['Abrir informe', job.report_url], ['Descargar PDF', job.pdf_url]]) {
                const link = document.createElement('a'); link.href = url; link.textContent = label; link.style.marginRight = '1rem'; row.append(link);
            }
        } else if (job.status === 'failed') {
            const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Reintentar etapa pendiente';
            button.onclick = () => send(job.retry_url, {}).catch(showError); row.append(button);
            const error = document.createElement('span'); error.textContent = job.error_message; row.append(error);
        } else {
            const hint = document.createElement('span'); hint.textContent = 'Puedes navegar o cerrar esta página.'; row.append(hint);
        }
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
