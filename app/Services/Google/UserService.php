<?php

namespace App\Services\Google;

use App\Models\ProviderAccount;
use App\Services\TokenEncrypter;
use Illuminate\Support\Facades\DB;

class UserService
{
    protected $encrypter;

    /**
     * @param TokenEncrypter $encrypter
     */
    public function __construct(TokenEncrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    public function saveFromUser(ProviderAccount $user, string $provider)
    {
        $payload = [
            'provider_id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'picture' => $user->getPicture(),
            'provider' => $provider,
            'access_token' => $user->getAccessToken(),
            'refresh_token' => $user->getRefreshToken(),
            'scopes' => implode(' ', $user->getScopes()),
            'expires_at' => $user->getExpiresAt(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        // unset($payload['access_token'], $payload['refresh_token'], $payload['scopes']);

        if (DB::table('provider_accounts')
            ->where('provider_id', $payload['provider_id'])
            ->where('provider', $provider)
            ->exists()
        ) {
            unset($payload['created_at']);

            DB::table('provider_accounts')
                ->where('provider_id', $payload['provider_id'])
                ->where('provider', $provider)
                ->update($payload);
        } else {
            DB::table('provider_accounts')->insert($payload);
        }
    }

    public function getFromUser(ProviderAccount $account, string $provider,): ?ProviderAccount
    {
        if (!$account) {
            return null;
        }
        $account->token = $this->encrypter->decode($account->token);

        return new ProviderAccount((array) $account);
    }
}
