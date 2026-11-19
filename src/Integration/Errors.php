<?php

namespace Ernestdefoe\Connect\Integration;

use Laminas\Diactoros\Response\JsonResponse;

/** The error body every integration route answers with: {errors: [{status, code, detail?}]}. */
final class Errors
{
    public static function json(int $status, string $code, ?string $detail = null): JsonResponse
    {
        $error = ['status' => (string) $status, 'code' => $code];
        if ($detail !== null) {
            $error['detail'] = $detail;
        }

        return new JsonResponse(['errors' => [$error]], $status);
    }
}
