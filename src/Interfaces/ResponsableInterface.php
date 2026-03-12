<?php

namespace Comhon\EntityRequester\Interfaces;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface ResponsableInterface
{
    /**
     * @param  Request  $request
     * @return Response
     */
    public function response(string $id, $request);
}
