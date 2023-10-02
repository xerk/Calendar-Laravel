<?php

namespace App\Services\Google\Provider;

use Google\Client;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\ProviderAccount;
use Google\Service\Oauth2\Userinfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;

class GoogleProvider extends AbstractProvider
{
    protected $providerName = 'google';

    public function createAuthUrl(): string
    {
        return $this->getHttpClient()->createAuthUrl();
    }

    public function redirect(): RedirectResponse
    {
        if ($redirectCallback = config('services.google.redirect_callback')) {
            $this->request->query->add(['redirect_callback' => $redirectCallback]);
        }

        return parent::redirect();
    }

    protected function fetchAccessTokenWithAuthCode(string $code): array
    {
        return $this->getHttpClient()->fetchAccessTokenWithAuthCode($code);
    }

    /**
     * @return array
     */
    protected function getBasicProfile($credentials)
    {
        $jwt = explode('.', $credentials['id_token']);

        // Extract the middle part, base64 decode it, then json_decode it
        return json_decode(base64_decode($jwt[1]), true);
    }

    /**
     * @param Userinfo $userProfile
     * @return void
     */
    protected function toUser($userProfile)
    {
        return tap(new ProviderAccount(), function ($user) use ($userProfile) {
            $user->setId($userProfile['sub']);
            $user->setName($userProfile['name']);
            $user->setEmail($userProfile['email']);
            $user->setPicture($userProfile['picture']);
        });
    }

    /**
     * @return Client
     */
    protected function getHttpClient(): Client
    {
        if (is_null($this->httpClient)) {

            $this->httpClient = new \Google\Client();
            $this->httpClient->setApplicationName(config('app.name'));
            $this->httpClient->setClientId($this->clientId);
            $this->httpClient->setClientSecret($this->clientSecret);
            $this->httpClient->setRedirectUri($this->redirectUrl);
            $this->httpClient->setScopes($this->scopes);
            $this->httpClient->setApprovalPrompt(config('services.google.approval_prompt'));
            $this->httpClient->setAccessType(config('services.google.access_type', 'offline'));
            $this->httpClient->setIncludeGrantedScopes(config('services.google.include_granted_scopes'));

            // Add request query to the state
            $this->httpClient->setState(
                Crypt::encrypt($this->request->all())
            );
        }

        return $this->httpClient;
    }
}
