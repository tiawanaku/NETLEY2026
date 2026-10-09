(function () {
    // Nunca insertamos nada dentro del árbol que Livewire gestiona (evita que
    // el morph de Livewire se confunda al re-renderizar la tabla, p. ej. al
    // alternar columnas, y borre la tabla entera). La barra vive suelta en
    // document.body y se posiciona por coordenadas sobre el contenedor real.
    var bars = new Map(); // ctn -> { bar, inner }

    function createBar() {
        var bar = document.createElement('div');
        bar.className = 'netley-ta-top-scroll';

        var inner = document.createElement('div');
        bar.appendChild(inner);

        document.body.appendChild(bar);

        return { bar: bar, inner: inner };
    }

    function bind(ctn, entry) {
        var syncingBar = false;
        var syncingCtn = false;

        entry.bar.addEventListener('scroll', function () {
            if (syncingCtn) {
                return;
            }

            syncingBar = true;
            ctn.scrollLeft = entry.bar.scrollLeft;
            syncingBar = false;
        });

        ctn.addEventListener('scroll', function () {
            if (syncingBar) {
                return;
            }

            syncingCtn = true;
            entry.bar.scrollLeft = ctn.scrollLeft;
            syncingCtn = false;
        });
    }

    function update() {
        var seen = new Set();

        document.querySelectorAll('.fi-ta-content-ctn').forEach(function (ctn) {
            var table = ctn.querySelector('table');

            if (!table) {
                return;
            }

            var overflowing = table.scrollWidth > ctn.clientWidth + 1;
            var entry = bars.get(ctn);

            if (!overflowing) {
                if (entry) {
                    entry.bar.style.display = 'none';
                }

                return;
            }

            if (!entry) {
                entry = createBar();
                bars.set(ctn, entry);
                bind(ctn, entry);
            }

            seen.add(ctn);

            var rect = ctn.getBoundingClientRect();
            var barHeight = entry.bar.offsetHeight || 15;

            entry.inner.style.width = table.scrollWidth + 'px';
            entry.bar.style.display = 'block';
            entry.bar.style.width = rect.width + 'px';
            entry.bar.style.left = rect.left + window.scrollX + 'px';
            // Se posiciona ARRIBA del contenedor (no encima), para no tapar
            // la fila de cabecera de la tabla, que empieza justo en rect.top.
            entry.bar.style.top = rect.top + window.scrollY - barHeight + 'px';

            // No forzar aquí entry.bar.scrollLeft = ctn.scrollLeft en cada
            // tick: como este update() también corre en la fase de captura
            // del 'scroll' de window (más abajo), se ejecuta ANTES que el
            // listener propio de la barra (bind()) y le pisaba la posición
            // justo cuando el usuario la arrastraba, revirtiéndola sola. La
            // sincronización real ya la hacen los listeners de bind().
        });

        bars.forEach(function (entry, ctn) {
            if (!document.body.contains(ctn)) {
                entry.bar.remove();
                bars.delete(ctn);
            } else if (!seen.has(ctn)) {
                entry.bar.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', update);
    document.addEventListener('livewire:navigated', update);
    document.addEventListener('livewire:updated', update);
    window.addEventListener('resize', update);
    window.addEventListener('scroll', update, true);

    new MutationObserver(update).observe(document.documentElement, {
        childList: true,
        subtree: true,
    });

    update();
    setInterval(update, 500);
})();
