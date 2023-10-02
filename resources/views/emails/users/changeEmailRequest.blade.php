<x-mail::message>
# Hello {{ $userName }},

Your OTP is: **{{ $otp }}**

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
