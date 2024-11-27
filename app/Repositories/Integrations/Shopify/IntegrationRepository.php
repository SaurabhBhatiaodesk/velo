<?php

namespace App\Repositories\Integrations\Shopify;

use App\Repositories\BaseRepository;
use App\Repositories\AddressesRepository;
use App\Repositories\Integrations\Shopify\Traits\GqlApiTrait;
use App\Repositories\Integrations\Shopify\Traits\RestApiTrait;
use App\Repositories\Integrations\Shopify\Traits\InventoryTrait;
use App\Repositories\Integrations\Shopify\Traits\InstallationTrait;
use App\Repositories\Integrations\Shopify\Traits\CustomersTrait;
use App\Repositories\Integrations\Shopify\Traits\OrdersTrait;
use App\Repositories\Integrations\Shopify\Traits\ProductsTrait;
use App\Repositories\Integrations\Shopify\Traits\FulfilmentTrait;
use Illuminate\Support\Facades\Log;

class IntegrationRepository extends BaseRepository
{
    use GqlApiTrait;
    use RestApiTrait;
    use InventoryTrait;
    use InstallationTrait;
    use CustomersTrait;
    use OrdersTrait;
    use ProductsTrait;
    use FulfilmentTrait;

    private $shopifyDomain;
    private $addressesRepo;

    public function __construct()
    {
        $this->addressesRepo = new AddressesRepository();
    }
}
