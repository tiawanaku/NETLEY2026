import { test, expect } from '@playwright/test';

/**
 * Recorrido QA de punta a punta del panel y la portada.
 * Todo lo que se crea va marcado como "Qaprueba" / @qa-e2e.test y se borra
 * al final (global-teardown.js). Credenciales: QA_USUARIO / QA_CLAVE.
 */

const USUARIO = process.env.QA_USUARIO ?? 'admin';
const CLAVE = process.env.QA_CLAVE ?? 'password';

// Datos únicos por ejecución para no chocar con la regla de duplicados.
// Los nombres solo admiten letras: el código de la ejecución va en letras.
const RUN = String(Date.now()).slice(-7);
const RUN_LETRAS = [...RUN].map((d) => 'abcdefghij'[d]).join('');
const abogado = {
    nombres: 'Qaprueba',
    apellido: `Abogado ${RUN_LETRAS}`,
    ci: `8${RUN}`,
    telefono: `7${RUN}`,
    whatsapp: `6${RUN}`,
    correo: `abogado.${RUN}@qa-e2e.test`,
};
const consulta = {
    nombres: 'Qaprueba Consulta',
    apellido: `Talleres ${RUN_LETRAS}`,
    telefonoAr: `91${RUN}0`,
    correo: `consulta.${RUN}@qa-e2e.test`,
    ci: `9${RUN}`,
};

test.describe.configure({ mode: 'serial' });

/** @type {import('@playwright/test').Page} */
let page;
const erroresConsola = [];

test.beforeAll(async ({ browser }) => {
    page = await browser.newPage();
    page.on('console', (m) => m.type() === 'error' && erroresConsola.push(`${m.text()} @ ${page.url()}`));
    page.on('pageerror', (e) => erroresConsola.push(`${e.message} @ ${page.url()}`));

    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /Usuario/ }).fill(USUARIO);
    await page.getByRole('textbox', { name: /Contraseña/ }).fill(CLAVE);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page.getByRole('heading', { name: 'Escritorio' })).toBeVisible({ timeout: 60_000 });
});

test.afterAll(async () => {
    await page?.close();
});

/** Elige una opción en un select de Filament con buscador (sin cerrar modales). */
async function elegirOpcion(contenedor, combobox, busqueda, texto = busqueda) {
    await combobox.click();
    await page.keyboard.type(busqueda, { delay: 40 });
    const opcion = contenedor.locator('[role=option]:visible', { hasText: texto }).first();
    await opcion.waitFor();
    await opcion.click();
}

async function captura(nombre) {
    await test.info().attach(nombre, { body: await page.screenshot(), contentType: 'image/png' });
}

test('1. Agregar personal: sin Cargo principal ni Contrato vigente y con código +591', async () => {
    await page.goto('/admin/personal/create');

    await expect(page.getByText('Cargo principal')).toHaveCount(0);
    await expect(page.getByText(/contrato vigente/i)).toHaveCount(0);
    await expect(page.getByRole('combobox', { name: 'Código de país' }).first()).toHaveText(/\+591 \(Bolivia\)/);

    await page.getByRole('textbox', { name: 'Nombres*' }).fill(abogado.nombres);
    await page.getByRole('textbox', { name: 'Apellido paterno*' }).fill(abogado.apellido);
    await page.getByRole('textbox', { name: 'Carnet de identidad*' }).fill(abogado.ci);
    await page.getByRole('textbox', { name: 'Fecha de nacimiento*' }).fill('1990-05-10');
    await page.getByRole('textbox', { name: 'Teléfono', exact: true }).fill(abogado.telefono);
    await page.getByRole('textbox', { name: 'WhatsApp', exact: true }).fill(abogado.whatsapp);
    await page.getByRole('textbox', { name: 'Correo*' }).fill(abogado.correo);
    await page.getByRole('combobox', { name: 'Rol de acceso*' }).selectOption({ label: 'Abogado' });
    await page.getByRole('combobox', { name: 'Estado*' }).selectOption({ label: 'Habilitado' });
    await elegirOpcion(page, page.getByRole('combobox', { name: 'Profesión' }), 'Abog', /^\s*Abogado\s*$/);
    await page.getByRole('heading', { name: 'Agregar personal' }).click();
    await expect(page.getByText('Especialidades', { exact: true })).toBeVisible();
    await captura('personal-formulario');

    await page.getByRole('button', { name: 'Guardar' }).click();
    await page.getByRole('button', { name: 'Agregar personal' }).click();
    await expect(page).toHaveURL(/\/admin\/personal$/);

    await expect(page.locator('table')).toContainText(`+591 ${abogado.telefono}`);
    await expect(page.locator('img[src*="flagcdn"]')).toHaveCount(0);
    await captura('personal-listado');
});

