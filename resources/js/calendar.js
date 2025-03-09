import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    var calendar = new Calendar(calendarEl, {
        plugins: [ dayGridPlugin, interactionPlugin ],
        initialView: 'dayGridMonth',
        events: '/calendar/appointments',
        selectable: true,
        select: function(info) {
            let customer_id = document.getElementById('customer_id').value;
            let vehicle_id = document.getElementById('vehicle_id').value;
            let service_type = document.getElementById('service_type').value;

            if (!customer_id || !vehicle_id || !service_type) {
                alert("Please fill all fields before scheduling.");
                return;
            }

            fetch('/calendar/store', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    customer_id: customer_id,
                    vehicle_id: vehicle_id,
                    title: "Service Appointment",
                    service_type: service_type,
                    start: info.startStr,
                    end: info.endStr
                })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.success);
                calendar.refetchEvents();
            });
        }
    });

    calendar.render();
});
