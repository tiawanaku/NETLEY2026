<div
    x-data="{
        vista: 'mes',
        anchor: '{{ $hoy->toDateString() }}',
        hoyClave: '{{ $hoy->toDateString() }}',
        citas: @js($citasPorDia),
        meses: ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'],
        diasCortos: ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'],
        parse(clave) {
            const [y, m, d] = clave.split('-').map(Number);
            return new Date(y, m - 1, d);
        },
        clave(fecha) {
            const y = fecha.getFullYear();
            const m = String(fecha.getMonth() + 1).padStart(2, '0');
            const d = String(fecha.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        },
        sumar(fecha, dias) {
            const copia = new Date(fecha);
            copia.setDate(copia.getDate() + dias);
            return copia;
        },
        mover(direccion) {
            const actual = this.parse(this.anchor);
            if (this.vista === 'mes') {
                actual.setMonth(actual.getMonth() + direccion);
            } else if (this.vista === 'semana') {
                actual.setDate(actual.getDate() + (7 * direccion));
            } else {
                actual.setDate(actual.getDate() + direccion);
            }
            this.anchor = this.clave(actual);
        },
        irHoy() {
            this.anchor = this.hoyClave;
        },
        citasDe(clave) {
            return this.citas[clave] || [];
        },
        diasDelMes() {
            const actual = this.parse(this.anchor);
            const inicioMes = new Date(actual.getFullYear(), actual.getMonth(), 1);
            let cursor = this.sumar(inicioMes, -inicioMes.getDay());
            const dias = [];
            for (let i = 0; i < 42; i++) {
                const clave = this.clave(cursor);
                dias.push({
                    clave,
                    numero: cursor.getDate(),
                    enMes: cursor.getMonth() === inicioMes.getMonth(),
                    esHoy: clave === this.hoyClave,
                    citas: this.citasDe(clave),
                });
                cursor = this.sumar(cursor, 1);
            }
            return dias;
        },
        diasDeLaSemana() {
            const actual = this.parse(this.anchor);
            const inicio = this.sumar(actual, -actual.getDay());
            const dias = [];
            for (let i = 0; i < 7; i++) {
                const fecha = this.sumar(inicio, i);
                const clave = this.clave(fecha);
                dias.push({
                    clave,
                    etiqueta: this.diasCortos[fecha.getDay()] + ' ' + fecha.getDate(),
                    esHoy: clave === this.hoyClave,
                    citas: this.citasDe(clave),
                });
            }
            return dias;
        },
        diaActual() {
            return { clave: this.anchor, citas: this.citasDe(this.anchor) };
        },
        titulo() {
            const actual = this.parse(this.anchor);
            if (this.vista === 'mes') {
                return this.meses[actual.getMonth()] + ' ' + actual.getFullYear();
            }
            if (this.vista === 'semana') {
                const inicio = this.sumar(actual, -actual.getDay());
                const fin = this.sumar(inicio, 6);
                return inicio.getDate() + ' – ' + fin.getDate() + ' de ' + this.meses[fin.getMonth()] + ', ' + fin.getFullYear();
            }
            return this.diasCortos[actual.getDay()] + ' ' + actual.getDate() + ' de ' + this.meses[actual.getMonth()] + ', ' + actual.getFullYear();
        },
    }"
    style="display:flex;flex-direction:column;gap:8px;"
