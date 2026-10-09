<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoConsulta: string implements HasColor, HasLabel
{
    case Pendiente = 'pendiente';
    case Respondido = 'respondido';
    case Agendado = 'agendado';
    case CasoIniciado = 'caso_iniciado';
    case NoContesta = 'no_contesta';
    case NoResponde = 'no_responde';
    case NoRequiereServicios = 'no_requiere_servicios';
    case SoloConsulta = 'solo_consulta';
    case RemitirPsicologia = 'remitir_psicologia';
    case RemitirSocial = 'remitir_social';
    case Reactivado = 'reactivado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Respondido => 'Respondido',
            self::Agendado => 'Agendado',
            self::CasoIniciado => 'Caso Iniciado',
            self::NoContesta => 'No Contesta',
            self::NoResponde => 'No Responde',
            self::NoRequiereServicios => 'No Requiere los Servicios',
            self::SoloConsulta => 'Solo Consulta',
            self::RemitirPsicologia => 'Remitir a Psicología',
            self::RemitirSocial => 'Remitir a Social',
            self::Reactivado => 'Reactivado',
        };
    }

    /**
     * Colores por estado (coinciden con las pestañas de la lista de Consultas):
     * verde = nueva, amarillo = pendiente, naranja = reactivada,
     * azul = se convirtió en cliente ejecutivo, rojo = el resto de cerradas.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'success',
            self::Respondido, self::Agendado => 'warning',
            self::Reactivado => 'orange',
            self::CasoIniciado => 'info',
            self::NoContesta, self::NoResponde, self::NoRequiereServicios,
            self::SoloConsulta, self::RemitirPsicologia, self::RemitirSocial => 'danger',
        };
    }

    /** Estados que se consideran "cola abierta" (consultas.php en el sistema viejo). */
    public static function abiertos(): array
    {
        return [self::Pendiente, self::Respondido, self::Agendado, self::Reactivado];
    }
}
