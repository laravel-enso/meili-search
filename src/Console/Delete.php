<?php

namespace LaravelEnso\MeiliSearch\Console;

use Illuminate\Console\Command;
use LaravelEnso\MeiliSearch\Services\MeiliSearch;

class Delete extends Command
{
    protected $signature = 'enso:meilisearch:delete
        {model : The model for which the index will be deleted}';

    protected $description = 'Delete an index';

    public function handle()
    {
        $model = $this->argument('model');

        MeiliSearch::deleteIndex($model);

        $this->info("Index for [{$model}] deleted.");
    }
}
