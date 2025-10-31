<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\DTOs\EntityRequest;

interface AccessValidatorInterface
{
    public function validate(EntityRequest $entityRequest);
}
