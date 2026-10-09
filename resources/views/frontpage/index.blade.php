@extends('layouts.public')

@section('nav')
    <a href="{{ route('frontpage') }}#areas" class="enlace-nav">Áreas</a>
    @if ($videos->isNotEmpty())
        <a href="{{ route('frontpage') }}#videos" class="enlace-nav">Videos</a>
    @endif
    <a href="{{ route('frontpage') }}#opiniones" class="enlace-nav">Opiniones</a>
    <a href="{{ route('frontpage') }}#consulta" class="enlace-nav">Consulta</a>
    <a href="{{ route('frontpage') }}#contacto" class="enlace-nav">Contacto</a>
    <a href="{{ url('/admin/login') }}" class="boton boton-contorno">Ingreso Personal</a>
    <a href="{{ route('portal.login') }}" class="boton boton-primario">Ingreso Clientes</a>
@endsection

@if ($redesSociales->isNotEmpty())
    @section('redes')
        @foreach ($redesSociales as $red)
            <a href="{{ $red->url }}" target="_blank" rel="noopener" title="{{ $red->plataforma->getLabel() }}"
               style="width:32px;height:32px;border-radius:999px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="#cbd5e1"><path d="{{ $red->plataforma->svgPath() }}"/></svg>
            </a>
        @endforeach
    @endsection
@endif

@php $whatsapp = $redesSociales->firstWhere('plataforma', \App\Enums\RedSocialPlataforma::WhatsApp); @endphp
@if ($whatsapp)
    @section('flotantes')
        <a href="{{ $whatsapp->url }}" target="_blank" rel="noopener"
           style="position:fixed;bottom:24px;right:24px;width:56px;height:56px;border-radius:999px;background:#25D366;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(0,0,0,.25);z-index:60;">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="#fff"><path d="{{ \App\Enums\RedSocialPlataforma::WhatsApp->svgPath() }}"/></svg>
        </a>
    @endsection
@endif

