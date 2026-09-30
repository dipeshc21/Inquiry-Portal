<x-mail::message>
# Thank you for contacting us

Hello {{ $inquiry->name }},

We have received your inquiry. A member of our team will contact you shortly.

<x-mail::panel>
Reference: **{{ $inquiry->reference_no }}**

Subject: {{ $inquiry->subject }}
</x-mail::panel>

Please keep this reference number for future correspondence.

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>
