// Lista desplegable del campo Ciudad (App\Filament\Admin\Forms\Components\CampoCiudad):
// muestra los departamentos (y sugerencias), filtra mientras se escribe y,
// si nada coincide, deja lo escrito a mano. Flechas para moverse, Enter para
// elegir la opción marcada (si no hay ninguna marcada, Enter sigue con la
// navegación normal entre campos), Escape para cerrar.
document.addEventListener('alpine:init', function () {
    window.Alpine.data('netleyCombo', function (opciones, id) {
        return {
            opciones: opciones,
            visibles: opciones,
            abierto: false,
            activa: -1,

            input() {
                return document.getElementById(id);
            },

            normalizar(texto) {
                return (texto || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
            },

            abrir() {
                this.abierto = true;
                this.filtrar(true);
            },

            cerrar() {
                this.abierto = false;
                this.activa = -1;
            },

            // Al abrir se muestra todo; al escribir, solo lo que coincide.
            filtrar(todo) {
                var q = this.normalizar(this.input()?.value);
                var self = this;
                var esExacto = this.opciones.some(function (o) { return self.normalizar(o) === q; });

                this.visibles = (todo === true && (q === '' || esExacto))
                    ? this.opciones
                    : this.opciones.filter(function (o) { return self.normalizar(o).includes(q); });
                this.activa = -1;

                if (todo !== true) {
                    this.abierto = true;
                }
            },

            elegir(opcion) {
                var input = this.input();

                if (input) {
                    input.value = opcion;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }

                this.$nextTick(() => this.cerrar());
            },

            tecla(e) {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();

                    if (!this.abierto) {
                        this.abrir();
                    }

                    if (this.visibles.length) {
                        var paso = e.key === 'ArrowDown' ? 1 : -1;
                        this.activa = (this.activa + paso + this.visibles.length) % this.visibles.length;
                    }

                    return;
                }

                if (e.key === 'Enter' && this.abierto && this.activa >= 0 && this.visibles[this.activa]) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.elegir(this.visibles[this.activa]);

                    return;
                }

                if (e.key === 'Escape' || e.key === 'Tab' || e.key === 'Enter') {
                    this.cerrar();
                }
            },
        };
    });
});
