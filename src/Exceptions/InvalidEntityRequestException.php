<?php

namespace Comhon\EntityRequester\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class InvalidEntityRequestException extends \Exception
{
    public function __construct(string $message, array $params = [])
    {
        parent::__construct(__("entity-requester::validation.$message", $params));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
