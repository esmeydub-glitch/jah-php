<?php

declare(strict_types=1);

namespace Jah\Security;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * SalkGuard
 * Capa SALK userland para JAH MemoryAgent.
 * Protege secretos, valida rutas de DataCore y registra auditoría sin exponer API keys.
 */
final class SalkGuard
{
    private string $root;
    private array $config;
    private array $salkConfig;
    private string $auditFile;

    /** @var array<string,string> */
    private array $knownSecrets = [];

    public function __construct(string $root, array $config = [])
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
        $this->config = $config;
        $this->salkConfig = is_array($config['salk'] ?? null) ? $config['salk'] : [];

        $auditPath = (string)($this->salkConfig['audit_file'] ?? ($this->root . '/runtime/security/salk_audit.ndjson'));
        $this->auditFile = $this->resolvePath($auditPath);
        $this->collectKnownSecrets();
        $this->ensureAuditDirectory();
    }

    public function preflight(string $context = 'runtime'): array
    {
        $checks = [];
        $warnings = [];
        $errors = [];

        $env = $this->checkEnv();
        $checks['env'] = $env;
        $warnings = array_merge($warnings, $env['warnings']);
        $errors = array_merge($errors, $env['errors']);

        $api = $this->protectApiKey();
        $checks['api_key'] = $api;
        $warnings = array_merge($warnings, $api['warnings']);
        $errors = array_merge($errors, $api['errors']);

        $paths = $this->checkDataCorePath();
        $checks['datacore_paths'] = $paths;
        $warnings = array_merge($warnings, $paths['warnings']);
        $errors = array_merge($errors, $paths['errors']);

        $permissions = $this->verifyRuntimePermissions();
        $checks['permissions'] = $permissions;
        $warnings = array_merge($warnings, $permissions['warnings']);
        $errors = array_merge($errors, $permissions['errors']);

        $secretScan = $this->scanProjectForSecrets();
        $checks['secret_scan'] = $secretScan;
        $warnings = array_merge($warnings, $secretScan['warnings']);
        $errors = array_merge($errors, $secretScan['errors']);

        $packageVectors = $this->checkPackageJsonVectors();
        $checks['package_vectors'] = $packageVectors;
        $warnings = array_merge($warnings, $packageVectors['warnings']);
        $errors = array_merge($errors, $packageVectors['errors']);

        $result = [
            'ok' => $errors === [],
            'context' => $context,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'checks' => $checks,
        ];

        $this->auditEvent('salk.preflight', $result, ['context' => $context]);
        return $this->maskSecrets($result);
    }

    public function checkEnv(): array
    {
        $warnings = [];
        $errors = [];
        $envFile = $this->root . '/.env';
        $publicEnv = $this->root . '/public/.env';

        if (is_file($publicEnv)) {
            $errors[] = '.env no debe existir dentro de public/';
        }

        if (is_file($envFile)) {
            $perms = substr(sprintf('%o', fileperms($envFile)), -4);
            if (is_readable($envFile) === false) {
                $errors[] = '.env existe pero no se puede leer';
            }
            if (in_array($perms, ['0666', '0777', '0646', '0766'], true)) {
                $warnings[] = ".env tiene permisos demasiado abiertos ({$perms})";
            }
        } else {
            $warnings[] = '.env no existe; se usará el entorno del sistema si está configurado';
        }

        return [
            'ok' => $errors === [],
            'env_file' => is_file($envFile),
            'public_env_file' => is_file($publicEnv),
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    public function protectApiKey(): array
    {
        $warnings = [];
        $errors = [];
        $apiKey = $this->getSecret('QWEN_API_KEY');

        if ($apiKey === '') {
            $warnings[] = 'QWEN_API_KEY no está configurada; Qwen no responderá hasta configurarla';
        }

        if ($apiKey !== '' && strlen($apiKey) < 24) {
            $warnings[] = 'QWEN_API_KEY parece demasiado corta';
        }

        return [
            'ok' => $errors === [],
            'present' => $apiKey !== '',
            'fingerprint' => $apiKey !== '' ? $this->fingerprint($apiKey) : null,
            'source' => $this->secretSource('QWEN_API_KEY'),
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    public function checkDataCorePath(): array
    {
        $warnings = [];
        $errors = [];
        $publicPath = $this->realOrFallback($this->root . '/public');

        $paths = [
            'datacore_storage' => (string)($this->config['paths']['datacore_storage'] ?? $this->root . '/runtime/memory/datacore'),
            'hot_storage' => (string)($this->config['paths']['hot_storage'] ?? $this->root . '/runtime/memory/pyramid'),
            'runtime_security' => dirname($this->auditFile),
        ];

        $resolved = [];
        foreach ($paths as $name => $path) {
            $full = $this->resolvePath($path);
            $resolved[$name] = $full;

            if ($this->isInside($full, $publicPath)) {
                $errors[] = "{$name} no debe estar dentro de public/";
            }

            if (!is_dir($full)) {
                if (!@mkdir($full, 0700, true) && !is_dir($full)) {
                    $errors[] = "no se pudo crear ruta segura: {$name}";
                }
            }

            if (is_dir($full) && !is_writable($full)) {
                $errors[] = "ruta no escribible: {$name}";
            }
        }

        return [
            'ok' => $errors === [],
            'paths' => $resolved,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    public function verifyRuntimePermissions(): array
    {
        $warnings = [];
        $errors = [];
        $dirs = [
            $this->root . '/runtime',
            dirname($this->auditFile),
            (string)($this->config['paths']['datacore_storage'] ?? $this->root . '/runtime/memory/datacore'),
            (string)($this->config['paths']['hot_storage'] ?? $this->root . '/runtime/memory/pyramid'),
        ];

        foreach ($dirs as $dir) {
            $path = $this->resolvePath($dir);
            if (!is_dir($path)) {
                if (!@mkdir($path, 0700, true) && !is_dir($path)) {
                    $errors[] = "no se pudo crear {$path}";
                    continue;
                }
            }

            $perms = substr(sprintf('%o', fileperms($path)), -4);
            if (in_array($perms, ['0777', '0775', '0766'], true)) {
                $warnings[] = "permisos amplios en {$this->relativePath($path)} ({$perms})";
            }
        }

        return [
            'ok' => $errors === [],
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    public function scanProjectForSecrets(): array
    {
        $warnings = [];
        $errors = [];
        $matches = [];
        $maxMatches = (int)($this->salkConfig['max_secret_scan_matches'] ?? 20);
        $patterns = $this->secretPatterns();
        $skipDirs = ['.git', 'runtime', 'vendor', 'node_modules'];
        $skipFiles = ['.env', '.env.example'];
        $skipExtensions = ['md', 'txt', 'log'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
                function ($current) use ($skipDirs, $skipFiles, $skipExtensions): bool {
                    $name = $current->getFilename();
                    if ($current->isDir()) {
                        return !in_array($name, $skipDirs, true);
                    }
                    if (in_array($name, $skipFiles, true)) {
                        return false;
                    }
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    return !in_array($ext, $skipExtensions, true);
                }
            )
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            if ($file->getSize() > 1_000_000) {
                continue;
            }
            $path = $file->getPathname();
            $content = @file_get_contents($path);
            if (!is_string($content)) {
                continue;
            }
            foreach ($patterns as $name => $pattern) {
                if (preg_match($pattern, $content) === 1) {
                    $matches[] = [
                        'file' => $this->relativePath($path),
                        'pattern' => $name,
                    ];
                    if (count($matches) >= $maxMatches) {
                        break 2;
                    }
                }
            }
        }

        if ($matches !== []) {
            $warnings[] = 'posibles secretos detectados fuera de .env; revisar antes de publicar';
        }

        return [
            'ok' => true,
            'matches' => $matches,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * Revisa vectores de supply-chain que no pertenecen al modo PHP puro.
     *
     * Regla JAH:
     * - package.json / node_modules / locks de Node no deben formar parte del runtime.
     * - composer.json es PHP, pero se audita si tiene scripts/plugins peligrosos.
     * - JSON se permite solo como transporte externo API/Qwen, no como motor interno.
     */
    public function checkPackageJsonVectors(): array
    {
        $warnings = [];
        $errors = [];
        $files = [];
        $dangerousScripts = [];
        $publicExposure = [];

        $publicPath = $this->realOrFallback($this->root . '/public');

        $nodeArtifacts = [
            'package.json',
            'package-lock.json',
            'npm-shrinkwrap.json',
            'yarn.lock',
            'pnpm-lock.yaml',
            'node_modules',
        ];

        foreach ($nodeArtifacts as $artifact) {
            $path = $this->root . DIRECTORY_SEPARATOR . $artifact;
            if (file_exists($path)) {
                $files[$artifact] = $this->relativePath($path);
                $errors[] = "{$artifact} detectado; JAH MemoryAgent debe mantenerse en modo PHP puro sin Node/npm";
            }

            $publicArtifact = $this->root . '/public/' . $artifact;
            if (file_exists($publicArtifact)) {
                $publicExposure[] = $artifact;
                $errors[] = "{$artifact} está expuesto dentro de public/";
            }
        }

        $composerJson = $this->root . '/composer.json';
        if (is_file($composerJson)) {
            $files['composer.json'] = $this->relativePath($composerJson);
            $composer = $this->readJsonFile($composerJson);
            if ($composer === null) {
                $warnings[] = 'composer.json existe pero no se pudo leer como JSON válido';
            } else {
                $scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
                $this->collectDangerousScriptFindings('composer.json', $scripts, $dangerousScripts);

                $allowPlugins = $composer['config']['allow-plugins'] ?? null;
                if ($allowPlugins === true) {
                    $warnings[] = 'composer.json permite todos los plugins; usa allow-plugins con lista explícita';
                }
            }
        }

        $composerLock = $this->root . '/composer.lock';
        if (is_file($composerLock)) {
            $files['composer.lock'] = $this->relativePath($composerLock);
        }

        foreach (['composer.json', 'composer.lock'] as $artifact) {
            $publicArtifact = $this->root . '/public/' . $artifact;
            if (is_file($publicArtifact)) {
                $publicExposure[] = $artifact;
                $errors[] = "{$artifact} no debe estar expuesto dentro de public/";
            }
        }

        if ($dangerousScripts !== []) {
            $errors[] = 'se detectaron scripts de instalación/ejecución peligrosos en manifiestos';
        }

        return [
            'ok' => $errors === [],
            'mode' => 'php_puro_actionscript_php',
            'json_policy' => [
                'public_transport_layer' => 'app/http/JsonTransport.php',
                'allowed_public_json' => ['http_api_transport', 'qwen_cloud_payload', 'qwen_cloud_response'],
                'allowed_internal_serialization' => ['salk_audit_ndjson', 'datacore_tier_serialization'],
                'forbidden' => ['api_keys_in_json', 'package_json_runtime', 'node_modules', 'internal_actions_as_json', 'internal_config_as_json'],
            ],
            'node_detected' => array_key_exists('package.json', $files) || array_key_exists('node_modules', $files),
            'files' => $files,
            'public_exposure' => $publicExposure,
            'dangerous_scripts' => $dangerousScripts,
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Valida que un payload JSON público no contenga secretos.
     * Útil antes de responder en api.php/agent.php o antes de guardar traces.
     */
    public function validatePublicJsonPayload(array $payload, string $context = 'json.public'): array
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $encoded = is_string($encoded) ? $encoded : '';

        $errors = [];
        $warnings = [];

        if ($encoded !== '' && $this->containsSecret($encoded)) {
            $errors[] = 'payload JSON público contiene un posible secreto';
        }

        foreach (['authorization', 'bearer', 'password', 'secret', 'token'] as $key) {
            if ($this->arrayHasSensitiveKey($payload, $key)) {
                $warnings[] = "payload JSON público contiene campo sensible de diagnóstico: {$key}";
            }
        }

        $result = [
            'ok' => $errors === [],
            'context' => $context,
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
        ];

        $this->auditEvent('salk.validate_public_json', $result, ['context' => $context]);
        return $this->maskSecrets($result);
    }

    public function auditEvent(string $event, array $result = [], array $metadata = []): array
    {
        $record = $this->maskSecrets([
            'ts' => (new DateTimeImmutable('now', new DateTimeZone((string)($this->config['timezone'] ?? 'UTC'))))->format(DATE_ATOM),
            'event' => $event,
            'status' => ($result['ok'] ?? $result['status'] ?? null) === false ? 'warning' : 'ok',
            'result' => $result,
            'metadata' => $metadata,
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ],
        ]);

        $json = json_encode($record, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('no se pudo serializar auditoría SALK');
        }

        $this->ensureAuditDirectory();
        file_put_contents($this->auditFile, $json . "\n", FILE_APPEND | LOCK_EX);

        return [
            'stored' => true,
            'audit_file' => $this->relativePath($this->auditFile),
            'event' => $event,
        ];
    }

    public function maskSecrets(mixed $value): mixed
    {
        if (is_array($value)) {
            $masked = [];
            foreach ($value as $key => $item) {
                $keyString = is_string($key) ? $key : (string)$key;
                if ($this->isSensitiveKey($keyString) && !is_array($item) && !is_object($item)) {
                    $masked[$key] = $this->maskText((string)$item);
                } else {
                    $masked[$key] = $this->maskSecrets($item);
                }
            }
            return $masked;
        }

        if (is_object($value)) {
            return $this->maskSecrets((array)$value);
        }

        if (is_string($value)) {
            return $this->maskText($value);
        }

        return $value;
    }

    public function maskText(string $text): string
    {
        $masked = $text;

        foreach ($this->knownSecrets as $secret) {
            if ($secret !== '') {
                $masked = str_replace($secret, $this->maskFixed($secret), $masked);
            }
        }

        $masked = preg_replace('/Bearer\s+[^\s"\']+/i', 'Bearer [SALK_MASKED]', $masked) ?? $masked;
        $masked = preg_replace('/sk-[A-Za-z0-9_\-]{12,}/', 'sk-[SALK_MASKED]', $masked) ?? $masked;
        $masked = preg_replace('/(QWEN_API_KEY\s*=\s*)[^\s\n\r]+/i', '$1[SALK_MASKED]', $masked) ?? $masked;
        $masked = preg_replace('/("api_key"\s*:\s*")[^"]+(")/i', '$1[SALK_MASKED]$2', $masked) ?? $masked;
        $masked = preg_replace('/(Authorization\s*:\s*Bearer\s+)[^\s\n\r]+/i', '$1[SALK_MASKED]', $masked) ?? $masked;

        return $masked;
    }

    public function containsSecret(string $text): bool
    {
        if (preg_match('/sk-[A-Za-z0-9_\-]{12,}/', $text) === 1) {
            return true;
        }
        if (preg_match('/QWEN_API_KEY\s*=\s*[^\s\n\r]+/i', $text) === 1) {
            return true;
        }
        if (preg_match('/Authorization\s*:\s*Bearer\s+[^\s\n\r]+/i', $text) === 1) {
            return true;
        }
        foreach ($this->knownSecrets as $secret) {
            if ($secret !== '' && str_contains($text, $secret)) {
                return true;
            }
        }
        return false;
    }

    public function getSecret(string $name): string
    {
        $value = $_ENV[$name] ?? getenv($name) ?: '';
        return is_string($value) ? $value : '';
    }

    private function collectKnownSecrets(): void
    {
        foreach (['QWEN_API_KEY', 'DASHSCOPE_API_KEY', 'OPENAI_API_KEY'] as $key) {
            $secret = $this->getSecret($key);
            if ($secret !== '') {
                $this->knownSecrets[$key] = $secret;
            }
        }
    }

    private function secretSource(string $name): string
    {
        if (array_key_exists($name, $_ENV)) {
            return 'env_loaded';
        }
        $value = getenv($name);
        return is_string($value) && $value !== '' ? 'process_env' : 'missing';
    }

    private function readJsonFile(string $path): ?array
    {
        $content = @file_get_contents($path);
        if (!is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $scripts
     * @param array<int,array<string,string>> $findings
     */
    private function collectDangerousScriptFindings(string $source, array $scripts, array &$findings): void
    {
        $dangerous = [
            '/\bcurl\b/i',
            '/\bwget\b/i',
            '/\bbash\b/i',
            '/\bsh\s+-c\b/i',
            '/\bphp\s+-r\b/i',
            '/\beval\b/i',
            '/\bbase64_decode\b/i',
            '/\bnc\b|\bnetcat\b/i',
            '/\bnode\b/i',
            '/\bnpm\b/i',
            '/\byarn\b/i',
            '/\bpython\b/i',
            '/\brm\s+-rf\b/i',
        ];

        foreach ($scripts as $name => $script) {
            $commands = is_array($script) ? $script : [$script];
            foreach ($commands as $command) {
                $command = (string)$command;
                foreach ($dangerous as $pattern) {
                    if (preg_match($pattern, $command) === 1) {
                        $findings[] = [
                            'source' => $source,
                            'script' => (string)$name,
                            'pattern' => $pattern,
                            'command' => $this->maskText($command),
                        ];
                        break;
                    }
                }
            }
        }
    }

    private function arrayHasSensitiveKey(array $payload, string $needle): bool
    {
        foreach ($payload as $key => $value) {
            $key = strtolower((string)$key);
            if ($key === $needle || str_contains($key, $needle)) {
                return true;
            }
            if (is_array($value) && $this->arrayHasSensitiveKey($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function secretPatterns(): array
    {
        return [
            'qwen_or_dashscope_key' => '/sk-[A-Za-z0-9_\-]{20,}/',
            'authorization_bearer_key' => '/Authorization\s*:\s*Bearer\s+sk-[A-Za-z0-9_\-]{12,}/i',
            'hardcoded_api_key' => '/[\'\"]api_key[\'\"]\s*=>\s*[\'\"]sk-[A-Za-z0-9_\-]{12,}/i',
        ];
    }

    private function isSensitiveKey(string $key): bool
    {
        $k = strtolower($key);
        return str_contains($k, 'key')
            || str_contains($k, 'secret')
            || str_contains($k, 'token')
            || str_contains($k, 'authorization')
            || str_contains($k, 'bearer')
            || str_contains($k, 'password');
    }

    private function maskFixed(string $secret): string
    {
        if (strlen($secret) <= 8) {
            return '[SALK_MASKED]';
        }
        return substr($secret, 0, 3) . '[SALK_MASKED]' . substr($secret, -3);
    }

    private function fingerprint(string $secret): string
    {
        return substr(hash('sha256', $secret), 0, 16);
    }

    private function ensureAuditDirectory(): void
    {
        $dir = dirname($this->auditFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }
        if ($path[0] !== DIRECTORY_SEPARATOR) {
            $path = $this->root . DIRECTORY_SEPARATOR . $path;
        }
        return $this->realOrFallback($path);
    }

    private function realOrFallback(string $path): string
    {
        $real = realpath($path);
        return $real !== false ? $real : $path;
    }

    private function isInside(string $path, string $parent): bool
    {
        $path = rtrim($this->realOrFallback($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $parent = rtrim($this->realOrFallback($parent), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $parent);
    }

    private function relativePath(string $path): string
    {
        $full = $this->realOrFallback($path);
        $root = rtrim($this->realOrFallback($this->root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($full, $root) ? substr($full, strlen($root)) : $full;
    }
}
