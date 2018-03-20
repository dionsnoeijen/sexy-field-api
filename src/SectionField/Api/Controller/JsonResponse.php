<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\Api\Controller;

class JsonResponse extends \Symfony\Component\HttpFoundation\JsonResponse
{
    public function __construct($data = null, $status = 200, array $headers = array(), $json = false)
    {
        // Make the origin a configurable option
        $headers = array_merge($headers, [
            'Access-Control-Allow-Origin' => '*'
        ]);

        parent::__construct($data, $status, $headers, $json);
    }
}
