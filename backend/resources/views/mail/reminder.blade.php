<x-mail::message>
# Your follow-up is due

Hello {{ $reminder->user->name }},

<div>{!! nl2br(e($reminder->title)) !!}</div>

<x-mail::panel>
Reference: **{{ $reminder->inquiry->reference_no }}**

Subject: {{ $reminder->inquiry->subject }}

Due: {{ $reminder->remind_at->format('Y-m-d H:i') }}
{{ config('app.timezone') }}
</x-mail::panel>

<x-mail::button :url="$inquiryUrl">
Open inquiry
</x-mail::button>

Mark the reminder complete after following up.

{{ config('app.name') }}
</x-mail::message>
