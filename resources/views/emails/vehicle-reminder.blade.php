<p>Hello {{ $customer->name }},</p>
<p>{{ $workshopName }} has a {{ $reminder->type }} reminder for {{ $vehicle->plate_number }} due {{ $reminder->due_on->format('d M Y') }}.</p>
@if ($reminder->note)
    <p>{{ $reminder->note }}</p>
@endif
<p>Contact the workshop if you have questions or wish to change your reminder preference.</p>
