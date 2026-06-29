<?php

declare(strict_types=1);

use Jah\Actions\ActionScript;
use Jah\Security\SalkGuard;

require_once dirname(__DIR__, 2) . '/php_actionscript_php_doc/ActionScriptEngine.php';
require_once dirname(__DIR__) . '/security/SalkGuard.php';

/**
 * SalkSecurityActionScript
 * Acciones SALK en PHP puro para proteger DataCore y secretos del MemoryAgent.
 */
final class SalkSecurityActionScript
{
    private SalkGuard $guard;

    public function __construct(SalkGuard $guard)
    {
        $this->guard = $guard;
        $this->registerActions();
    }

    private function registerActions(): void
    {
        ActionScript::define('salk.preflight')
            ->timeout(3000)
            ->handler(fn(array $data): array => $this->guard->preflight((string)($data['context'] ?? 'runtime')));

        ActionScript::define('salk.check_env')
            ->timeout(1000)
            ->handler(fn(array $data): array => $this->guard->checkEnv());

        ActionScript::define('salk.protect_api_key')
            ->timeout(1000)
            ->handler(fn(array $data): array => $this->guard->protectApiKey());

        ActionScript::define('salk.check_datacore_path')
            ->timeout(1000)
            ->handler(fn(array $data): array => $this->guard->checkDataCorePath());

        ActionScript::define('salk.scan_package_vectors')
            ->timeout(1500)
            ->handler(fn(array $data): array => $this->guard->checkPackageJsonVectors());

        ActionScript::define('salk.validate_public_json')
            ->timeout(1000)
            ->handler(fn(array $data): array => $this->guard->validatePublicJsonPayload(
                is_array($data['payload'] ?? null) ? $data['payload'] : [],
                (string)($data['context'] ?? 'json.public')
            ));

        ActionScript::define('salk.verify_runtime_permissions')
            ->timeout(1000)
            ->handler(fn(array $data): array => $this->guard->verifyRuntimePermissions());

        ActionScript::define('salk.mask_secrets')
            ->timeout(1000)
            ->handler(fn(array $data): array => ['payload' => $this->guard->maskSecrets($data['payload'] ?? [])]);

        ActionScript::define('salk.audit_event')
            ->timeout(1000)
            ->handler(function (array $data): array {
                $event = (string)($data['event'] ?? 'salk.event');
                $result = is_array($data['result'] ?? null) ? $data['result'] : [];
                $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
                return $this->guard->auditEvent($event, $result, $metadata);
            });
    }
}
