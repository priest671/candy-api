<?php

namespace GetCandy\Api\Core\Search\Commands;

use GetCandy\Api\Core\Categories\Models\Category;
use Ramsey\Uuid\Uuid;
use Illuminate\Console\Command;
use GetCandy\Api\Core\Search\SearchManager;
use Illuminate\Contracts\Events\Dispatcher;
use GetCandy\Api\Core\Search\SearchContract;
use GetCandy\Api\Core\Products\Models\Product;
use GetCandy\Api\Core\Search\Jobs\ReindexSearchJob;
use GetCandy\Api\Core\Search\Actions\IndexDocuments;

class IndexCategoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'candy:categories:reindex {batchsize=1000} {--queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindexes categories';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Dispatcher $events, SearchManager $manager)
    {
        $batchsize = (int) $this->argument('batchsize');
        $total = Category::withoutGlobalScopes()->count();

        $this->output->text('Indexing ' . $total . ' categories in ' . ceil($total / $batchsize) . ' batches');

        $batches = ceil($total / $batchsize);
        $bar = $this->output->createProgressBar($batches);

        $uuid = Uuid::uuid4()->toString();

        dump('Memory, Used: '. round(memory_get_usage() / 1048576,2) .'mb / Allocated: '. round(memory_get_usage(true) / 1048576,2) .'mb');

        Category::withoutGlobalScopes()->with([
            'attributes',
            'customerGroups',
            'channels',
        ])->chunk($batchsize, function ($categories, $index) use ($manager, $uuid, $batches, $bar, $events) {
            dump('Memory, Used: '. round(memory_get_usage() / 1048576,2) .'mb / Allocated: '. round(memory_get_usage(true) / 1048576,2) .'mb');
            dump((int) $batches, $index, $batches === $index);
            IndexDocuments::run([
                'driver' => $manager->with(
                    config('getcandy.search.driver')
                ),
                'documents' => $categories,
                'uuid' => $uuid,
                'final' => (int) $batches === $index,
            ]);
            $bar->advance();
        });
    }
}