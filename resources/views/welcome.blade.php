<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #153c36; background: #f3f7f4; }
        body { margin: 0; min-height: 100svh; display: grid; place-items: center; }
        main { width: min(36rem, calc(100% - 4rem)); padding: 2rem; box-sizing: border-box; background: white; border-radius: 1.5rem; border: 1px solid #d7e5dd; }
        p { line-height: 1.7; color: #45635b; }
        small { color: #587269; }
    </style>
</head>
<body>
    <main>
        <small>Finanzas personales · MXN</small>
        <h1>{{ config('app.name') }}</h1>
        <p>Base del proyecto preparada. Los módulos de cuentas, movimientos y presupuestos se implementarán en las siguientes etapas.</p>
        <p>Esta versión todavía no permite registrar operaciones financieras.</p>
    </main>
</body>
</html>
