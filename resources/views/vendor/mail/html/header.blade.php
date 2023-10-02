@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
@else
<img src="{{ config('saas.app_logo_url') }}" class="logo" alt="{{ config('app.name') }} Logo">
@endif
</a>
</td>
</tr>
