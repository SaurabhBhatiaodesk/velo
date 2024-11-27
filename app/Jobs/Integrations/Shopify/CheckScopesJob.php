<?php

namespace App\Jobs\Integrations\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Store;
use App\Repositories\Integrations\Shopify\IntegrationRepository;
use App\Events\Integrations\Shopify\AccessScopeRefreshNeeded;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

class CheckScopesJob implements ShouldQueue, ShouldBeUnique, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $repo;
    public $store;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Store $store)
    {

        $this->store = $store;
        $this->repo = new IntegrationRepository();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (
            $this->store->shopifyShop &&
            !$this->repo->validateAccessScopes($this->store->shopifyShop)
        ) {
            AccessScopeRefreshNeeded::dispatch($this->store->user, $this->repo->shopifyInstall($this->store->shopifyShop->domain));
        }
    }

    /**
     * Get the unique ID for the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return static::class . $this->store->slug;
    }

    /**
     * Get the cache key for the job.
     *
     * @return string
     */
    public function uniqueFor()
    {
        return 60; // the job will be unique for 60 seconds, adjust as necessary
    }
}
