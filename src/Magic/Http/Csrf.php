<?php

namespace Magic\Http;

/**
 * CSRF token derived deterministically from the auth cookie + a server salt.
 * Same value every call within a session, so the page can render it once and
 * the API layer can verify without server-side state.
 */
final class Csrf
{
    private const SALT = 'csrf-salt';
    private const COOKIE = 'auth_token';

    public static function token(): string
    {
        return hash_hmac('sha256', $_COOKIE[self::COOKIE] ?? '', self::SALT);
    }

    public static function verify(string $supplied): bool
    {
        return hash_equals(self::token(), $supplied);
    }
}
