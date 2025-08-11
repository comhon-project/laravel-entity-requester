<?php

namespace Comhon\EntityRequester\Interfaces;

interface ResponsableInterface
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(string $id, $request);
}