@section('contenido')
    <style>
        .enlace-nav { align-self: center; font-size: 13px; color: #cbd5e1; padding: 6px 4px; }
        .enlace-nav:hover { color: #fff; }
        .tarjeta { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; }
        .banner-exito {
            background: #dcfce7; border: 1px solid #86efac; color: #166534;
            border-radius: 10px; padding: 12px 16px; font-size: 14px; margin-bottom: 20px;
        }
        .banner-error {
            background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
            border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px;
        }
        .campo label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .campo input, .campo textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px;
            font-size: 14px; font-family: inherit;
        }
        .campo { margin-bottom: 16px; }
        .campo .error { color: #b91c1c; font-size: 12px; margin-top: 4px; }

        .estrellas {
            display: flex; flex-direction: row-reverse; justify-content: flex-end;
            gap: 4px; margin-bottom: 16px;
        }
        .estrellas input { position: absolute; opacity: 0; width: 0; height: 0; }
        .estrellas label { font-size: 26px; color: #d1d5db; cursor: pointer; line-height: 1; }
        .estrellas input:checked ~ label,
        .estrellas label:hover,
        .estrellas label:hover ~ label { color: #f59e0b; }

        .carrusel-testimonios { display: flex; gap: 18px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 10px; }
        .carrusel-testimonios::-webkit-scrollbar { height: 6px; }
        .carrusel-testimonios::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 999px; }
        .tarjeta-testimonio { scroll-snap-align: start; min-width: 280px; max-width: 320px; flex: 0 0 auto; padding: 22px; }

        /* Las imágenes del carrusel traen su propio texto compuesto para formato ancho
           (16:9). Se usa "contain" siempre (nunca "cover"): así nunca se recorta ese texto,
           sin importar el ancho de pantalla — a lo sumo queda una franja de marca a los
           lados o arriba/abajo si el ancho de la ventana no es exactamente 16:9. */
        .hero-imagenes { position: relative; overflow: hidden; color: #fff; }
        .hero-track { display: flex; transition: transform .7s ease; }
        .hero-slide { min-width: 100%; position: relative; display: flex; align-items: center; background: linear-gradient(135deg,#1a2537,#243352); min-height: 460px; }
        .hero-slide.con-imagen { aspect-ratio: 16 / 9; background-size: contain; background-repeat: no-repeat; background-position: center; background-color: var(--navy); min-height: 0; }
        .hero-slide.con-texto.con-imagen::before { content:''; position:absolute; inset:0; background:linear-gradient(90deg,rgba(15,23,42,.82),rgba(15,23,42,.35)); }
        .hero-slide .hero-contenido { position: relative; z-index: 1; }
        .hero-cta-bar { background: var(--navy); padding: 18px 0 44px; }
        .hero-flechas button {
            position: absolute; top: 50%; transform: translateY(-50%); z-index: 2;
            width: 40px; height: 40px; border-radius: 999px; border: none; cursor: pointer;
            background: rgba(255,255,255,.15); color: #fff; font-size: 18px;
        }
        .hero-flechas button:hover { background: rgba(255,255,255,.3); }
        .hero-flechas .anterior { left: 18px; }
        .hero-flechas .siguiente { right: 18px; }
        .hero-puntos { position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%); z-index: 2; display: flex; gap: 8px; }
        .hero-puntos button {
            width: 9px; height: 9px; border-radius: 999px; border: none; cursor: pointer;
            background: rgba(255,255,255,.4);
        }
        .hero-puntos button.activo { background: #fff; }

        @media (max-width: 640px) {
            .hero-cta-bar .contenedor span { flex-basis: 100%; text-align: center; margin-right: 0; margin-bottom: 6px; }
        }

        .video-marco { position: relative; padding-top: 56.25%; border-radius: 14px; overflow: hidden; background: #000; }
        .video-marco iframe, .video-marco video { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
    </style>

    @php $slides = $imagenesCarrusel->isNotEmpty() ? $imagenesCarrusel : collect([null]); @endphp
    <section class="hero">
        <div class="hero-imagenes">
            <div class="hero-track" id="hero-track">
                @foreach ($slides as $slide)
                    @php $tieneTexto = ! $slide || filled($slide->titulo) || filled($slide->subtitulo); @endphp
                    <div class="hero-slide {{ $slide ? 'con-imagen' : '' }} {{ $tieneTexto ? 'con-texto' : '' }}"
                         @if ($slide) style="background-image:url('{{ asset('storage/'.$slide->imagen) }}');" @endif>
                        @if ($tieneTexto)
                            <div class="contenedor hero-contenido">
                                <span style="display:inline-block;background:rgba(79,195,247,.15);color:#7dd3fc;font-size:12px;font-weight:700;letter-spacing:.05em;padding:6px 14px;border-radius:999px;margin-bottom:16px;">
                                    ASESORÍA LEGAL EN BOLIVIA
                                </span>
                                <h1 style="font-size:38px;line-height:1.2;margin:0 0 16px;max-width:640px;">
                                    {{ $slide->titulo ?? 'Asesoría legal seria, cercana y con resultados.' }}
                                </h1>
                                <p style="font-size:16px;color:#cbd5e1;max-width:520px;margin:0;">
                                    {{ $slide->subtitulo ?? 'NETLEY acompaña a personas y empresas en procesos civiles, penales, familiares y laborales, con seguimiento cercano de cada caso.' }}
                                </p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($slides->count() > 1)
                <div class="hero-flechas">
                    <button type="button" class="anterior" onclick="heroMover(-1)" aria-label="Anterior">&larr;</button>
                    <button type="button" class="siguiente" onclick="heroMover(1)" aria-label="Siguiente">&rarr;</button>
                </div>
                <div class="hero-puntos" id="hero-puntos">
                    @foreach ($slides as $i => $slide)
                        <button type="button" class="{{ $i === 0 ? 'activo' : '' }}" onclick="heroIr({{ $i }})"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="hero-cta-bar">
            <div class="contenedor" style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:center;">
                <span style="color:#cbd5e1;font-size:14px;margin-right:auto;">NETLEY — asesoría legal seria, cercana y con resultados.</span>
                <a href="#consulta" class="boton boton-primario">Solicitar consulta gratuita</a>
                <a href="{{ route('portal.login') }}" class="boton boton-contorno">Ver el avance de mi proceso</a>
            </div>
        </div>
    </section>

    <section class="contenedor" style="margin-top:-24px;position:relative;z-index:2;">
        <div class="tarjeta revelar" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));text-align:center;box-shadow:0 12px 30px rgba(26,37,55,.12);">
            @foreach ([
                ['valor' => $stats['casos'], 'etiqueta' => 'Casos gestionados'],
                ['valor' => $stats['resueltos'], 'etiqueta' => 'Casos resueltos'],
                ['valor' => $stats['clientes'], 'etiqueta' => 'Clientes atendidos'],
                ['valor' => $stats['abogados'], 'etiqueta' => 'Abogados en el equipo'],
            ] as $stat)
                <div style="padding:26px 14px;border-right:1px solid #f0f1f3;">
                    <div style="font-size:28px;font-weight:800;color:#1a2537;">{{ $stat['valor'] }}+</div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;">{{ $stat['etiqueta'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section id="areas" style="padding:80px 0 60px;">
        <div class="contenedor">
            <h2 class="revelar" style="font-size:26px;color:#1a2537;margin:0 0 8px;">Áreas de práctica</h2>
            <p class="revelar" style="color:#4b5563;margin:0 0 30px;">Cobertura legal integral en las principales materias.</p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
                @foreach (\App\Enums\Especialidad::cases() as $especialidad)
                    <div class="tarjeta revelar" style="padding:22px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;font-weight:700;margin-bottom:14px;">
                            {{ mb_substr($especialidad->getLabel(), 0, 1) }}
                        </div>
                        <h3 style="margin:0 0 6px;font-size:16px;color:#1a2537;">Derecho {{ $especialidad->getLabel() }}</h3>
                        <p style="margin:0;font-size:13px;color:#6b7280;">Asesoría y representación en materia {{ strtolower($especialidad->getLabel()) }}.</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if ($oficinas->isNotEmpty())
        <section style="padding:0 0 60px;">
            <div class="contenedor">
                <h2 class="revelar" style="font-size:22px;color:#1a2537;margin:0 0 20px;">Nuestras oficinas</h2>
                <div class="revelar" style="display:flex;gap:14px;flex-wrap:wrap;">
                    @foreach ($oficinas as $oficina)
                        <span class="tarjeta" style="border-radius:999px;padding:8px 16px;font-size:13px;color:#1a2537;">
                            {{ $oficina->oficina }} — {{ $oficina->ciudad }}
                        </span>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($videos->isNotEmpty())
        <section id="videos" style="padding:0 0 70px;">
            <div class="contenedor">
                <h2 class="revelar" style="font-size:26px;color:#1a2537;margin:0 0 8px;">Conócenos en video</h2>
                <p class="revelar" style="color:#4b5563;margin:0 0 30px;">Casos, testimonios y consejos legales de nuestro equipo.</p>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
                    @foreach ($videos as $video)
                        <div class="revelar">
                            <div class="video-marco">
                                @if ($video->url)
                                    <iframe src="{{ $video->url_embed }}" title="{{ $video->titulo }}" allowfullscreen></iframe>
                                @elseif ($video->archivo)
                                    <video src="{{ asset('storage/'.$video->archivo) }}" controls></video>
                                @endif
                            </div>
                            <h3 style="margin:12px 0 4px;font-size:15px;color:#1a2537;">{{ $video->titulo }}</h3>
                            @if ($video->descripcion)
                                <p style="margin:0;font-size:13px;color:#6b7280;">{{ $video->descripcion }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section id="opiniones" style="background:#fff;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:70px 0;">
        <div class="contenedor">
            <h2 class="revelar" style="font-size:26px;color:#1a2537;margin:0 0 8px;">Lo que dicen nuestros clientes</h2>
            <p class="revelar" style="color:#4b5563;margin:0 0 30px;">Opiniones reales de personas que confiaron su proceso a nuestro equipo.</p>

            @if (session('testimonio_enviado'))
                <div class="banner-exito">
                    ¡Gracias por tu opinión! Quedó registrada y se publicará luego de ser revisada por nuestro equipo.
                </div>
            @endif

            @if ($testimonios->isEmpty())
                <p style="color:#9ca3af;font-size:14px;margin-bottom:30px;">
                    Todavía no hay opiniones publicadas. ¡Sé el primero en compartir tu experiencia!
                </p>
            @else
                <div class="carrusel-testimonios revelar" style="margin-bottom:40px;">
                    @foreach ($testimonios as $testimonio)
                        <div class="tarjeta tarjeta-testimonio">
                            <div style="color:#f59e0b;font-size:15px;margin-bottom:10px;">
                                {{ str_repeat('★', $testimonio->calificacion) }}{{ str_repeat('☆', 5 - $testimonio->calificacion) }}
                            </div>
                            <p style="font-size:14px;color:#374151;margin:0 0 14px;min-height:60px;">
                                &ldquo;{{ \Illuminate\Support\Str::limit($testimonio->testimonio, 180) }}&rdquo;
                            </p>
                            <div style="font-size:13px;font-weight:700;color:#1a2537;">{{ $testimonio->nombre }}</div>
                            <div style="font-size:12px;color:#9ca3af;">{{ $testimonio->fecha->translatedFormat('d \d\e F, Y') }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="tarjeta revelar" style="padding:28px;max-width:520px;">
                <h3 style="margin:0 0 4px;font-size:16px;color:#1a2537;">Comparte tu experiencia</h3>
                <p style="margin:0 0 18px;font-size:13px;color:#6b7280;">Tu comentario será revisado antes de publicarse.</p>

                @if ($errors->has('testimonio') || $errors->has('nombre') || $errors->has('calificacion'))
                    <div class="banner-error">{{ $errors->first('testimonio') ?: ($errors->first('nombre') ?: $errors->first('calificacion')) }}</div>
                @endif

                <form method="POST" action="{{ route('frontpage.testimonio.store') }}">
                    @csrf
                    <div class="estrellas">
                        <input type="radio" id="e5" name="calificacion" value="5" {{ old('calificacion', 5) == 5 ? 'checked' : '' }}><label for="e5">★</label>
                        <input type="radio" id="e4" name="calificacion" value="4" {{ old('calificacion') == 4 ? 'checked' : '' }}><label for="e4">★</label>
                        <input type="radio" id="e3" name="calificacion" value="3" {{ old('calificacion') == 3 ? 'checked' : '' }}><label for="e3">★</label>
                        <input type="radio" id="e2" name="calificacion" value="2" {{ old('calificacion') == 2 ? 'checked' : '' }}><label for="e2">★</label>
                        <input type="radio" id="e1" name="calificacion" value="1" {{ old('calificacion') == 1 ? 'checked' : '' }}><label for="e1">★</label>
                    </div>

                    <div class="campo">
                        <label for="t-nombre">Nombre</label>
                        <input type="text" id="t-nombre" name="nombre" value="{{ old('nombre') }}" required>
                    </div>
                    <div class="campo">
                        <label for="t-correo">Correo (opcional)</label>
                        <input type="email" id="t-correo" name="correo" value="{{ old('correo') }}">
                    </div>
                    <div class="campo">
                        <label for="t-testimonio">Tu opinión</label>
                        <textarea id="t-testimonio" name="testimonio" rows="4" required>{{ old('testimonio') }}</textarea>
                    </div>

                    <button type="submit" class="boton boton-primario" style="border:none;font-size:14px;padding:11px 22px;">
                        Enviar opinión
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section id="consulta" style="padding:70px 0;">
        <div class="contenedor" style="display:flex;gap:40px;flex-wrap:wrap;">
            <div style="flex:1;min-width:280px;" class="revelar">
                <h2 style="font-size:26px;color:#1a2537;margin:0 0 8px;">Solicita tu consulta</h2>
                <p style="color:#4b5563;margin:0 0 20px;max-width:420px;">
                    Cuéntanos brevemente tu caso. Un miembro de nuestro equipo se pondrá en
                    contacto contigo para programar tu consulta inicial.
                </p>
                <p style="margin:6px 0;color:#1a2537;font-size:14px;"><strong>Teléfono:</strong> (591) 2-2000000</p>
                <p style="margin:6px 0;color:#1a2537;font-size:14px;"><strong>WhatsApp:</strong> +591 70000000</p>
                <p style="margin:6px 0;color:#1a2537;font-size:14px;"><strong>Correo:</strong> contacto@netley.bo</p>
            </div>

            <div class="tarjeta revelar" style="flex:1;min-width:300px;padding:28px;">
                @if (session('consulta_enviada'))
                    <div class="banner-exito">
                        ¡Consulta enviada! Nos pondremos en contacto contigo a la brevedad.
                    </div>
                @endif

                @if ($errors->any() && ($errors->has('telefono') || $errors->has('consulta') || $errors->has('captcha')))
                    <div class="banner-error">
                        {{ $errors->first('telefono') ?: ($errors->first('consulta') ?: $errors->first('captcha')) }}
                    </div>
                @endif

                <form method="POST" action="{{ route('frontpage.consulta.store') }}">
                    @csrf
                    <div style="display:flex;gap:12px;">
                        <div class="campo" style="flex:1;">
                            <label for="c-nombres">Nombres</label>
                            <input type="text" id="c-nombres" name="nombres" value="{{ old('nombres') }}" required>
                        </div>
                        <div class="campo" style="flex:1;">
                            <label for="c-apellido">Apellido</label>
                            <input type="text" id="c-apellido" name="ap_paterno" value="{{ old('ap_paterno') }}">
                        </div>
                    </div>
                    <div style="display:flex;gap:12px;">
                        <div class="campo" style="flex:1;">
                            <label for="c-telefono">Teléfono / WhatsApp</label>
                            <input type="text" id="c-telefono" name="telefono" value="{{ old('telefono') }}" required>
                        </div>
                        <div class="campo" style="flex:1;">
                            <label for="c-correo">Correo (opcional)</label>
                            <input type="email" id="c-correo" name="correo" value="{{ old('correo') }}">
                        </div>
                    </div>
                    <div class="campo">
                        <label for="c-ciudad">Ciudad</label>
                        <input type="text" id="c-ciudad" name="ciudad" value="{{ old('ciudad') }}">
                    </div>
                    <div class="campo">
                        <label for="c-consulta">Cuéntanos tu caso</label>
                        <textarea id="c-consulta" name="consulta" rows="4" required>{{ old('consulta') }}</textarea>
                    </div>
                    <div class="campo">
                        <label for="c-captcha">Verificación: ¿cuánto es {{ $captcha['a'] }} + {{ $captcha['b'] }}?</label>
                        <input type="text" id="c-captcha" name="captcha" inputmode="numeric" autocomplete="off" required>
                    </div>

                    <button type="submit" class="boton boton-primario" style="width:100%;border:none;font-size:15px;padding:11px;">
                        Enviar consulta
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section id="contacto" style="background:#fff;border-top:1px solid #e5e7eb;padding:50px 0;">
        <div class="contenedor revelar" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
            <div>
                <h3 style="margin:0 0 6px;color:#1a2537;font-size:16px;">¿Ya eres cliente?</h3>
                <p style="margin:0;color:#6b7280;font-size:13px;">Ingresa al portal para ver el estado y avance de tu proceso.</p>
            </div>
            <a href="{{ route('portal.login') }}" class="boton boton-primario">Ingresar al portal</a>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            const track = document.getElementById('hero-track');
            if (!track) return;

            const puntos = document.querySelectorAll('#hero-puntos button');
            const total = track.children.length;
            let actual = 0;
            let temporizador;

            function mostrar(indice) {
                actual = (indice + total) % total;
                track.style.transform = 'translateX(-' + (actual * 100) + '%)';
                puntos.forEach((p, i) => p.classList.toggle('activo', i === actual));
            }

            window.heroMover = (delta) => { mostrar(actual + delta); reiniciarAuto(); };
            window.heroIr = (indice) => { mostrar(indice); reiniciarAuto(); };

            function reiniciarAuto() {
                clearInterval(temporizador);
                if (total > 1) temporizador = setInterval(() => mostrar(actual + 1), 6000);
            }

            reiniciarAuto();
        })();
    </script>
@endpush
