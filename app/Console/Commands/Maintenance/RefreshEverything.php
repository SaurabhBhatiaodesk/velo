<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;

class RefreshEverything extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 're:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears all cache, rebuilds database, reseeds it and clears cache again just for kicks.';

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
        if (config('app.env') === 'testing') {
            $this->comment('Droping all tables...');
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            foreach (\DB::select('SHOW TABLES') as $table) {
                $table_array = get_object_vars($table);
                \Schema::drop($table_array[key($table_array)]);
            }
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->comment('');
            $this->comment('Migrating database...');
            $this->call('migrate');

            $this->comment('Seeding database...');
            $this->call('db:seed');

            $this->comment('Patching database...');
        }

        $this->comment('Optimizing...');
        $this->call('optimize:clear');

        $this->comment('');
        $this->comment('Caching...');
        $this->call('optimize');

        $this->comment('');
        $this->comment('Clearing compiled...');
        $this->call('clear-compiled');

        $this->comment('');
        $this->comment('Dumping autoload files...');
        $this->comment('(If this fails, run "composer dump-autoload" manually)');
        exec('composer dump-autoload');

        $this->comment('');
        $this->comment('');
        $this->comment('All done! :)');
    }
}
