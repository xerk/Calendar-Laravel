<x-mail::message>
# Hello {{ $userName }},

Thank you for signing up for an account on {{ config('app.name') }}. To ensure the security of your account, we have generated a one-time password (OTP) that you can use to verify your identity and complete the sign-up process.

Your OTP is: **{{ $otp }}**

Please enter this code on the sign-up screen when prompted.

If you did not attempt to sign up for an account on our {{ config('app.name') }}, please disregard this email.

<x-mail::button color="brand" :url="config('saas.app_url')">
Sign In
</x-mail::button>

Best regards,,<br>
{{ config('app.name') }}
</x-mail::message>
