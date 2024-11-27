<?php

namespace App\Http\Controllers\SupportSystem\Velo;

use App\Events\Models\User\LocaleSelected;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Models\SupportSystem;
use App\Models\User;
use Illuminate\Http\Request;

class SetUILocaleController extends Controller
{
    public function setUILocale(Request $request)
    {
        $user = SupportSystemController::getUser();

        $locale_iso = $request->segment(3);
        switch ($locale_iso) {
            case "preferred_language_eng":
                $local_id = 1;
                break;
            case "preferred_language_heb":
                $local_id = 2;
                break;
            default:
                $local_id = false;
        }
        if (!$local_id) {
            return SupportSystemController::error(403, 'Invalid locale');

        }

        if (!$user->update(['locale_id' => $local_id])) {
            return SupportSystemController::error(403, 'Invalid locale');
        }

        LocaleSelected::dispatch($user);
        return SupportSystemController::response(['success' => 1]);
    }
}
