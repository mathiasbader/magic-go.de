<?php

namespace Magic;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Static facade around Twig — keeps the call sites in entry points small:
 *   View::display('landing.html.twig', ['error' => $error]);
 *
 * Templates live in /templates, compiled cache in /var/cache/twig.
 * `auto_reload` is on so editing a .twig file doesn't require a cache flush;
 * `strict_variables` makes typos in template vars throw rather than silently
 * render empty strings.
 */
final class View
{
    private static ?Environment $twig = null;

    public static function render(string $template, array $vars = []): string
    {
        return self::env()->render($template, $vars);
    }

    public static function display(string $template, array $vars = []): void
    {
        echo self::render($template, $vars);
    }

    private static function env(): Environment
    {
        if (self::$twig === null) {
            $loader = new FilesystemLoader(__DIR__ . '/../../templates');
            self::$twig = new Environment($loader, [
                'cache' => __DIR__ . '/../../var/cache/twig',
                'auto_reload' => true,
                'strict_variables' => true,
            ]);
        }
        return self::$twig;
    }
}
