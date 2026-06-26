# Prueba de Despliegue en Alibaba Cloud - Jah-PHP

## Servicios Utilizados
- **Compute:** Alibaba Cloud ECS (Ubuntu 22.04, PHP 8.2+)
- **AI Service:** Qwen Cloud / DashScope International API
- **Endpoint:** https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions
- **Modelo:** qwen-max

## Configuración de Despliegue
apt update && apt install -y php-cli php-curl git
git clone https://github.com/esmeydub-glitch/jah-php.git
cd jah-php && cp .env.example .env
php -S 0.0.0.0:8000 -t jah-php

## Evidencia
Este backend está diseñado para ejecutarse nativamente en Alibaba Cloud ECS sin Docker ni dependencias externas.
La integración con Qwen Cloud se realiza vía cURL nativo de PHP al endpoint internacional oficial.
