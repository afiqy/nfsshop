<div class="mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="{{ $csvUrl }}">Download CSV</a>
    <form class="d-inline" method="GET" action="{{ $pdfUrl }}">
        @foreach ($filters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        <label for="{{ $id }}-batch">PDF batch</label>
        <input id="{{ $id }}-batch" name="batch" type="number" min="1" max="1000000" value="1" style="width: 6rem" required>
        <button class="btn btn-sm btn-outline-secondary">Download PDF</button>
    </form>
    <small>Each PDF contains up to 300 rows; wide tables and long values may reduce this. Use the next batch number for more rows.</small>
</div>