test('2. Personal: no deja registrar teléfono, WhatsApp ni correo repetidos', async () => {
    await page.goto('/admin/personal/create');
    await page.getByRole('textbox', { name: 'Nombres*' }).fill('Qaprueba');
    await page.getByRole('textbox', { name: 'Apellido paterno*' }).fill(`Duplicado ${RUN_LETRAS}`);
    await page.getByRole('textbox', { name: 'Carnet de identidad*' }).fill(`7${RUN}`);
    await page.getByRole('textbox', { name: 'Fecha de nacimiento*' }).fill('1991-01-01');
    // Teléfono igual; WhatsApp ajeno usado como teléfono; correo en mayúsculas.
    await page.getByRole('textbox', { name: 'Teléfono', exact: true }).fill(abogado.telefono);
    await page.getByRole('textbox', { name: 'WhatsApp', exact: true }).fill(abogado.whatsapp);
    await page.getByRole('textbox', { name: 'Correo*' }).fill(abogado.correo.toUpperCase());
    await page.getByRole('combobox', { name: 'Rol de acceso*' }).selectOption({ label: 'Secretaria' });
    await page.getByRole('combobox', { name: 'Estado*' }).selectOption({ label: 'Habilitado' });

    await page.getByRole('button', { name: 'Guardar' }).click();
    await page.getByRole('button', { name: 'Agregar personal' }).click();

    const esperado = `${abogado.nombres} ${abogado.apellido}`;
    await expect(page.getByText(`Este teléfono ya está registrado para otro personal (${esperado}).`)).toBeVisible();
    await expect(page.getByText(`Este número de WhatsApp ya está registrado para otro personal (${esperado}).`)).toBeVisible();
    await expect(page.getByText(`Este correo electrónico ya está registrado para otro personal (${esperado}).`)).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/personal\/create$/);
    await captura('personal-duplicado-bloqueado');
});

test('3. Editar personal: tampoco muestra Cargo principal ni Contrato vigente', async () => {
    await page.goto('/admin/personal');
    await page.locator('tr', { hasText: abogado.apellido }).getByRole('link').first().click();
    await expect(page).toHaveURL(/\/admin\/personal\/\d+\/edit$/);
    await expect(page.getByText('Cargo principal')).toHaveCount(0);
    await expect(page.getByText(/contrato vigente/i)).toHaveCount(0);
    await expect(page.getByRole('textbox', { name: 'Teléfono', exact: true })).toHaveValue(abogado.telefono);
    await captura('personal-editar');
});

test('4. Crear consulta con código +54 y origen Talleres con detalle', async () => {
    await page.goto('/admin/consultas/create');
    await page.getByRole('textbox', { name: 'Nombres*' }).fill(consulta.nombres);
    await page.getByRole('textbox', { name: 'Apellido paterno' }).fill(consulta.apellido);
    await elegirOpcion(page, page.getByRole('combobox', { name: 'Código de país' }).first(), 'argen', '+54');
    await expect(page.getByRole('combobox', { name: 'Código de país' }).first()).toHaveText(/\+54 \(Argentina\)/);
    await page.getByRole('textbox', { name: 'Teléfono', exact: true }).fill(consulta.telefonoAr);
    await page.getByRole('textbox', { name: 'Correo' }).fill(consulta.correo);
    await page.locator('#form\\.consulta').fill('Consulta QA E2E: herencia familiar.');
    await page.getByRole('combobox', { name: 'Estado*' }).selectOption({ label: 'Pendiente' });

    const origen = page.getByRole('combobox', { name: 'Origen*' });
    await origen.fill('Talleres');
    await origen.blur();
    const detalle = page.getByRole('textbox', { name: /Detalle del taller/ });
    await expect(detalle).toBeVisible();
    await detalle.fill('Taller QA colegio San Calixto');
    await captura('consulta-origen-talleres');

    await page.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByText('Creado')).toBeVisible();
    await expect(page.locator('table')).toContainText(`+54 ${consulta.telefonoAr}`);
    await captura('consulta-listado');
});

test('5. Filtro de Teléfono en Consultas: con código, sin código y sin resultados', async () => {
    const filtrar = async (valor) => {
        await page.goto(`/admin/consultas?filters[telefono][valor]=${encodeURIComponent(valor)}`);
        await expect(page.getByText(`Teléfono: ${valor}`)).toBeVisible();
    };

    await filtrar(`+54 ${consulta.telefonoAr.slice(0, 6)}`);
    await expect(page.locator('table tbody')).toContainText(consulta.nombres);
    await captura('filtro-telefono-con-codigo');

    await filtrar(consulta.telefonoAr.slice(-6));
    await expect(page.locator('table tbody')).toContainText(consulta.nombres);

    // Sin resultados Filament muestra el estado vacío en lugar de filas.
    await filtrar('70000000999');
    await expect(page.locator('main')).not.toContainText(consulta.nombres);
});

