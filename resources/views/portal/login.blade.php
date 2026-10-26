@extends('layouts.public')

@section('nav')
    <a href="{{ route('frontpage') }}" class="boton boton-contorno">Volver al inicio</a>
@endsection

@section('contenido')
    <section style="padding:70px 0;min-height:60vh;display:flex;align-items:center;">
        <div class="contenedor" style="max-width:420px;">
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:32px;">
                <h1 style="font-size:20px;color:#1a2537;margin:0 0 4px;">Portal de clientes</h1>
                <p style="font-size:13px;color:#6b7280;margin:0 0 24px;">
                    Ingresa con las credenciales que te proporcionó tu abogado para ver el avance de tu proceso.
                </p>

                @if ($errors->any())
                    <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:18px;">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('portal.login.attempt') }}">
                    @csrf
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Usuario</label>
                    <input type="text" name="username" value="{{ old('username') }}" required autofocus
                        style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;margin-bottom:16px;">

                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Contraseña</label>
                    <input type="password" name="password" required
                        style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;margin-bottom:22px;">

                    <button type="submit" class="boton boton-primario" style="width:100%;border:none;font-size:15px;padding:11px;">
                        Ingresar
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
