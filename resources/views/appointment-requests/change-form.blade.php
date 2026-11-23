<details class="mt-2">
    <summary>Change or cancel</summary>
    <form method="POST" action="{{ $updateUrl }}" class="mt-2">
        @csrf @method('PUT')
        <label for="{{ $id }}-start">New start (MYT)</label>
        <input id="{{ $id }}-start" name="start" type="datetime-local" value="{{ str_replace(' ', 'T', substr($start, 0, 16)) }}" required>
        <label for="{{ $id }}-end">New end (MYT)</label>
        <input id="{{ $id }}-end" name="end" type="datetime-local" value="{{ $end ? str_replace(' ', 'T', substr($end, 0, 16)) : '' }}" required>
        <button class="btn btn-sm btn-primary">Reschedule</button>
    </form>
    <form method="POST" action="{{ $cancelUrl }}" class="mt-2">
        @csrf @method('PATCH')
        <button class="btn btn-sm btn-outline-danger">Cancel</button>
    </form>
</details>
