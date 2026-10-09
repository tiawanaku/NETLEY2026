<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoPortada extends Model
{
    use HasFactory;

    protected $table = 'videos_portada';

    protected $fillable = [
        'titulo',
        'descripcion',
        'url',
        'archivo',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** Convierte una URL de YouTube/Vimeo en su URL de embed, para usar en un <iframe>. */
    public function getUrlEmbedAttribute(): ?string
    {
        if (blank($this->url)) {
            return null;
        }

        if (preg_match('/youtu\.be\/([A-Za-z0-9_-]+)/', $this->url, $m)
            || preg_match('/[?&]v=([A-Za-z0-9_-]+)/', $this->url, $m)
            || preg_match('/youtube\.com\/embed\/([A-Za-z0-9_-]+)/', $this->url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $this->url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return $this->url;
    }
}
