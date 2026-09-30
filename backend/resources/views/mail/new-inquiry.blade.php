<x-mail::message>
# A new inquiry has arrived

<x-mail::panel>
Reference: **{{ $inquiry->reference_no }}**

Name: {{ $inquiry->name }}

Email: {{ $inquiry->email }}

Company: {{ $inquiry->company ?: 'Not provided' }}

Source: {{ str_replace('_', ' ', $inquiry->source) }}

Priority: {{ ucfirst($inquiry->priority) }}
</x-mail::panel>

## {{ $inquiry->subject }}

<div>{!! nl2br(e($inquiry->message)) !!}</div>

<x-mail::button :url="$inquiryUrl">
View inquiry
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
