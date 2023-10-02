<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\ProviderAccount;
use App\Services\Google\UserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use App\Services\Google\CalendarManager;
use App\Services\Google\Provider\ProviderInterface;

class AccountController extends Controller
{
    protected $manager;

    /**
     * @param CalendarManager $manager
     */
    public function __construct(CalendarManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @param string $driver
     * @return RedirectResponse
     */
    public function auth(string $driver): RedirectResponse
    {
        try {

            if ($driver === 'zoom') {
                $clientId = config('zoom.client_id');
                $redirectUri = config('zoom.redirect_uri');
                $url = "https://zoom.us/oauth/authorize?response_type=code&client_id={$clientId}&redirect_uri={$redirectUri}";
                return redirect()->to($url);
            }

            return $this->manager->driver($driver)->redirect();
        } catch (\InvalidArgumentException $exception) {
            report($exception);

            abort(400, $exception->getMessage());
        }
    }

    /**
     * @param string $driver
     * @return RedirectResponse
     */
    public function callback(string $driver)
    {

        if ($driver === 'zoom') {
            return $this->getZoomUser(request()->code);
        }

        /** @var User $user */
        $user = $this->manager->driver($driver)->getUser();
        app(UserService::class)->saveFromUser($user, $driver);

        return redirect()->to($user->getRedirectCallback() ?? '/');
    }

    private function getZoomUser($code) {
        $clientId = config('zoom.client_id');
        $clientSecret = config('zoom.client_secret');
        $redirectUri = config('zoom.redirect_uri');

        $code_verifier = bin2hex(random_bytes(32)); // Generate a random 64-character string
        $code_challenge = $this->base64_urlencode(hash('sha256', $code_verifier, true));
        // dd($code_challenge, $code_verifier);

        // POST https://zoom.us/oauth/token
        // HTTP/1.1

        // # Header
        // Host: zoom.us
        // Authorization: Basic Q2xpZW50X0lEOkNsaWVudF9TZWNyZXQ=
        // Content-Type: application/x-www-form-urlencoded

        // # Request body
        // code: [CODE]
        // grant_type: authorization_code
        // redirect_uri: [REDIRECT URI]
        // code_verifier: [CODE VERIFIER]

        $code_verifier =


        $client =  Http::withBasicAuth($clientId, $clientSecret)
        ->asForm()->post("https://zoom.us/oauth/token", [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        $data = $client->json();

        $user = $this->zoomUser($data);

        $account = new ProviderAccount();

        $account->setId($user['id'])
            ->setName($user['display_name'])
            ->setEmail($user['email'])
        ->setRedirectCallback(config('zoom.redirect_uri_callback'))
            ->setToken($data['access_token'])
            ->setExpiresAt(
                Carbon::now()->addSeconds($data['expires_in'])
            )
            ->setScopes(
                explode(' ', $data['scope'])
            );

            if ($data['refresh_token']) {
                $account->setRefreshToken($data['refresh_token']);
            }

        app(UserService::class)->saveFromUser($account, 'zoom');

        return redirect()->to($account->getRedirectCallback() ?? '/');
    }

    public function zoomUser($data) {
        $user = Http::withToken($data['access_token'])->get("https://api.zoom.us/v2/users/me");

        return $user->json();
    }

    private function base64_urlencode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Connect user to provider id
     * @param string $driver, string $provider_id
     * @return RedirectResponse
     */
    public function connect(Request $request, string $driver, string $provider_id) {
        $user = auth()->user();
        if ($driver == 'zoom') {

            $provider = ProviderAccount::where([
                'user_id' => $user->id,
                'provider' => 'zoom'
            ])->first();
                        $provider_id = $request->provider_id;
            if ($provider) {
                // Update the existing provider account
                $provider->provider_id = $provider_id;
                $provider->save();
            } else {
                // Create a new provider account if it doesn't exist
                $provider = new ProviderAccount();
                $provider->provider_id = $provider_id;
                $provider->provider = $driver;
                $provider->name = $driver;
                $provider->email = $driver;
                $provider->picture = $driver;
                $provider->meeting_type = 'zoom';
                $provider->user_id = $user->id;
                $provider->save();
            }
            
            // Optionally, you can associate the provider with the user if needed
            // Return a response or redirect as needed
            // For example, you can return a success message or redirect the user to a different page
            return response()->json(['message' => 'Provider connected successfully']);
        }
        $provider = ProviderAccount::where('provider_id', $provider_id)->first();

        if ($driver == 'zoom') {
            $provider->meeting_type = 'zoom';
        }

        if ($request->google_meet) {

            if ($provider->meeting_type == 'google_meet') {
                return response()->json([
                    'message' => 'You are already connected to this account',
                    'status' => 400,
                    'code' => 'already_connected'
                ], 400);
            }

            $provider->meeting_type = 'google_meet';
            $provider->save();

            return response()->json([
                'message' => 'Google Meet connected successfully',
                'status' => 200,
                'code' => 'connected'
            ], 200);
        }

        if ($user->id == $provider->user_id) {
            return response()->json([
                'message' => 'You are already connected to this account',
                'status' => 400,
                'code' => 'already_connected'
            ], 400);
        }

        if ($provider->user_id != null) {
            return response()->json([
                'message' => 'This account is already connected to another user',
                'status' => 400,
                'code' => 'already_connected'
            ], 400);
        }

        $provider->user_id = $user->id;
        $provider->save();

        return response()->json([
            'message' => 'Account connected successfully',
            'status' => 200,
            'code' => 'connected'
        ], 200);
    }

    /**
     * Disconnect user from provider id
     * @param string $driver, string $provider_id
     * @return RedirectResponse
     */
    public function disconnect(Request $request, string $driver, string $id) {
        $user = auth()->user();

        $provider = ProviderAccount::find($id);
        if ($user->id != $provider->user_id) {
            return response()->json([
                'message' => 'You are not connected to this account',
                'status' => 400,
                'code' => 'not_connected'
            ], 400);
        }

        if ($request->google_meet) {
            $provider->meeting_type = null;
            $provider->save();

            return response()->json([
                'message' => 'Google Meet disconnected successfully',
                'status' => 200,
                'code' => 'disconnected'
            ], 200);
        }

        if($provider->provider == 'zoom') {
            $provider->delete();
            return response()->json([
                'message' => 'Account disconnected successfully',
                'status' => 200,
                'code' => 'disconnected'
            ], 200);
        }

        $provider->user_id = null;
        $provider->meeting_type = null;
        $provider->save();

        return response()->json([
            'message' => 'Account disconnected successfully',
            'status' => 200,
            'code' => 'disconnected'
        ], 200);
    }
}
