<?php

declare(strict_types=1);

namespace Jah\DataCore;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * SecurityAgent - Authentication and authorization layer.
 *
 * Stores users and sessions inside the database directory using NDJSON files,
 * keeping the zero-dependency promise of DataCore.
 */
final class SecurityAgent
{
    private string $basePath;
    private string $usersFile;
    private string $sessionsFile;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->usersFile = $this->basePath . '/security/users.ndjson';
        $this->sessionsFile = $this->basePath . '/security/sessions.ndjson';
        $this->init();
    }

    private function init(): void
    {
        foreach ([$this->basePath . '/security'] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException("Cannot create security directory: {$dir}");
            }
        }
    }

    /**
     * Create a new user. Password is hashed with bcrypt.
     *
     * @throws InvalidArgumentException If username already exists or validation fails.
     */
    public function register(string $username, string $password, array $roles = []): array
    {
        $this->validateCredentials($username, $password);

        $users = $this->loadUsers();
        if (isset($users[$username])) {
            throw new InvalidArgumentException('Username already exists');
        }

        $user = [
            'id' => bin2hex(random_bytes(16)),
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'roles' => $roles,
            'created_at' => time(),
            'last_login' => null,
        ];

        $users[$username] = $user;
        $this->saveUsers($users);

        return $this->sanitizeUser($user);
    }

    /**
     * Authenticate user and return a session token.
     *
     * @return array{token: string, user: array}
     *
     * @throws InvalidArgumentException On invalid credentials.
     */
    public function login(string $username, string $password): array
    {
        $users = $this->loadUsers();
        if (!isset($users[$username])) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        $user = $users[$username];
        if (!password_verify($password, $user['password_hash'])) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $users[$username] = $user;
            $this->saveUsers($users);
        }

        $user['last_login'] = time();
        $users[$username] = $user;
        $this->saveUsers($users);

        $token = $this->createSession($user['id']);

        return [
            'token' => $token,
            'user' => $this->sanitizeUser($user),
        ];
    }

    /**
     * Invalidate a session by token.
     */
    public function logout(string $token): void
    {
        $sessions = $this->loadSessions();
        unset($sessions[$token]);
        $this->saveSessions($sessions);
    }

    /**
     * Validate a session token and return the associated user.
     *
     * @return array|null
     */
    public function validateSession(string $token): ?array
    {
        $sessions = $this->loadSessions();
        if (!isset($sessions[$token])) {
            return null;
        }

        $users = $this->loadUsers();
        foreach ($users as $user) {
            if ($user['id'] === $sessions[$token]['user_id']) {
                return $this->sanitizeUser($user);
            }
        }

        return null;
    }

    /**
     * Change password for an authenticated user.
     */
    public function changePassword(string $username, string $currentPassword, string $newPassword): void
    {
        $this->validateCredentials($username, $newPassword);

        $users = $this->loadUsers();
        if (!isset($users[$username])) {
            throw new InvalidArgumentException('User not found');
        }

        $user = $users[$username];
        if (!password_verify($currentPassword, $user['password_hash'])) {
            throw new InvalidArgumentException('Current password is incorrect');
        }

        $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $users[$username] = $user;
        $this->saveUsers($users);
    }

    /**
     * Hash a password without storing it.
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify a password against a hash.
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * List all users (without password hashes).
     *
     * @return array<int, array>
     */
    public function listUsers(): array
    {
        $users = $this->loadUsers();
        return array_values(array_map([$this, 'sanitizeUser'], $users));
    }

    /**
     * Delete a user and invalidate all their sessions.
     */
    public function deleteUser(string $username): void
    {
        $users = $this->loadUsers();
        if (!isset($users[$username])) {
            throw new InvalidArgumentException('User not found');
        }

        $userId = $users[$username]['id'];
        unset($users[$username]);
        $this->saveUsers($users);

        $sessions = $this->loadSessions();
        foreach ($sessions as $token => $session) {
            if ($session['user_id'] === $userId) {
                unset($sessions[$token]);
            }
        }
        $this->saveSessions($sessions);
    }

    private function createSession(string $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $sessions = $this->loadSessions();
        $sessions[$token] = [
            'token' => $token,
            'user_id' => $userId,
            'created_at' => time(),
            'expires_at' => time() + 86400,
        ];
        $this->saveSessions($sessions);

        return $token;
    }

    private function loadUsers(): array
    {
        if (!file_exists($this->usersFile)) {
            return [];
        }

        $content = file_get_contents($this->usersFile);
        if ($content === false || $content === '') {
            return [];
        }

        $users = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $record = json_decode($line, true);
            if (is_array($record) && isset($record['username'])) {
                $users[$record['username']] = $record;
            }
        }

        return $users;
    }

    private function saveUsers(array $users): void
    {
        $this->writeNdjson($this->usersFile, array_values($users));
    }

    private function loadSessions(): array
    {
        if (!file_exists($this->sessionsFile)) {
            return [];
        }

        $content = file_get_contents($this->sessionsFile);
        if ($content === false || $content === '') {
            return [];
        }

        $sessions = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $record = json_decode($line, true);
            if (is_array($record) && isset($record['token'])) {
                $sessions[$record['token']] = $record;
            }
        }

        return $sessions;
    }

    private function saveSessions(array $sessions): void
    {
        $this->writeNdjson($this->sessionsFile, array_values($sessions));
    }

    private function writeNdjson(string $file, array $records): void
    {
        $lines = [];
        foreach ($records as $record) {
            try {
                $lines[] = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            } catch (JsonException $e) {
                throw new RuntimeException('Cannot encode security record', 0, $e);
            }
        }

        file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX);
    }

    private function sanitizeUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'roles' => $user['roles'],
            'created_at' => $user['created_at'],
            'last_login' => $user['last_login'],
        ];
    }

    private function validateCredentials(string $username, string $password): void
    {
        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Username and password are required');
        }

        if (strlen($username) < 3 || strlen($username) > 64) {
            throw new InvalidArgumentException('Username must be 3-64 characters');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }
    }
}
