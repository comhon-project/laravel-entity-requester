<?php

namespace Comhon\EntityRequester\Commands;

use Illuminate\Console\Command;

class EntityRequesterCommand extends Command
{
    public $signature = 'laravel-entity-requester';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
