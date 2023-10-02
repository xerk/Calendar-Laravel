<x-mail::message>
Dear **{{ $user->name }}**,

We are writing to inform you that your subscription to [Grandcalendar.io]({{ config('saas.app_url') }})'s {{ $package_name }} package has failed for the following reason: {{ $reason }}.

We understand that this may be an inconvenience to you and we apologize for any inconvenience caused. 

In the meantime, please do not hesitate to contact us if you have any questions or concerns. Our customer support team is available 24/7 to assist you with any issues you may encounter.

Once again, we apologize for the inconvenience and we thank you for your patience and understanding.

Best regards,

The [Grandcalendar.io]({{ config('saas.app_url') }}) Team
</x-mail::message>