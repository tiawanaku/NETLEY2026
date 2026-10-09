<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'NETLEY' }}</title>
    <link rel="icon" href="{{ asset('images/netley-mark.png') }}">
    <style>
        :root {
            --navy: #1a2537;
            --sky: #4fc3f7;
            --bg: #f5f7fa;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: #1f2937;
        }
        a { text-decoration: none; color: inherit; }
        .contenedor { max-width: 1100px; margin: 0 auto; padding: 0 24px; }
        header.sitio {
            background: var(--navy);
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 2px 12px rgba(0,0,0,.15);
        }
        .revelar {
            opacity: 0;
            transform: translateY(16px);
            transition: opacity .6s ease, transform .6s ease;
        }
        .revelar.visible {
            opacity: 1;
            transform: translateY(0);
        }
        header.sitio .barra {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            flex-wrap: wrap;
            gap: 12px;
        }
        header.sitio .marca {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 18px;
        }
        header.sitio .marca img { height: 36px; }
        header.sitio nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .boton {
            display: inline-block;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .boton-primario { background: var(--sky); color: var(--navy); }
        .boton-primario:hover { filter: brightness(1.05); }
        .boton-contorno { background: transparent; color: #fff; border-color: rgba(255,255,255,.5); }
        .boton-contorno:hover { background: rgba(255,255,255,.1); }
        footer.sitio {
            background: var(--navy);
            color: #cbd5e1;
            margin-top: 60px;
            padding: 28px 0;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <header class="sitio">
        <div class="contenedor barra">
            <a href="{{ route('frontpage') }}" class="marca">
                <img src="{{ asset('images/netley-logo.png') }}" alt="NETLEY">
            </a>
            <nav>
                @yield('nav')
            </nav>
        </div>
    </header>

    <main>
        @yield('contenido')
    </main>

    <footer class="sitio">
        <div class="contenedor" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
            <span>&copy; {{ now()->year }} NETLEY. Todos los derechos reservados.</span>
            @hasSection('redes')
                <div style="display:flex;gap:10px;">
                    @yield('redes')
                </div>
            @endif
        </div>
    </footer>

    @yield('flotantes')

    <script>
        document.querySelectorAll('.revelar').forEach((el, i) => {
            el.style.transitionDelay = (i % 6) * 60 + 'ms';
        });
        const observador = new IntersectionObserver((entradas) => {
            entradas.forEach((entrada) => {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('visible');
                    observador.unobserve(entrada.target);
                }
            });
        }, { threshold: 0.15 });
        document.querySelectorAll('.revelar').forEach((el) => observador.observe(el));
    </script>
    @stack('scripts')
</body>
</html>
