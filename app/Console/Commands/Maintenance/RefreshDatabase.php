<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;

class RefreshDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 're:db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        // $this->comment('Droping all tables...');
        // \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // foreach(\DB::select('SHOW TABLES') as $table) {
        //     $table_array = get_object_vars($table);
        //     \Schema::drop($table_array[key($table_array)]);
        // }
        // \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        //
        // $this->comment('');
        // $this->comment('Migrating database...');
        // $this->call('migrate');
        //
        // $this->comment('');
        // $this->comment('Seeding database...');
        // $this->call('db:seed');

        $this->comment('');
        $this->comment('');
        $this->comment('All done! :)');
    }
}
