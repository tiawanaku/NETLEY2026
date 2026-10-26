import { execSync } from 'node:child_process';

/** Al terminar, borra los registros marcados como QA (ver limpiar-datos-qa.php). */
export default function globalTeardown() {
    execSync('php tests/e2e/limpiar-datos-qa.php', { stdio: 'inherit' });
}
