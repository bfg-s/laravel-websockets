<?php

namespace Bfg\LaravelWebSockets\Helpers;

class BladeDirective
{
    public static function verifiedJsResponse(array $options = [])
    {
        return response(static::verifiedJs($options))
            ->header('Content-Type', 'application/javascript');
    }

    public static function verifiedJs(array $options = [])
    {
        $js = file_get_contents(public_path('vendor/websockets/ws.js'));
        $js .= "(function () {".static::jsInstanceGenerator($options)."})();";

        return $js;
    }

    public static function jsInstanceGenerator(array $options = [])
    {
        return "window.WebSocketConnect(".json_encode(static::makeDefaultOptions($options)).");";
    }

    public static function directiveScripts($expression)
    {
        $class = static::class;
        return <<<HTML
<script src="<?php echo asset('vendor/websockets/ws.js'); ?>" defer></script>
<script type='text/javascript'><?php echo \\$class::jsInstanceGenerator($expression); ?></script>
HTML;
    }

    public static function directiveInline($expression)
    {
        $class = static::class;
        return <<<HTML
<script type='text/javascript'><?php echo \\$class::verifiedJs($expression); ?></script>
HTML;
    }

    protected static function makeDefaultOptions(array $options = [])
    {
        $cfg = config('broadcasting.connections.pusher');

        if (!isset($options['ssl']) && isset($cfg['options']['scheme'])) {
            $options['ssl'] = $cfg['options']['scheme'] === "https";
        }

        if (!isset($options['host'])) {
            $url = explode("://", config('app.url'));
            $options['host'] = $url[1] ?? null;
        };

        if (!isset($options['port']) && isset($cfg['options']['port'])) {
            $options['port'] = $cfg['options']['port'];
        }

        if (!isset($options['key'])) {
            $options['key'] = $cfg['key'];
        }

        if (!isset($options['token'])) {
            $options['token'] = session()->token();
        }

        if (!isset($options['cluster']) && isset($cfg['options']['cluster'])) {
            $options['cluster'] = $cfg['options']['cluster'];
        }

        if (!isset($options['encrypted']) && isset($cfg['options']['encrypted'])) {
            $options['encrypted'] = $cfg['options']['encrypted'];
        }

        if (
            !isset($options['headers']['Authorization'])
            && $authHeader = request()->header('Authorization')
        ) {
            $options['headers']['Authorization'] = $authHeader;
        }

        if (!isset($options['user_id'])) {
            $options['user_id'] = \Auth::id();
        }

        return $options;
    }
}
