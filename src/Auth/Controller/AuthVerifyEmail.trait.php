<?php

namespace Polyel\Auth\Controller;

use Polyel\Http\Request;
use Polyel\Encryption\Facade\Crypt;
use Polyel\Auth\SendsVerificationEmail;

trait AuthVerifyEmail
{
    use SendsVerificationEmail;

    public function displayEmailVerificationView()
    {
        // Don't show the email verification view if they are already verified
        if($this->user->hasVerifiedEmail())
        {
            return redirect($this->redirectTo)->withFlash('success', 'Your email has already been verified');
        }

        return response(view('auth.verification:view', [
            'message' => 'Your email has not been verified, please use the link sent to you or request a new one'
        ]));
    }

    public function verify(Request $request, $id, $hash, $expiration)
    {
        // The signed URL signature must be valid and the expiration still within the timeout...
        if($this->signatureIsValid($request) && $this->signatureHasNotExpired($expiration))
        {
            // Checks that the user is verifying against the same ID and email from the URL
            if($this->verificationIsNotForThisUser($id, $hash))
            {
                return response(view('auth.verification:view', [
                    'message' => 'Invalid verification details, please request a new verification link'
                ]));
            }

            // Redirect if the user has already got a verified email
            if($this->user->hasVerifiedEmail())
            {
                return redirect($this->redirectTo)->withFlash('success', 'Your email has already been verified');
            }

            if($this->user->markEmailAsVerified())
            {
                if($response = $this->verified($request))
                {
                    return $response;
                }

                return redirect($this->redirectTo)->withFlash('success', 'Your email has now successfully been verified');
            }
        }

        return response(view('auth.verification:view', [
            'message' => 'Invalid verification link, please request a new verification email'
        ]));
    }

    private function signatureIsValid(Request $request)
    {
        // Construct the path to build up the original URL to compare it against the signature
        $url = $request->path();

        // Recreate the original signature to compare it and check that it is valid
        $urlSignature = hash_hmac('sha256', $url, Crypt::getEncryptionKey());

        $originalSignature = $request->query('sig', '');

        return hash_equals($urlSignature, (string) $originalSignature);
    }

    private function signatureHasNotExpired($expiration)
    {
        $timeout = config('auth.verification_expiration');

        // Validate that the expiration is within the timeout limit in minutes
        return (time() - $expiration) < $timeout * 60;
    }

    private function verificationIsNotForThisUser($id, $hash)
    {
        // The ID from the URL must be the same as the current logged in user
        if(!hash_equals((string) $id, (string) $this->auth->userId()))
        {
            return true;
        }

        // The email from the URL must be the same as the current logged in user
        if(!hash_equals((string) $hash, sha1($this->auth->user()->get('email'))))
        {
            return true;
        }

        return false;
    }

    public function resendVerifyEmail(Request $request)
    {
        // Don't resend verification URLs if they are already verified
        if($this->user->hasVerifiedEmail())
        {
            return redirect($this->redirectTo)->withFlash('success', 'Your email has already been verified');
        }

        if($this->sendVerificationEmail($this->auth->user()->get('username'), $this->auth->user()->get('email')) === false)
        {
            return redirect('/email/verify')->withFlash(
                'error',
                'A verification email could not be sent, it failed to send, please try again'
            );
        }

        return redirect('/email/verify')->withFlash(
            'success',
            'A new verification email has been sent, please use the link to verify your email'
        );
    }
}