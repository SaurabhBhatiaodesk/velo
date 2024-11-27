<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use App\Models\Store;

class ReportInvalidSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:reportInvalidSlugs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends an email reporting of any invalid store slugs in the database';

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
        $res = [];
        foreach (Store::all() as $store) {
            if (!preg_match('/^[-a-z0-9]+$/D', $store->slug)) {
                $res[$store->slug] = true;
            }
        }

        if (count($res)) {
            \Illuminate\Support\Facades\Mail::to('itay@veloapp.io')->send(new \App\Mail\Admin\Error([
                'message' => 'Invalid store slugs detected in database',
                'error' => $res,
            ]));
        }
    }
}
