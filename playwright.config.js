import { defineConfig } from '@playwright/test';

/**
 * Pruebas QA de navegador (tests/e2e). Requieren la app corriendo
 * (php artisan serve) y MySQL. Usan el Chrome instalado en la PC.
 *
 *   npm run qa          -> corre las pruebas sin ventana
 *   npm run qa:ver      -> corre las pruebas mostrando el navegador
 *   npm run qa:ui       -> modo interactivo (paso a paso, con capturas)
 *   npm run qa:reporte  -> abre el reporte HTML del último run
 */
export default defineConfig({
    testDir: './tests/e2e',
    timeout: 120_000,
    expect: { timeout: 15_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list'], ['html', { outputFolder: 'tests/e2e/reporte', open: 'never' }]],
    outputDir: 'tests/e2e/resultados',
    globalTeardown: './tests/e2e/global-teardown.js',
    use: {
        baseURL: process.env.QA_URL ?? 'http://127.0.0.1:8000',
        channel: 'chrome',
        viewport: { width: 1440, height: 900 },
        locale: 'es-BO',
        screenshot: 'on',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
        launchOptions: { slowMo: Number(process.env.QA_LENTO ?? 0) },
    },
});