test('6. Agendar en la consulta con Denunciado y Denuncias', async () => {
    await page.goto('/admin/consultas');
    await page.locator('tr', { hasText: consulta.apellido }).getByRole('link', { name: /Ver/ }).click();
    await page.getByRole('button', { name: /^Agendar$/ }).click();

    const modal = page.getByRole('dialog').filter({ hasText: 'Denuncias' }).last();
    const cerrarLista = () => modal.getByRole('heading').first().click();

    await elegirOpcion(modal, modal.getByRole('combobox', { name: /Abogado/ }), 'Qaprueba', abogado.apellido);
    await cerrarLista();
    await modal.locator('input[type=date]').first().fill('2026-12-20');
    await modal.locator('input[type=time]').first().fill('10:30');
    await modal.getByRole('textbox', { name: 'Denunciado' }).fill('Pedro QA Denunciado');
    for (const delito of ['Estafa', 'Robo agravado']) {
        await elegirOpcion(modal, modal.getByRole('combobox', { name: 'Denuncias' }), delito);
        await cerrarLista();
    }
    await captura('agendar-denuncias');

    await modal.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByText('Cita agendada')).toBeVisible();

    await page.reload();
    await expect(page.getByRole('textbox', { name: 'Denunciado' })).toHaveValue('Pedro QA Denunciado');
    await expect(page.getByRole('combobox', { name: 'Denuncias' })).toContainText('Estafa');
    await expect(page.getByRole('combobox', { name: 'Denuncias' })).toContainText('Robo agravado');
});

test('7. Cliente Ejecutivo: Delitos en lugar de Tipo de proceso y Denuncias precargadas', async () => {
    await page.locator('#fi-main-content').getByRole('button', { name: 'Cliente Ejecutivo' }).click();
    const wizard = page.getByRole('dialog').filter({ hasText: 'Validación de datos' }).last();

    await expect(wizard.locator('[id$="telefono"]').first()).toHaveValue(consulta.telefonoAr);
    await wizard.locator('[id$=".ci"]').fill(consulta.ci);
    await wizard.locator('[id$="fecha_nacimiento"]').fill('1985-03-15');
    await wizard.getByRole('button', { name: 'Siguiente' }).click();

    await expect(wizard.getByRole('combobox', { name: /^Delitos/ })).toBeVisible();
    await expect(wizard.getByRole('combobox', { name: /Materia legal/ })).toHaveCount(0);
    await expect(wizard.getByRole('textbox', { name: 'Denunciado' })).toHaveValue('Pedro QA Denunciado');
    await expect(wizard.getByRole('combobox', { name: 'Denuncias' })).toContainText('Estafa');

    await elegirOpcion(wizard, wizard.getByRole('combobox', { name: /^Delitos/ }), 'Estafa');
    await wizard.getByText('Cliente Ejecutivo', { exact: true }).first().click();
    await expect(wizard.getByText('Materia legal: Penal.')).toBeVisible();
    await wizard.locator('[id*="duracion"]').first().fill('6');
    await captura('wizard-proceso-delitos');
    await wizard.getByRole('button', { name: 'Siguiente' }).click();

    await wizard.locator('[id$="iguala"]').fill('3000');
    await wizard.locator('[id$="anticipo"]').fill('500');
    // "Forma de pago" aparece recién cuando el anticipo se confirma (al salir del campo).
    await page.keyboard.press('Tab');
    await expect(wizard.getByText('Anticipo: Bs. 500.00')).toBeVisible();
    await wizard.locator('select[id*="forma"]').first().selectOption({ index: 1 });
    await captura('wizard-pago');
    await wizard.getByRole('button', { name: 'Guardar' }).click();
    await expect(page.getByText('Consulta ascendida a caso')).toBeVisible();
});

test('8. Filtro de Correo en Clientes (sin distinguir mayúsculas)', async () => {
    const valor = consulta.correo.split('@')[0].toUpperCase();
    await page.goto(`/admin/clientes?filters[correo][valor]=${encodeURIComponent(valor)}`);
    await expect(page.getByText(`Correo: ${valor}`)).toBeVisible();
    await expect(page.locator('table tbody')).toContainText(`+54 ${consulta.telefonoAr}`);
    await captura('clientes-filtro-correo');
});

test('9. Portada en celular: selector de código y bloqueo de consulta repetida', async () => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/#consulta');
    const form = page.locator('#consulta form');
    await form.scrollIntoViewIfNeeded();

    const codigo = form.locator('select').first();
    await expect(codigo.locator('option:checked')).toHaveText('+591 (Bolivia)');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

    await form.locator('#c-nombres').fill('Qaprueba Portada');
    await codigo.selectOption({ label: '+54 (Argentina)' });
    await form.locator('#c-telefono').fill(consulta.telefonoAr);
    await form.locator('#c-consulta').fill('Prueba QA E2E de duplicado desde la portada.');
    const [a, b] = (await form.locator('label[for=c-captcha]').innerText()).match(/(\d+)\s*\+\s*(\d+)/).slice(1).map(Number);
    await form.locator('#c-captcha').fill(String(a + b));
    await captura('portada-movil');

    await form.getByRole('button', { name: 'Enviar consulta' }).click();
    await expect(page.getByText('Ya existe una consulta registrada con este teléfono. Nos pondremos en contacto contigo.')).toBeVisible();
    await captura('portada-duplicado');
    await page.setViewportSize({ width: 1440, height: 900 });
});

test('10. Sin errores en la consola del navegador', async () => {
    expect(erroresConsola, erroresConsola.join('\n')).toEqual([]);
});
