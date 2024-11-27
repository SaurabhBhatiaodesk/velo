<?php

namespace App\Http\Controllers\SupportSystem\Velo;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Mail\Users\PasswordReset;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function forgot()
    {
        $user = SupportSystemController::getUser();
        $token = Str::random(30);
        $user->update([
            'token' => $token,
            'token_created_at' => Carbon::now()
        ]);
        if (!Mail::to($user->email)->send(new PasswordReset($user, $token))) {
            return SupportSystemController::error(401, "", "Unable to mail user");
        }
        return SupportSystemController::response(["success" => 1]);
    }
}
