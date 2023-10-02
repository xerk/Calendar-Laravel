<x-mail::message>
# Hello {{ $notifiable->name }},

{{ $message }}

@if($url)
<x-mail::button :url="$url">
Button Text
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
