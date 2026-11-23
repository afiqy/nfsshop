<p>Workshop: {{ $workshopName }}</p>
<p>Appointment update: {{ str_replace('_', ' ', ucfirst($notification->event)) }}.</p>
<p>Service: {{ $notification->service }}<br>
Vehicle: {{ $notification->vehicle_plate }}<br>
Time: {{ substr($notification->start, 0, 16) }}@if ($notification->end) to {{ substr($notification->end, 0, 16) }}@endif MYT</p>
<p>Update recorded {{ $notification->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT. Later changes may apply; check your account or contact the workshop for current details.</p>
