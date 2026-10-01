/**
 * Actualización automática de las lecturas de un estanque (paso 13): sondea su endpoint
 * (/estanques/{id}/lecturas) cada ~30s con fetch() y solo reemplaza el bloque de datos,
 * sin recargar la página. Si una petición falla, conserva los últimos datos mostrados y
 * lo indica junto a la hora de actualización en vez de romper la interfaz.
 */
(function () {
    const POLL_MS = 30000;
    const STATUS_LABELS = {
        normal: ['ok', 'Normal'],
        advertencia: ['warn', 'Advertencia'],
        critico: ['crit', 'Crítico'],
    };

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[char]));
    }

    function statusTag(status) {
        if (!status || !STATUS_LABELS[status]) {
            return '<span class="tag mute">Sin datos</span>';
        }

        const [tone, label] = STATUS_LABELS[status];

        return `<span class="tag ${tone}">${label}</span>`;
    }

    function relativeTime(iso) {
        if (!iso) {
            return 'nunca se ha conectado';
        }

        const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);

        if (minutes < 1) return 'hace instantes';
        if (minutes < 60) return `hace ${minutes} min`;

        const hours = Math.round(minutes / 60);

        if (hours < 24) return `hace ${hours} h`;

        return `hace ${Math.round(hours / 24)} d`;
    }

    function deviceLine(item) {
        if (!item.device_code) {
            return 'Sin dispositivo asignado';
        }

        if (!item.device_active) {
            return `Dispositivo ${escapeHtml(item.device_code)} · inactivo`;
        }

        return `Dispositivo ${escapeHtml(item.device_code)} · conectado ${relativeTime(item.device_last_connected_at)}`;
    }

    function renderRow(item) {
        const valueLine = item.value !== null
            ? `${[item.value, item.unit].filter(Boolean).map(escapeHtml).join(' ')} · ${new Date(item.measured_at).toLocaleString('es-CO')}`
            : 'Sin lecturas todavía';

        return `<div class="list-row">
            <span class="body">
                <b>${escapeHtml(item.metric_name)}</b>
                <span>${valueLine} · sensor ${escapeHtml(item.sensor_code)} · ${deviceLine(item)}</span>
            </span>
            ${statusTag(item.status)}
        </div>`;
    }

    function render(root, data) {
        const body = root.querySelector('[data-live-body]');

        if (!data.readings.length) {
            body.innerHTML = '<p class="muted-line">Este estanque no tiene sensores activos configurados.</p>';

            return;
        }

        if (!data.has_active_batch) {
            body.innerHTML = '<p class="muted-line">Este estanque no tiene un lote activo: no se registran mediciones nuevas.</p>';

            return;
        }

        body.innerHTML = data.readings.map(renderRow).join('');
    }

    function boot(root) {
        const url = root.dataset.pollUrl;
        const status = root.querySelector('[data-live-status]');
        let timer = null;

        async function tick() {
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    throw new Error('HTTP '.concat(response.status));
                }

                render(root, await response.json());
                status.textContent = `Actualizado a las ${new Date().toLocaleTimeString('es-CO')}`;
            } catch (error) {
                // Se conservan los últimos datos mostrados; solo se avisa que la actualización falló.
                status.textContent = `No se pudo actualizar (último intento: ${new Date().toLocaleTimeString('es-CO')})`;
            }
        }

        function start() {
            if (timer === null) {
                tick();
                timer = window.setInterval(tick, POLL_MS);
            }
        }

        function stop() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stop();
            } else {
                start();
            }
        });

        window.addEventListener('pagehide', stop);

        if (!document.hidden) {
            start();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-live-readings]').forEach(boot);
    });
})();
