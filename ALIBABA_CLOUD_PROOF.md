# Prueba de Despliegue en Alibaba Cloud - Jah-PHP

## Servicios Utilizados
- **Compute:** Alibaba Cloud ECS o Function Compute con PHP runtime
- **AI Service:** Qwen Cloud / DashScope International API
- **Endpoint:** `https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions`
- **Modelo:** `qwen-max` por defecto, configurable con `QWEN_MODEL`

## Archivo de código que demuestra Qwen Cloud
- `app/QwenConnector.php`

Este archivo llama a Qwen Cloud con PHP puro y cURL nativo:

```php
$ch = curl_init($this->baseUrl . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $this->apiKey,
        'Content-Type: application/json',
    ],
]);
```

## Configuración de Despliegue ECS

```bash
apt update
apt install -y php-cli php-curl git
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php
cp .env.example .env
# Editar .env y poner QWEN_API_KEY
php -S 0.0.0.0:8000 -t public
```

## Prueba HTTP

```bash
curl http://TU_IP:8000/api.php?action=status

curl -X POST http://TU_IP:8000/agent.php \
  -H "Content-Type: application/json" \
  -d '{"message":"¿Qué recuerdas de mi proyecto?","collection":"memories"}'
```

## Evidencia para video separado

Para cumplir el requisito de prueba de Alibaba Cloud, grabar un video corto mostrando:

1. Consola conectada a ECS o Function Compute.
2. `php -v`.
3. Variables configuradas sin mostrar la key completa.
4. `php -S 0.0.0.0:8000 -t public` o endpoint Function Compute.
5. `curl /api.php?action=status`.
6. Llamada a `/agent.php` usando Qwen Cloud.

El backend no usa Python, Node.js, Docker ni base de datos externa para el runtime MemoryAgent principal.
