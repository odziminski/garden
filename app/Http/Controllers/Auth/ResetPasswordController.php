<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Rules\BcryptPassword;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords {
        sendResetFailedResponse as private originalSendResetFailedResponse;
    }

    public function __construct()
    {
        $this->middleware('throttle:5,1,password-reset')->only('reset');
    }

    protected function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed', new BcryptPassword()],
        ];
    }

    protected function sendResetFailedResponse(Request $request, $response)
    {
        return $this->originalSendResetFailedResponse($request, Password::INVALID_TOKEN);
    }

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;
}
