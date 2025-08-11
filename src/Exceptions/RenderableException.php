<?php

namespace Comhon\EntityRequester\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class RenderableException extends \Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
