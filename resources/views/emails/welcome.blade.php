@component('mail::message')
Welcome to {{ config('app.name') }}

The body of your message.

@component('mail::button', ['url' => '{{ config(app.url) }}'])
Button Text
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
