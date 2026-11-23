<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    public function __construct()
    {
        $this->middleware('throttle:5,1,password-link')->only('sendResetLinkEmail');
    }

    public function sendResetLinkEmail(Request $request): JsonResponse|RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        try {
            $this->broker()->sendResetLink($credentials);
        } catch (TransportExceptionInterface $exception) {
            report($exception);
        }

        $message = __('If an account exists for that address, a password reset link will be sent.');

        return $request->wantsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }
}
