<?php

declare(strict_types=1);

namespace Jah\Agents;

use Jah\Security\JahSalkToken;

final class SalkAgent extends BaseAgent
{
    protected function onBoot(): void
    {
        $this->subscribeToEvent('salk.event_received');
    }

    public function handle(array $event): void
    {
        if (($event['type'] ?? '') === 'salk.event_received') {
            $this->log('SALK recibio evento firmado para validacion.', 'debug');
        }
    }

    public function birthAgent(string $agentId, string $agentClass, string $engineId, string $bootId, array $capabilities = []): array
    {
        $payload = [
            'purpose' => 'agent_birth',
            'agent_id' => $agentId,
            'agent_class' => $agentClass,
            'engine_id' => $engineId,
            'boot_id' => $bootId,
            'capabilities' => $capabilities,
            'expires' => time() + 3600,
        ];

        return [
            'payload' => $payload,
            'signature' => JahSalkToken::make($payload, 3600),
        ];
    }

    public function validateAgentIdentity(array $identity, string $agentId, string $agentClass, string $engineId, string $bootId): array
    {
        $token = (string) ($identity['signature'] ?? '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'SALK_AGENT_SIGNATURE_MISSING'];
        }

        $verified = JahSalkToken::verify($token);
        if (!$verified['ok']) {
            return $verified;
        }

        $payload = $verified['payload'];
        foreach ([
            'purpose' => 'agent_birth',
            'agent_id' => $agentId,
            'agent_class' => $agentClass,
            'engine_id' => $engineId,
            'boot_id' => $bootId,
        ] as $key => $expected) {
            if (($payload[$key] ?? null) !== $expected) {
                return ['ok' => false, 'error' => 'SALK_AGENT_IDENTITY_MISMATCH'];
            }
        }

        return ['ok' => true, 'payload' => $payload];
    }

    public function makeComponentToken(array $context): string
    {
        $context['purpose'] = $context['purpose'] ?? 'component_event';
        $context['payload_hash'] = $context['payload_hash'] ?? JahSalkToken::payloadHash($context['payload'] ?? []);

        return JahSalkToken::make($context);
    }

    public function validateEvent(array $event): array
    {
        $token = (string) ($event['salk_token'] ?? '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'SALK_TOKEN_MISSING'];
        }

        $verified = JahSalkToken::verify($token);
        if (!$verified['ok']) {
            return $verified;
        }

        $payload = $verified['payload'];
        if (($payload['purpose'] ?? null) !== 'component_event') {
            return ['ok' => false, 'error' => 'SALK_PURPOSE_INVALID'];
        }

        if (($payload['event'] ?? null) !== ($event['event'] ?? null)) {
            return ['ok' => false, 'error' => 'SALK_EVENT_MISMATCH'];
        }

        if (($payload['component_id'] ?? null) !== ($event['component_id'] ?? null)) {
            return ['ok' => false, 'error' => 'SALK_COMPONENT_MISMATCH'];
        }

        $eventPayload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        if (($payload['payload_hash'] ?? '') !== JahSalkToken::payloadHash($eventPayload)) {
            return ['ok' => false, 'error' => 'SALK_PAYLOAD_MISMATCH'];
        }

        return ['ok' => true, 'payload' => $payload];
    }
}
