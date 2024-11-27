<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;

class RefreshCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 're:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears and rebuilds all cache, clears compiled and dumps autoload.';

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
        $this->comment('Optimizing...');
        $this->call('optimize');

        $this->comment('');
        $this->call('route:cache');

        $this->comment('');
        $this->comment('Clearing compiled...');
        $this->call('clear-compiled');

        $this->comment('');
        $this->comment('Clearing schedule cache...');
        $this->call('schedule:clear-cache');

        $this->comment('');
        $this->comment('Dumping autoload files...');
        $this->comment('(If this throws an error, run "composer dump-autoload" manually)');
        exec('composer dump-autoload');

        $this->comment('.');
        sleep(1);
        $this->comment('.');
        sleep(1);
        $this->comment('.');

        $this->call('route:list');
        $this->comment('All done! :)');
    }
}
