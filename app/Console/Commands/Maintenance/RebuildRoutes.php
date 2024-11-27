<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;

class RebuildRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 're:route';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears route cache and lists routes';

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
    public function handle()
    {
        $this->comment('');
        $this->comment('Clearing route cache...');
        $this->call('route:clear');
        $this->comment('Rebuilding route cache...');
        $this->call('route:cache');
        $this->comment('Done. Here are the routes:');
        $this->call('route:list');
    }
}
