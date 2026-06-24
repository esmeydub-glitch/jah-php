# JAH ActionPHP Metrics

Fecha de medicion: 2026-06-14

## Comandos

```bash
php tests/actionphp_smoke.php
php tests/actionphp_metrics.php
curl -o /tmp/jah-demo-page.html -s -w 'status=%{http_code}\ncontent_type=%{content_type}\nsize_download=%{size_download}\ntime_total=%{time_total}\n' http://127.0.0.1:8002/demo_dashboard.php
curl -o /tmp/jah-demo-video.webm -s -w 'status=%{http_code}\ncontent_type=%{content_type}\nsize_download=%{size_download}\ntime_total=%{time_total}\n' http://127.0.0.1:8002/media/jah-demo.webm
```

## Resultado actual

```json
{
    "php_version": "8.4.21",
    "render_ms": 0.115,
    "html_bytes": 1645,
    "component_count": {
        "stage": 1,
        "scene": 1,
        "video": 1,
        "button": 2,
        "jah_events": 2,
        "salk_tokens": 2
    },
    "video": {
        "path": "public/media/jah-demo.webm",
        "exists": true,
        "bytes": 6477188,
        "readable": true
    }
}
```

## HTTP local

Demo:

```text
status=200
content_type=text/html; charset=UTF-8
size_download=3597
time_total=0.000824
```

Video:

```text
status=200
content_type=video/webm
size_download=6477188
time_total=0.008316
```

## Criterios de funcionamiento

- PHP renderiza el arbol `Stage -> Scene -> Video -> Button`.
- El HTML contiene un componente `<video>` apuntando a `public/media/jah-demo.webm`.
- El archivo de video existe, es legible y pesa `6477188` bytes.
- Hay eventos JAH renderizados como `data-jah-event`.
- Hay tokens SALK renderizados como `data-salk-token`.
- El servidor local responde `200 OK` para la demo y para el video.

## Prueba de escala

```bash
php tests/actionphp_scale.php --panels=100 --buttons=1000 --tables=100 --rows=10 --events=10000
```

Resultado actual:

```json
{
    "input": {
        "panels": 100,
        "buttons": 1000,
        "tables": 100,
        "rows_per_table": 10,
        "synthetic_events": 10000
    },
    "timing_ms": {
        "build_components": 18.673,
        "render_html": 4.226,
        "build_event_names": 0.363
    },
    "memory_bytes": {
        "start": 2097152,
        "end": 6291456,
        "delta": 8388608,
        "peak_delta": 10485760
    },
    "html_bytes": 1524897,
    "component_count": {
        "stage": 1,
        "scene": 1,
        "panel": 100,
        "card": 100,
        "table": 100,
        "button": 2000,
        "jah_events": 2000,
        "salk_tokens": 2000
    }
}
```

Lectura: ActionPHP construyo 100 paneles, 100 tarjetas, 100 tablas y 2000 botones/eventos firmados por SALK en `4.226 ms`, generando `1524897` bytes de HTML con `8388608` bytes adicionales de memoria PHP.
