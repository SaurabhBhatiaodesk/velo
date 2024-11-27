<?php

namespace App\Traits\Integrations;

use App\Models\ShopifyShop;

trait ConnectsShopifyToUser
{
    private function connectByDomainTokenAndEmail($shopifyDomain, $shopifyToken, $email)
    {
        // get the shop by domain
        $shopifyShop = ShopifyShop::where('domain', $shopifyDomain)->first();

        // If the shopifyShop exists but not connected to a store
        if (!is_null($shopifyShop) && is_null($shopifyShop->store_slug)) {
            // make sure ShopifyShop's token matches the provided token
            $shopifyShop->token = $shopifyToken;
            // make sure ShopifyShop's email matches the user's email
            $shopifyShop->email = $email;
            // save the changes to db
            $shopifyShop->save();
        }

        return $shopifyShop;
    }
}




