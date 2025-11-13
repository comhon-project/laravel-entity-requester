<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\DTOs\EntityRequest;

interface RequestGateInterface
{
    public function authorize(EntityRequest $entityRequest);
}
