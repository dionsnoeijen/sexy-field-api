<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Api\Utils;

use Symfony\Component\HttpFoundation\Request;

class AccessControlAllowOrigin
{
    const ACCESS_CONTROL_ALLOWED_ORIGINS = 'ACCESS_CONTROL_ALLOWED_ORIGINS';

    public static function get(Request $request): string
    {
        $origin = $request->headers->get('Origin');

        $accessControlAllowOrigin = 'null';
        if (!empty($_ENV[self::ACCESS_CONTROL_ALLOWED_ORIGINS])) {
            $origins = explode(',', $_ENV[self::ACCESS_CONTROL_ALLOWED_ORIGINS]);
            if (in_array($origin, $origins)) {
                $accessControlAllowOrigin = $origins[array_search($origin, $origins)];
            }
        }

        return $accessControlAllowOrigin;
    }
}