>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <div style="display:flex;align-items:center;gap:6px;">
            <button type="button" x-on:click="mover(-1)" style="padding:4px 9px;font-size:12px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;">&larr;</button>
            <button type="button" x-on:click="irHoy()" style="padding:4px 9px;font-size:12px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;">Hoy</button>
            <button type="button" x-on:click="mover(1)" style="padding:4px 9px;font-size:12px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;">&rarr;</button>
            <span style="font-size:13px;font-weight:600;color:#1a2537;text-transform:capitalize;margin-left:6px;" x-text="titulo()"></span>
        </div>

        <div style="display:inline-flex;border:1px solid #d1d5db;border-radius:8px;overflow:hidden;">
            <template x-for="opcion in [['mes','Mes'],['semana','Semana'],['dia','Día']]" :key="opcion[0]">
                <button
                    type="button"
                    x-on:click="vista = opcion[0]"
                    :style="'padding:4px 11px;font-size:12px;border:none;cursor:pointer;background:' + (vista === opcion[0] ? '#1a2537' : '#fff') + ';color:' + (vista === opcion[0] ? '#fff' : '#374151') + ';'"
                    x-text="opcion[1]"
                ></button>
            </template>
        </div>
    </div>

    <div x-show="vista === 'mes'" style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px;">
        <div style="display:grid;grid-template-columns:repeat(7,minmax(70px,1fr));gap:1px;background:#e5e7eb;min-width:560px;">
            <template x-for="nombre in diasCortos" :key="nombre">
                <div style="background:#1a2537;color:#fff;padding:5px;text-align:center;font-size:11px;font-weight:600;" x-text="nombre"></div>
            </template>
            <template x-for="dia in diasDelMes()" :key="dia.clave">
                <div :style="'background:' + (dia.esHoy ? '#eff6ff' : '#fff') + ';min-height:60px;padding:4px;' + (dia.enMes ? '' : 'opacity:.4;')">
                    <div :style="'font-size:11px;font-weight:' + (dia.esHoy ? '700' : '600') + ';color:' + (dia.esHoy ? '#2563eb' : '#374151') + ';margin-bottom:2px;'" x-text="dia.numero"></div>
                    <template x-for="item in dia.citas" :key="item.hora + item.nombres">
                        <div
                            style="font-size:9px;line-height:1.25;background:#fee2e2;border:1px solid #fca5a5;border-radius:3px;padding:1px 3px;margin-bottom:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            :title="item.hora + ' — ' + item.nombres"
                            x-text="item.hora + ' ' + item.nombres"
                        ></div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <div x-show="vista === 'semana'" style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px;">
        <div style="display:grid;grid-template-columns:repeat(7,minmax(110px,1fr));gap:1px;background:#e5e7eb;min-width:770px;">
            <template x-for="dia in diasDeLaSemana()" :key="dia.clave">
                <div :style="'background:' + (dia.esHoy ? '#eff6ff' : '#fff') + ';min-height:150px;padding:5px;'">
                    <div :style="'font-size:11px;font-weight:' + (dia.esHoy ? '700' : '600') + ';color:' + (dia.esHoy ? '#2563eb' : '#374151') + ';margin-bottom:4px;text-transform:capitalize;'" x-text="dia.etiqueta"></div>
                    <template x-for="item in dia.citas" :key="item.hora + item.nombres">
                        <div style="font-size:10px;line-height:1.3;background:#fee2e2;border:1px solid #fca5a5;border-radius:4px;padding:2px 4px;margin-bottom:2px;">
                            <div style="font-weight:600;" x-text="item.hora"></div>
                            <div x-text="item.nombres"></div>
                        </div>
                    </template>
                    <div x-show="dia.citas.length === 0" style="font-size:10px;color:#9ca3af;">—</div>
                </div>
            </template>
        </div>
    </div>

    <div x-show="vista === 'dia'" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
        <template x-for="item in diaActual().citas" :key="item.hora + item.nombres">
            <div style="display:flex;gap:12px;padding:8px 12px;border-bottom:1px solid #f3f4f6;font-size:12px;">
                <div style="width:46px;font-weight:700;color:#991b1b;flex-shrink:0;" x-text="item.hora"></div>
                <div x-text="item.nombres"></div>
            </div>
        </template>
        <div x-show="diaActual().citas.length === 0" style="padding:14px;text-align:center;color:#9ca3af;font-size:12px;">
            No hay citas agendadas este día.
        </div>
    </div>
</div>
