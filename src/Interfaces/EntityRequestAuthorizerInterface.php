<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\DTOs\EntityRequest;

interface EntityRequestAuthorizerInterface
{
    public function authorize(EntityRequest $entityRequest);
}
