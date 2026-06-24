<?php

declare(strict_types=1);

namespace Jah\DataCore\Test;

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../src/SecurityAgent.php';

use Jah\DataCore\SecurityAgent;

final class SecurityAgentTest
{
    private string $testDir;

    public function __construct()
    {
        $this->testDir = sys_get_temp_dir() . '/jah_security_test_' . bin2hex(random_bytes(4));
        mkdir($this->testDir, 0700, true);
    }

    private array $results = [];

    public function run(): array
    {
        echo "=== SECURITY AGENT TEST SUITE ===\n\n";

        $this->results = [];
        $this->runTest('register_user', fn() => $this->testRegister('register'));
        $this->runTest('register_duplicate', fn() => $this->testRegisterDuplicate('register_duplicate'));
        $this->runTest('login_success', fn() => $this->testLoginSuccess('login_success'));
        $this->runTest('login_failure', fn() => $this->testLoginFailure('login_failure'));
        $this->runTest('logout', fn() => $this->testLogout('logout'));
        $this->runTest('validate_session', fn() => $this->testValidateSession('validate_session'));
        $this->runTest('change_password', fn() => $this->testChangePassword('change_password'));
        $this->runTest('hash_password', fn() => $this->testHashPassword('hash_password'));
        $this->runTest('verify_password', fn() => $this->testVerifyPassword('verify_password'));
        $this->runTest('list_users', fn() => $this->testListUsers('list_users'));
        $this->runTest('delete_user', fn() => $this->testDeleteUser('delete_user'));
        $this->runTest('validation_short_username', fn() => $this->testValidationShortUsername('validation_short_username'));
        $this->runTest('validation_short_password', fn() => $this->testValidationShortPassword('validation_short_password'));
        $this->runTest('session_persistence', fn() => $this->testSessionPersistence('session_persistence'));

        return $this->results;
    }

    private function runTest(string $name, callable $fn): void
    {
        $start = hrtime(true);
        try {
            $fn();
            $this->results[$name] = ['ok' => true];
            echo "{$name}: ✓ " . round((hrtime(true) - $start) / 1_000_000, 2) . "ms\n";
        } catch (\Throwable $e) {
            $this->results[$name] = ['ok' => false, 'error' => $e->getMessage()];
            echo "{$name}: ✗ {$e->getMessage()}\n";
        }
    }

    private function security(string $name = ''): SecurityAgent
    {
        $path = $name === '' ? $this->testDir : $this->testDir . '/' . $name;
        if ($name !== '') {
            mkdir($path, 0700, true);
        }
        return new SecurityAgent($path);
    }

    private function testRegister(string $name): void
    {
        $s = $this->security($name);
        $user = $s->register('admin', 'secret123', ['admin']);
        $this->assert(isset($user['id']), 'Missing id');
        $this->assert($user['username'] === 'admin', 'Wrong username');
        $this->assert($user['roles'] === ['admin'], 'Wrong roles');
        $this->assert(!isset($user['password_hash']), 'Password hash leaked');
    }

    private function testRegisterDuplicate(string $name): void
    {
        $s = $this->security($name);
        $s->register('admin', 'secret123');
        $this->expectInvalidArgument(fn() => $s->register('admin', 'secret123'));
    }

    private function testLoginSuccess(string $name): void
    {
        $s = $this->security($name);
        $s->register('user', 'password1');
        $result = $s->login('user', 'password1');
        $this->assert(isset($result['token']), 'Missing token');
        $this->assert(isset($result['user']['username']), 'Missing user');
        $this->assert($result['user']['username'] === 'user', 'Wrong user returned');
    }

    private function testLoginFailure(string $name): void
    {
        $s = $this->security($name);
        $s->register('user', 'password1');
        $this->expectInvalidArgument(fn() => $s->login('user', 'wrong'));
        $this->expectInvalidArgument(fn() => $s->login('unknown', 'password1'));
    }

    private function testLogout(string $name): void
    {
        $s = $this->security($name);
        $s->register('user', 'password1');
        $result = $s->login('user', 'password1');
        $token = $result['token'];
        $s->logout($token);
        $this->assert($s->validateSession($token) === null, 'Session still valid after logout');
    }

    private function testValidateSession(string $name): void
    {
        $s = $this->security($name);
        $s->register('user', 'password1');
        $result = $s->login('user', 'password1');
        $user = $s->validateSession($result['token']);
        $this->assert($user !== null, 'Valid session not found');
        $this->assert($user['username'] === 'user', 'Wrong user in session');
        $this->assert($s->validateSession('invalid-token') === null, 'Invalid token returned user');
    }

    private function testChangePassword(string $name): void
    {
        $s = $this->security($name);
        $s->register('user', 'password1');
        $s->changePassword('user', 'password1', 'newpass1');
        $this->expectInvalidArgument(fn() => $s->login('user', 'password1'));
        $result = $s->login('user', 'newpass1');
        $this->assert(isset($result['token']), 'Login with new password failed');
    }

    private function testHashPassword(string $name): void
    {
        $s = $this->security($name);
        $hash = $s->hashPassword('secret');
        $this->assert(is_string($hash), 'Hash is not string');
        $this->assert(strlen($hash) > 0, 'Hash is empty');
        $this->assert($s->verifyPassword('secret', $hash), 'Verification failed for valid password');
    }

    private function testVerifyPassword(string $name): void
    {
        $s = $this->security($name);
        $hash = $s->hashPassword('secret');
        $this->assert($s->verifyPassword('secret', $hash), 'Valid password rejected');
        $this->assert(!$s->verifyPassword('wrong', $hash), 'Invalid password accepted');
    }

    private function testListUsers(string $name): void
    {
        $s = $this->security($name);
        $s->register('alice', 'passw0rd1', ['admin']);
        $s->register('bob', 'passw0rd2');
        $users = $s->listUsers();
        $this->assert(count($users) === 2, 'Expected 2 users');
        $names = array_column($users, 'username');
        $this->assert(in_array('alice', $names, true), 'Alice not found');
    }

    private function testDeleteUser(string $name): void
    {
        $s = $this->security($name);
        $s->register('user', 'password1');
        $s->deleteUser('user');
        $this->assert(count($s->listUsers()) === 0, 'User not deleted');
    }

    private function testValidationShortUsername(string $name): void
    {
        $s = $this->security($name);
        $this->expectInvalidArgument(fn() => $s->register('ab', 'password1'));
    }

    private function testValidationShortPassword(string $name): void
    {
        $s = $this->security($name);
        $this->expectInvalidArgument(fn() => $s->register('user', 'short'));
    }

    private function testSessionPersistence(string $name): void
    {
        $path = $this->testDir . '/' . $name;
        mkdir($path, 0700, true);
        $s1 = new SecurityAgent($path);
        $s1->register('user', 'password1');
        $result = $s1->login('user', 'password1');
        $token = $result['token'];

        $s2 = new SecurityAgent($path);
        $user = $s2->validateSession($token);
        $this->assert($user !== null, 'Session not persisted across instances');
        $this->assert($user['username'] === 'user', 'Wrong user in persisted session');
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    private function expectInvalidArgument(callable $fn): void
    {
        try {
            $fn();
            throw new \RuntimeException('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            // expected
        }
    }
}

// Run via tests/run.php
