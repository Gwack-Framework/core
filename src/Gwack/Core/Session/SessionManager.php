<?php

namespace Gwack\Core\Session;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Session Manager
 *
 * @package Gwack\Core\Session
 */
class SessionManager
{
    private Session $session;
    private array $config;

    /**
     * SessionManager constructor
     *
     * @param array $config Session configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'driver' => 'file',
            'lifetime' => 120, // minutes
            'path' => sys_get_temp_dir() . '/sessions',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'lax'
        ], $config);

        $this->initializeSession();
    }

    /**
     * Initialize the session
     *
     * @return void
     */
    private function initializeSession(): void
    {
        $storage = $this->createSessionStorage();
        $this->session = new Session($storage);
        $this->session->start();
    }

    /**
     * Create session storage based on configuration
     *
     * @return mixed
     */
    private function createSessionStorage()
    {
        switch ($this->config['driver']) {
            case 'native':
                return new NativeSessionStorage([
                    'cookie_lifetime' => $this->config['lifetime'] * 60,
                    'cookie_path' => $this->config['path'] ?? '/',
                    'cookie_domain' => $this->config['domain'],
                    'cookie_secure' => $this->config['secure'],
                    'cookie_httponly' => $this->config['http_only'],
                    'cookie_samesite' => $this->config['same_site'],
                ]);

            case 'file':
            default:
                return new PhpBridgeSessionStorage();
        }
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function authenticated(): bool
    {
        return $this->session->has('user_id') && $this->session->get('user_id') !== null;
    }

    /**
     * Get the authenticated user
     *
     * @return User|null
     */
    public function user(): ?User
    {
        if (!$this->authenticated()) {
            return null;
        }

        $userData = $this->session->get('user_data');
        if ($userData) {
            return User::fromArray($userData);
        }

        // If we only have user_id, load from database
        $userId = $this->session->get('user_id');
        // TODO: Integrate with database/ORM when available
        return new User(['id' => $userId]);
    }

    /**
     * Authenticate a user
     *
     * @param User $user
     * @return void
     */
    public function authenticate(User $user): void
    {
        $this->session->set('user_id', $user->getId());
        $this->session->set('user_data', $user->toArray());
        $this->session->migrate();
    }

    /**
     * Logout the current user
     *
     * @return void
     */
    public function logout(): void
    {
        $this->session->remove('user_id');
        $this->session->remove('user_data');
        $this->session->invalidate();
    }

    /**
     * Get a session value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session->get($key, $default);
    }

    /**
     * Set a session value
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->session->set($key, $value);
    }

    /**
     * Check if session has a key
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->session->has($key);
    }

    /**
     * Remove a session key
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void
    {
        $this->session->remove($key);
    }

    /**
     * Flash a message for the next request
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function flash(string $key, mixed $value): void
    {
        $this->session->getFlashBag()->add($key, $value);
    }

    /**
     * Get flashed messages
     *
     * @param string $key
     * @return array
     */
    public function getFlashed(string $key): array
    {
        return $this->session->getFlashBag()->get($key);
    }

    /**
     * Get the underlying session instance
     *
     * @return Session
     */
    public function getSession(): Session
    {
        return $this->session;
    }
}
