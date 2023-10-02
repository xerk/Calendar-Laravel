<?php

namespace App\Services;

use Firebase\JWT\JWT;

use Firebase\JWT\Key;
use Illuminate\Contracts\Encryption\Encrypter;

class TokenEncrypter
{
    /**
     * @var Encrypter
     */
    protected $encrypter;
    /**
     * @var string
     */
    protected $alg = 'HS512';

    public function __construct(Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    /**
     * @param array $payload
     * @return string
     * @throws \Exception
     */
    public function encode(array $payload): string
    {
        $config = config('app');

        $tokenId = base64_encode(random_bytes(16));
        $issuedAt = new \DateTimeImmutable();

        $jwtPayload = [
            'iat'  => $issuedAt->getTimestamp(),
            'jti'  => $tokenId,
            'iss'  => $config['name'],
            'nbf'  => $issuedAt->getTimestamp(),
            'exp'  => $payload['expires_at']->getTimestamp(),
            'data' => [
                'access_token' => $payload['access_token'],
                'refresh_token' => $payload['refresh_token'],
                'provider' => $payload['provider'],
                'scopes' => $payload['scopes'],
                'email' => $payload['email'],
                'provider_id' => $payload['provider_id'],
            ]
        ];

        return JWT::encode($jwtPayload, config('app.key'), $this->alg);
    }

    public function decode(string $payload): array
    {
        $config = config('app');

        $decoded = JWT::decode($payload, config('app.key'), [$this->alg]);

        return (array) $decoded->data;
    }
}
