<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait ValidatesHmac
{
    /**
     * Validate Hmac
     *
     * @return boolean
     */
    public function validateHmac($string, $hmac)
    {
        $expectedHmac = hash_hmac(
            'sha256',
            $string,
            $this->secret,
        );
        return ($hmac === $expectedHmac);
    }
}
