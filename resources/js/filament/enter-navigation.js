(function () {
    // Campos marcados con data-enter-nav="true" (inputs/selects nativos) o
    // envueltos en un contenedor data-enter-nav-field="true" (selects
    // ->searchable(), que Filament renderiza como un botón + panel Alpine
    // cargado aparte, no como un <select> nativo — extraInputAttributes() no
    // llega a ese botón, así que se marca el contenedor y se resuelve el
    // botón real en tiempo de ejecución). Ver PersonalForm::configure.
    //
    // Al presionar Enter en uno de ellos, salta al siguiente campo VACÍO de
    // la cadena (saltándose los que ya tienen valor, p. ej. los que traen un
    // default precargado); si no queda ninguno vacío por delante, dispara el
    // botón "Crear" — replica el atajo de teclado que tenía agragar_pp.php.
    var PLACEHOLDER = 'Seleccione una opción';

    // Campos marcados con data-enter-nav-live="true" (ej. "Estado", "Materia
    // legal") disparan un ->live() que revela/oculta OTROS campos de la
    // cadena (ej. "Delito", "Especialidades") tras una ida y vuelta a
    // Livewire. Ni un setTimeout corto ni el hook Livewire.hook('commit')
    // (su succeed() nunca llegó a dispararse en pruebas reales, aunque la
    // petición sí terminaba en 200) son confiables acá.
    //
    // Antes esto sondeaba el DOM y avanzaba en cuanto dos lecturas seguidas
    // (150ms aparte) daban el mismo número de campos — pero si la ida y
    // vuelta real tardaba MÁS de 300ms (normal en desarrollo local), las
    // DOS lecturas caían antes de que el campo nuevo apareciera, y el
    // sondeo concluía "estable" de forma prematura, saltándose ese campo.
    // Por eso ahora se espera un mínimo fijo generoso ANTES de siquiera
    // empezar a sondear — para cuando se pregunta "¿sigue cambiando?", la
    // ida y vuelta normal ya debería haber terminado.
    function waitForStableSequence(maxWaitMs) {
        var minWaitMs = 500;

        return new Promise(function (resolve) {
            setTimeout(function () {
                var waited = minWaitMs;
                var lastLength = null;

                function tick() {
                    var length = getSequence().length;

                    if (lastLength !== null && length === lastLength) {
                        resolve();

                        return;
                    }

                    lastLength = length;
                    waited += 200;

                    if (waited >= maxWaitMs) {
                        resolve();

                        return;
                    }

                    setTimeout(tick, 200);
                }

                tick();
            }, minWaitMs);
        });
    }

    function resolveField(marker) {
        if (marker.hasAttribute('data-enter-nav')) {
            return marker;
        }

        // data-enter-nav-field: el contenedor de un select buscable; el botón
        // real (role="combobox") lo renderiza el componente Alpine adentro.
        return marker.querySelector('select, button[role="combobox"]');
    }

    function getSequence() {
        return Array.from(document.querySelectorAll('[data-enter-nav="true"], [data-enter-nav-field="true"]'))
            .map(resolveField)
            .filter(function (el) {
                return el && el.offsetParent !== null && !el.disabled;
            });
    }

    function isComboboxTrigger(el) {
        return el.tagName === 'BUTTON' && el.getAttribute('role') === 'combobox';
    }

    function isEmpty(el) {
        if (isComboboxTrigger(el)) {
            var text = el.textContent.trim();

            return text === '' || text === PLACEHOLDER;
        }

        return !el.value || el.value.trim() === '';
    }

    function clickCreateButton() {
        var buttons = Array.from(document.querySelectorAll('button'));

        // data-enter-submit: marca fija en el botón de Crear/Guardar, puesta
        // vía ->extraAttributes() en la página (ver CreatePersonal/
        // CreateConsulta). No depende del texto del botón, que cambia de
        // una página a otra ("Crear", "Guardar", etc.) y antes rompía esto.
        var submitBtn = buttons.find(function (b) {
            return b.hasAttribute('data-enter-submit') && b.offsetParent !== null;
        });

        if (!submitBtn) {
            submitBtn = buttons.find(function (b) {
                return b.type === 'submit' && b.offsetParent !== null;
            });
        }

        if (!submitBtn) {
            submitBtn = buttons.find(function (b) {
                var text = b.textContent.trim();

                return (text === 'Crear' || text === 'Guardar') && b.offsetParent !== null;
            });
        }

        if (submitBtn) {
            submitBtn.click();
        }
    }

    function closeAnyOpenCombobox() {
        // En un combobox de selección múltiple (ej. "Profesión"), Filament
        // deja el panel ABIERTO después de elegir una opción con Enter (para
        // poder seguir agregando más), así que antes de avanzar al siguiente
        // campo hay que cerrarlo — si no, queda flotando encima del campo al
        // que se avanza. Se dispara Escape sobre el propio panel para que lo
        // cierre Filament con su propio método (closeDropdown()), en vez de
        // reimplementar el cierre a mano.
        //
        // bubbles:false a propósito: el manejador de Filament que cierra el
        // panel está puesto directamente en el panel (dropdown.addEventListener
        // en su JS), así que igual lo recibe aunque no burbujee. Si burbujeara
        // (bubbles:true), este mismo Escape sintético seguía subiendo hasta el
        // manejador que cierra el MODAL completo con Escape, cerrando de golpe
        // todo el formulario de "Agendar" en vez de solo el desplegable — eso
        // es justo lo que se reportó como "el Enter guarda/cierra directo".
        var openTrigger = document.querySelector('button[role="combobox"][aria-expanded="true"]');

        if (!openTrigger) {
            return;
        }

        var openPanelId = openTrigger.getAttribute('aria-controls');
        var panel = openPanelId && document.getElementById(openPanelId);

        if (panel) {
            panel.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: false, cancelable: true }));
        }
    }

    function advanceFrom(targetId) {
        // Se busca por id en vez de por referencia al elemento original: si
        // el campo era reactivo (->live()), Livewire puede haber
        // re-renderizado (morphdom) ese nodo al confirmar la selección, y la
        // referencia vieja quedaría desconectada del DOM.
        closeAnyOpenCombobox();

        var sequence = getSequence();
        var idx = sequence.findIndex(function (el) {
            return el.id === targetId;
        });

        if (idx === -1) {
            return;
        }

        var next = null;

        for (var i = idx + 1; i < sequence.length; i++) {
            if (isEmpty(sequence[i])) {
                next = sequence[i];
                break;
            }
        }

        if (next) {
            next.focus();

            try {
                if (next.select) {
                    next.select();
                }
            } catch (err) {
                // ignore: not every input type supports .select()
            }

            return;
        }

        clickCreateButton();
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') {
            return;
        }

        // Modal de confirmación marcado con data-enter-confirm: Enter confirma.
        var confirmModal = document.querySelector('[data-enter-confirm="true"]');

        if (confirmModal && confirmModal.getClientRects().length > 0) {
            // El botón de confirmar NO es siempre el primero dentro de
            // .fi-modal-footer-actions — en modales con botón de cancelar
            // visible (ej. "Revisar"), Filament lo pone ANTES que el de
            // confirmar, al revés de lo esperado. Se identifica por sus
            // propias características (type="submit" y color primario), no
            // por su posición ni por su texto.
            var confirmBtn = confirmModal.querySelector('.fi-modal-footer-actions button[type="submit"]')
                || confirmModal.querySelector('.fi-modal-footer-actions button.fi-color-primary')
                || confirmModal.querySelector('.fi-modal-footer-actions button');

            if (confirmBtn) {
                e.preventDefault();
                confirmBtn.click();
            }

            return;
        }

        var target = e.target;

        // Caso 1: Enter dentro de un panel de combobox ABIERTO (buscando o
        // con una opción resaltada). No se debe interceptar aquí: hay que
        // dejar que Filament confirme la opción con su propio manejador.
        // Solo se detecta a cuál campo pertenece (vía aria-controls) para
        // continuar la cadena una vez que la selección ya se aplicó.
        var openPanel = target.closest('.fi-dropdown-panel[role="listbox"]');

        if (openPanel) {
            var trigger = document.querySelector('[aria-controls="' + openPanel.id + '"]');

            if (trigger && trigger.id) {
                var triggerId = trigger.id;
                var isLive = trigger.closest('[data-enter-nav-live="true"]') !== null;

                if (isLive) {
                    waitForStableSequence(1500).then(function () {
                        advanceFrom(triggerId);
                    });
                } else {
                    setTimeout(function () {
                        advanceFrom(triggerId);
                    }, 150);
                }
            }

            return;
        }

        // Caso 2: Enter sobre el botón disparador de un combobox TODAVÍA
        // cerrado. A diferencia de un <select> nativo, Filament NO abre este
        // combobox con Enter (solo con clic, espacio o flechas — ver
        // handleSelectButtonKeydown en el JS de Filament, donde Enter es un
        // no-op a propósito), así que sin esto la tecla no hacía nada. Se
        // simula el clic para abrirlo; una vez abierto, el usuario navega
        // con flechas y el Caso 1 se encarga de seleccionar + avanzar.
        if (isComboboxTrigger(target)) {
            e.preventDefault();
            target.click();

            return;
        }

        if (!target || target.getAttribute('data-enter-nav') !== 'true') {
            return;
        }

        // Caso 3: campo de texto/fecha/select nativo — comportamiento normal.
        e.preventDefault();

        // No se avanza si el campo es obligatorio y está vacío (o no cumple
        // su patrón, ej. "solo números"): se usa la validación nativa del
        // navegador (Filament ya pone required/pattern en el <input>), que
        // además muestra el aviso nativo y mantiene el foco ahí.
        if (target.checkValidity && !target.checkValidity()) {
            target.reportValidity();

            return;
        }

        var targetId = target.id;

        if (target.hasAttribute('data-enter-nav-live')) {
            waitForStableSequence(1500).then(function () {
                advanceFrom(targetId);
            });
        } else {
            setTimeout(function () {
                advanceFrom(targetId);
            }, 75);
        }
    }, true);
    // ^ fase de CAPTURA: crucial para el Caso 1 — hay que leer a qué panel/
    // trigger pertenece el Enter ANTES de que el propio manejador de
    // Filament (en el <li> de la opción) corra y cierre/desmonte el panel,
    // porque después de eso target.closest(...) ya no encuentra el
    // ancestro (el nodo queda desconectado del árbol).

    // Al cambiar de paso en un Wizard, se pone el foco en el primer campo
    // vacío del paso que recién se muestra, para poder seguir encadenando
    // Enter sin tener que hacer clic manualmente para arrancar cada paso.
    //
    // "Siguiente" valida y re-renderiza por una ida y vuelta real a
    // Livewire (ver wire:target="callSchemaComponentMethod(...)" en el
    // botón) — un setTimeout corto y fijo corre antes de que el paso nuevo
    // exista/sea visible, así que se usa la misma espera robusta que ya
    // resolvió este mismo problema para los selects en cascada.
    function enfocarPrimerVacioDelPaso() {
        waitForStableSequence(1500).then(function () {
            var primerVacio = getSequence().find(function (el) {
                return isEmpty(el);
            });

            if (!primerVacio) {
                return;
            }

            primerVacio.focus();

            try {
                if (primerVacio.select) {
                    primerVacio.select();
                }
            } catch (err) {
                // ignore: no todos los tipos de input soportan .select()
            }
        });
    }

    // Los eventos de Livewire que dispara el Wizard al cambiar de paso
    // ('next-wizard-step', 'go-to-wizard-step') resultaron poco fiables para
    // enganchar esto (no siempre llegan a Livewire.on/window en la práctica
    // — mismo tipo de problema ya visto antes con Livewire.hook('commit')).
    // Es más simple y confiable detectar el propio clic en "Siguiente" o
    // "Anterior", igual que el resto de este archivo ya hace con otros
    // botones por su texto.
    document.addEventListener('click', function (e) {
        var boton = e.target.closest('button');

        if (boton && (boton.textContent.trim() === 'Siguiente' || boton.textContent.trim() === 'Anterior')) {
            enfocarPrimerVacioDelPaso();
        }
    });
})();
