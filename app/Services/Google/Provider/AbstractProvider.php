<?php

namespace App\Services\Google\Provider;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Google\Service\Oauth2;
use Illuminate\Http\Request;
use App\Models\ProviderAccount;
use Dnsinyukov\SyncCalendars\Token;
use Dnsinyukov\SyncCalendars\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;

abstract class AbstractProvider implements ProviderInterface
{
    protected $providerName;

    /**
     * The HTTP request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * The HTTP Client instance.
     *
     * @var $httpClient
     */
    protected $httpClient;

    /**
     * The client ID.
     *
     * @var string
     */
    protected $clientId;

    /**
     * The client secret.
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * The redirect URL.
     *
     * @var string
     */
    protected $redirectUrl;

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * The request user
     *
     * @var ProviderAccount|null
     */
    protected $user;

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * Create a new provider instance.
     *
     * @param Request $request
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUrl
     * @param array $scopes
     */
    public function __construct(Request $request, string $clientId, string $clientSecret, string $redirectUrl, array $scopes = [])
    {
        $this->request = $request;
        $this->clientId = $clientId;
        $this->redirectUrl = $redirectUrl;
        $this->clientSecret = $clientSecret;
        $this->scopes = $scopes;
    }

    /**
     * @return RedirectResponse
     * @throws \Exception
     */
    public function redirect(): RedirectResponse
    {
        $this->request->query->add(['state' => $this->getState()]);

        if ($user = $this->request->user()) {
            $this->request->query->add(['user_id' => $user->getKey()]);
        }

        return new RedirectResponse($this->createAuthUrl());
    }

    /**
     * @return ProviderAccount
     */
    public function getUser(): ProviderAccount
    {
        if (isset($this->user)) {
            return $this->user;
        }

        try {
            $credentials = $this->fetchAccessTokenWithAuthCode(
                $this->request->get('code', '')
            );

            $this->user = $this->toUser($this->getBasicProfile($credentials));
        } catch (\Exception $exception) {
            report($exception);
            throw new \InvalidArgumentException($exception->getMessage());
        }
        $state = $this->request->get('state', '');

        if (isset($state)) {
            $state = Crypt::decrypt($state);
        }

        $this->user
            ->setRedirectCallback($state['redirect_callback'])
            ->setToken($credentials['access_token'])
            ->setExpiresAt(
                Carbon::now()->addSeconds($credentials['expires_in'])
            )
            ->setScopes(
                explode($this->getScopeSeparator(), $credentials['scope'])
            );

            if (isset($credentials['refresh_token'])) {
                $this->user->setRefreshToken($credentials['refresh_token']);
            }
        return $this->user;
    }

    /**
     * Get an instance of the HTTP client.
     *
     * @return Client
     */
    protected function getHttpClient()
    {
        if (is_null($this->httpClient)) {
            $this->httpClient = new Client();
        }

        return $this->httpClient;
    }

    /**
     * @throws \Exception
     */
    protected function getState(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return string
     */
    public function getScopeSeparator(): string
    {
        return $this->scopeSeparator;
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (! method_exists($this->httpClient, $method)) {
            throw new \InvalidArgumentException("Method Not Allowed ${method}");
        }

        return call_user_func_array([$this->httpClient, $method], $args);
    }

    abstract protected function createAuthUrl();
    abstract protected function fetchAccessTokenWithAuthCode(string $code);
    abstract protected function getBasicProfile($credentials);
    abstract protected function toUser($userProfile);
}
