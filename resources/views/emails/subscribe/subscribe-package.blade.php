<x-mail::message>
Dear **{{ $user->name }}**,

Congratulations and welcome to [Grandcalendar.io]({{ config('saas.app_url') }})! We are thrilled to have you onboard as a subscriber to our **{{ $package_name }}** package **{{ $package_period }}**.



Our **{{ $package_name }}** package offers you access to a range of  features and functionalities  that will help you streamline your daily scheduling and task management needs. 

{!! $package_description !!}


To get started, simply log in to your [Grandcalendar.io]({{ config('saas.app_url') }}) account and start exploring all the great features and tools available to you. We are here to support you every step of the way, so please do not hesitate to reach out to us with any questions or concerns.



Thank you for choosing [Grandcalendar.io]({{ config('saas.app_url') }}), and we look forward to helping you make the most out of your subscription.



Best regards,

The [Grandcalendar.io]({{ config('saas.app_url') }}) Team

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>