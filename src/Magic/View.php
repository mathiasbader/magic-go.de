<?php

namespace Magic;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

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
            self::$twig->addFilter(new TwigFilter('mana', self::manaFilter(...), ['is_safe' => ['html']]));
        }
        return self::$twig;
    }

    private static function manaFilter(?string $cost): string
    {
        if (!$cost) return '';
        return preg_replace_callback('/\{([^}]+)\}/', static function (array $m): string {
            $code = strtoupper(str_replace('/', '', $m[1]));
            $url = 'https://svgs.scryfall.io/card-symbols/' . rawurlencode($code) . '.svg';
            return '<img src="/img/cache?url=' . rawurlencode($url) . '" alt="{' . htmlspecialchars($m[1], ENT_QUOTES) . '}" style="width:1.1em;height:1.1em;vertical-align:-0.15em;margin-right:3px;">';
        }, $cost);
    }
}
