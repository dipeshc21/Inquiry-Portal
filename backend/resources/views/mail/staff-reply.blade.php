<x-mail::message>
# An update on your inquiry

Hello {{ $inquiry->name }},

Reference: **{{ $inquiry->reference_no }}**

Subject: {{ $inquiry->subject }}

<div>{!! nl2br(e($reply->body)) !!}</div>

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
