<?php

namespace App\Support;

use App\Models\Personal;

/**
 * Catálogo de especialidades del personal (legacy: agragar_pp.php), agrupado
 * por profesión. No confundir con App\Enums\Especialidad, que es la materia
 * legal (Civil/Penal/Familia/Laboral) usada en Casos, Delitos y Llamadas.
 */
class PersonalEspecialidades
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        return [
            'Legales' => [
                'CIVIL' => 'Civil',
                'IMPOSITIVO' => 'Impositivo',
                'MINERO' => 'Minero',
                'PENAL' => 'Penal',
                'TRIBUTARIO' => 'Tributario',
                'LABORAL' => 'Laboral',
                'SEGURIDAD SOCIAL' => 'Seguridad Social',
                'FAMILIA' => 'Familia',
                'SIN ESPECIALIDAD' => 'Sin especialidad',
            ],
            'Psicología' => [
                'CLINICA' => 'Psicología clínica',
                'FORENSE' => 'Psicología forense',
                'EDUCATIVA' => 'Psicología educativa',
                'SOCIAL' => 'Psicología social',
                'FAMILIAR' => 'Psicología familiar',
            ],
            'Médicas' => [
                'CARDIOLOGIA' => 'Cardiología',
                'NEUMOLOGIA' => 'Neumología',
                'NEUROLOGIA' => 'Neurología',
                'HEMATOLOGIA' => 'Hematología',
                'INFECTOLOGIA' => 'Infectología',
                'ENDOCRINOLOGIA' => 'Endocrinología',
                'REUMATOLOGIA' => 'Reumatología',
                'GASTROENTEROLOGIA' => 'Gastroenterología',
                'CIRUGIA GENERAL' => 'Cirugía general',
                'NEUROCIRUGIA' => 'Neurocirugía',
                'MEDICO GENERAL' => 'Médico general',
            ],
            'Trabajo Social' => [
                'TRABAJO SOCIAL FAMILIAR' => 'Trabajo social familiar',
                'TRABAJO SOCIAL SALUD' => 'Trabajo social - salud',
                'TRABAJO SOCIAL EDUCATIVO' => 'Trabajo social educativo',
                'TRABAJO SOCIAL COMUNITARIO' => 'Trabajo social comunitario',
                'TRABAJO SOCIAL JURIDICO' => 'Trabajo social jurídico',
            ],
        ];
    }

    /**
     * Qué grupo de especialidades corresponde a cada profesión (campo
     * "profesion", select único) — Abogado→Legales, Psicologo→Psicología,
     * Medico→Médicas, Trabajador Social→Trabajo Social; el resto de
     * profesiones (Directora, Gerente, Administrativo, Contador, etc.) no
     * tiene especialidad asociada.
     *
     * @return array<string, string>
     */
    public static function grupoPorProfesion(): array
    {
        return [
            'Abogado' => 'Legales',
            'Psicologo' => 'Psicología',
            // El campo "cargo" (multivalor) ofrece "Psicologia" (sin esa
            // tilde en el valor) como sugerencia, en vez de "Psicologo"; se
            // mapea aparte para que igual dispare el grupo correcto.
            'Psicologia' => 'Psicología',
            'Medico' => 'Médicas',
            'Trabajador Social' => 'Trabajo Social',
            // Mismo caso: el campo "cargo" ofrece "Trabajo Social" como
            // sugerencia (no "Trabajador Social").
            'Trabajo Social' => 'Trabajo Social',
        ];
    }

    /**
     * Subconjunto de grupos de especialidades aplicable a la profesión
     * seleccionada. Vacío si la profesión no tiene especialidad asociada.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedForProfesion(?string $profesion): array
    {
        $grupo = self::grupoPorProfesion()[$profesion] ?? null;

        if (! $grupo) {
            return [];
        }

        return collect(self::grouped())
            ->only([$grupo])
            ->all();
    }

    /**
     * Igual que groupedForProfesion(), pero para varias profesiones a la vez
     * (campo "cargo", multivalor): devuelve la unión de los grupos de
     * especialidad de todas las profesiones marcadas, sin duplicados.
     *
     * @param  array<int, string>|null  $profesiones
     * @return array<string, array<string, string>>
     */
    public static function groupedForProfesiones(?array $profesiones): array
    {
        $grupos = collect($profesiones ?? [])
            ->map(fn (string $profesion) => self::grupoPorProfesion()[$profesion] ?? null)
            ->filter()
            ->unique()
            ->all();

        if (! $grupos) {
            return [];
        }

        return collect(self::grouped())
            ->only($grupos)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function flat(): array
    {
        return collect(self::grouped())->collapse()->all();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_keys(self::flat());
    }

    public static function label(string $value): string
    {
        return self::flat()[$value] ?? $value;
    }

    /**
     * Cargos/profesiones seleccionables en checkbox (legacy: campo "cargo",
     * multivalor, comma-joined; incluye "Otros" como texto libre adicional).
     *
     * @return array<int, string>
     */
    public static function cargos(): array
    {
        return [
            'Abogado',
            'Psicologia',
            'Trabajo Social',
            'Medico',
            'Administrativo',
            'Contador',
            'Secretaria',
            'Otros',
        ];
    }

    /**
     * Profesión/cargo principal (legacy: campo "profession", select único).
     *
     * @return array<string, string>
     */
    public static function profesiones(): array
    {
        return collect([
            'Directora', 'Gerente', 'Coordinadora', 'Representante legal',
            'Administrativo', 'Abogado', 'Psicologo', 'Trabajador Social',
            'Medico', 'Contador', 'Secretaria', 'Limpieza', 'Pasante', 'Procurador', 'Otros',
        ])->mapWithKeys(fn ($v) => [$v => $v])->all();
    }

    /**
     * Opciones de personal para selects (Agendar / Agendar llamada, Casos, etc.):
     * nombre completo + su(s) especialidad(es) en paréntesis semitransparente.
     * Requiere ->allowHtml() en el Select que las use.
     *
     * Si se pasa $areaLegal (valor de App\Enums\Especialidad, ej. 'CIVIL'), solo
     * se listan los abogados cuyo campo `especialidades` incluye esa área —
     * usado al designar personal a un caso, para filtrar por la materia legal
     * ya seleccionada en el mismo formulario.
     *
     * $profesion filtra además por el campo `profesion` (ej. 'Abogado').
     *
     * @return array<int, string>
     */
    public static function personalOptionsWithEspecialidad(?string $areaLegal = null, ?string $profesion = null): array
    {
        return Personal::query()
            ->when(filled($profesion), fn ($query) => $query->where('profesion', $profesion))
            ->when(
                filled($areaLegal),
                fn ($query) => $query->whereJsonContains('especialidades', $areaLegal)
            )
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'ap_paterno', 'ap_materno', 'especialidades'])
            ->mapWithKeys(function (Personal $personal) {
                $especialidades = collect($personal->especialidades ?? [])
                    ->map(fn (string $valor) => self::label($valor))
                    ->implode(', ');

                $sufijo = $especialidades !== ''
                    ? ' <span style="opacity:.5">('.e($especialidades).')</span>'
                    : '';

                return [$personal->id => e($personal->nombre_completo).$sufijo];
            })
            ->all();
    }

    /**
     * Igual que personalOptionsWithEspecialidad(), pero en texto plano (sin
     * HTML). Usar cuando el Select vaya a recibir valores preseleccionados
     * por código (ej. ->fillForm()) en un campo múltiple: Filament no calcula
     * bien las etiquetas de los "chips" ya seleccionados cuando el Select usa
     * ->allowHtml() junto con ->multiple() y un valor inicial no vacío.
     *
     * $mustIncludeIds fuerza a incluir esos ids aunque no coincidan con el
     * filtro de especialidad — para que un abogado ya asignado (ej. quien
     * respondió la consulta) no desaparezca de la lista solo porque no tiene
     * esa especialidad registrada en su ficha de personal.
     *
     * $profesion filtra además por el campo `profesion` (ej. 'Abogado'), para
     * secciones donde solo corresponde designar personal con esa profesión
     * (como el abogado asignado a un caso).
     *
     * @param  array<int, int>  $mustIncludeIds
     * @return array<int, string>
     */
    public static function personalOptionsPlain(?string $areaLegal = null, array $mustIncludeIds = [], ?string $profesion = null): array
    {
        return Personal::query()
            ->when(filled($profesion), fn ($query) => $query->where('profesion', $profesion))
            ->when(
                filled($areaLegal),
                fn ($query) => $query->where(
                    fn ($q) => $q->whereJsonContains('especialidades', $areaLegal)
                        ->when(filled($mustIncludeIds), fn ($q2) => $q2->orWhereIn('id', $mustIncludeIds))
                )
            )
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'ap_paterno', 'ap_materno', 'especialidades'])
            ->mapWithKeys(function (Personal $personal) {
                $especialidades = collect($personal->especialidades ?? [])
                    ->map(fn (string $valor) => self::label($valor))
                    ->implode(', ');

                $sufijo = $especialidades !== '' ? ' ('.$especialidades.')' : '';

                return [$personal->id => $personal->nombre_completo.$sufijo];
            })
            ->all();
    }
}
