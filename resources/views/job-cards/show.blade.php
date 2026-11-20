@extends('layouts.app')

@section('title', 'Job card')

@section('content')
    <a href="{{ route($staffView ? 'my-jobs.index' : 'job-cards.index') }}">{{ $staffView ? 'Back to my jobs' : 'Back to job cards' }}</a>
    <h1 class="h3 mt-2 mb-3">Job #{{ $job->id }}</h1>
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('status')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('assigned_mechanic_id')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('note')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('quantity')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('inventory_item_id')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    <div class="card shadow-sm p-3">
        <dl>
            <dt>Status</dt>
            <dd>{{ \App\Models\JobCard::LABELS[$job->status] }}</dd>
            @unless ($staffView)
                <dt>Assigned mechanic</dt>
                <dd>{{ $job->assigned_mechanic_id ? ($assigneeName ?? 'Unavailable') : 'Unassigned' }}</dd>
            @endunless
            <dt>Customer</dt>
            <dd>{{ $job->customer?->name ?? 'Unavailable' }}</dd>
            <dt>Vehicle</dt>
            <dd>{{ $job->vehicle?->plate_number ?? 'Unavailable' }}</dd>
            @if ($job->appointment)
                <dt>Appointment</dt>
                <dd>
                    @if ($staffView)
                        {{ substr($job->appointment->start, 0, 16) }} MYT
                    @else
                        <a href="{{ route('calendar.index', ['week' => substr($job->appointment->start, 0, 10)]) }}">{{ substr($job->appointment->start, 0, 16) }} MYT</a>
                    @endif
                    ({{ $job->appointment->status }})
                </dd>
            @endif
            @if ($job->completed_at)
                <dt>Completed at</dt>
                <dd>{{ $job->completed_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT</dd>
            @endif
            <dt>Target completion date</dt>
            <dd>
                {{ $job->due_date?->format('d M Y') ?? 'Not set' }}
                @if ($job->isOverdue($today))
                    <span class="badge badge-danger">Overdue</span>
                @endif
            </dd>
            <dt>Work requested</dt>
            <dd>{!! nl2br(e($job->description)) !!}</dd>
        </dl>
        @if (! $staffView && $job->nextStatus())
            <form method="POST" action="{{ route('job-cards.assign', $job) }}" class="mb-3">
                @csrf
                @method('PATCH')
                <label for="assigned_mechanic_id">Change assignment</label>
                <select class="form-control mb-2" id="assigned_mechanic_id" name="assigned_mechanic_id">
                    <option value="">Unassigned</option>
                    @foreach ($mechanics as $mechanic)
                        <option value="{{ $mechanic->id }}" @selected($job->assigned_mechanic_id === $mechanic->id)>{{ $mechanic->name }} (#{{ $mechanic->id }})</option>
                    @endforeach
                </select>
                @if ($mechanics->isEmpty())
                    <p class="text-muted">No active mechanics are available in this workshop.</p>
                @endif
                <button type="submit" class="btn btn-outline-primary">Save assignment</button>
            </form>
        @endif
        @if ($nextStatus = $job->nextStatus())
            <form method="POST" action="{{ route($staffView ? 'my-jobs.advance' : 'job-cards.advance', $job) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $nextStatus }}">
                <button type="submit" class="btn btn-primary">{{ $nextStatus === \App\Models\JobCard::COMPLETED ? 'Mark completed' : 'Start work' }}</button>
            </form>
        @endif
    </div>

    <section class="card shadow-sm p-3 mt-3" aria-labelledby="job-updates">
        <h2 class="h5" id="job-updates">Progress and service notes</h2>
        @if ($job->updates->isEmpty())
            <p>No progress history recorded for this job yet.</p>
        @else
            <ol class="list-group list-group-flush mb-3">
                @foreach ($job->updates as $update)
                    <li class="list-group-item">
                        <strong>
                            @if ($update->type === \App\Models\JobCardUpdate::TYPE_OPENED)
                                Job opened
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_STATUS)
                                Status changed to {{ \App\Models\JobCard::LABELS[$update->status] ?? 'Updated' }}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_PART_USED)
                                Part used
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_PART_RETURNED)
                                Unused part returned
                            @else
                                Service note
                            @endif
                        </strong>
                        <span class="d-block text-muted">
                            {{ $update->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT
                            &middot; {{ $update->actor_name }}
                        </span>
                        @if (in_array($update->type, [
                            \App\Models\JobCardUpdate::TYPE_NOTE,
                            \App\Models\JobCardUpdate::TYPE_PART_USED,
                            \App\Models\JobCardUpdate::TYPE_PART_RETURNED,
                        ], true))
                            <div>{!! nl2br(e($update->note)) !!}</div>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        <form method="POST" action="{{ route($staffView ? 'my-jobs.notes.store' : 'job-cards.notes.store', $job) }}">
            @csrf
            <div class="form-group">
                <label for="note">Add a service note</label>
                <textarea class="form-control" id="note" name="note" rows="4" maxlength="4000" required>{{ is_string(old('note')) ? old('note') : '' }}</textarea>
                <small class="form-text text-muted">Notes are permanent and visible to workshop admins and mechanics assigned to this job. Do not include passwords or payment-card details.</small>
            </div>
            <button type="submit" class="btn btn-outline-primary">Add note</button>
        </form>
    </section>

    @if ($canManageInventory)
        <section class="card shadow-sm p-3 mt-3" aria-labelledby="job-parts">
            <h2 class="h5" id="job-parts">Parts used</h2>
            @if ($job->parts->isEmpty())
                <p>No inventory parts recorded for this job.</p>
            @else
                <ul class="list-group mb-3">
                    @foreach ($job->parts as $part)
                        @php
                            $returned = $part->returns->sum('quantity');
                            $remaining = $part->quantity - $returned;
                        @endphp
                        <li class="list-group-item">
                            {{ $part->item_sku }} &middot; {{ $part->item_name }}:
                            {{ $part->quantity }} used,
                            {{ $returned }} returned,
                            {{ $remaining }} net used
                            <span class="d-block text-muted">
                                Recorded {{ $part->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT
                                &middot; User #{{ $part->recorded_by_user_id }}
                            </span>
                            @if ($remaining > 0 && $job->nextStatus())
                                <form class="form-inline mt-2" method="POST"
                                    action="{{ route($staffView ? 'my-jobs.parts.return' : 'job-cards.parts.return', [$job, $part]) }}">
                                    @csrf
                                    <label class="sr-only" for="return-quantity-{{ $part->id }}">Quantity to return</label>
                                    <input class="form-control mr-2" id="return-quantity-{{ $part->id }}" name="quantity"
                                        type="number" min="1" max="{{ $remaining }}" value="1" required>
                                    <button class="btn btn-outline-secondary" type="submit">Return unused stock</button>
                                </form>
                            @endif
                            @foreach ($part->returns as $return)
                                <span class="d-block text-muted">
                                    Returned {{ $return->quantity }} on {{ $return->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT
                                    &middot; User #{{ $return->recorded_by_user_id }}
                                </span>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($job->nextStatus())
                @if ($inventoryItems->isEmpty())
                    <p>No in-stock items are available. Add stock through Inventory before recording parts used.</p>
                @else
                    <form method="POST" action="{{ route($staffView ? 'my-jobs.parts.store' : 'job-cards.parts.store', $job) }}">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="inventory_item_id">Inventory item</label>
                                <select class="form-control" id="inventory_item_id" name="inventory_item_id" required>
                                    <option value="">Select an in-stock item</option>
                                    @foreach ($inventoryItems as $item)
                                        <option value="{{ $item->id }}" @selected((string) old('inventory_item_id') === (string) $item->id)>
                                            {{ $item->sku }} &middot; {{ $item->name }} ({{ $item->quantity }} on hand)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="part-quantity">Quantity used</label>
                                <input class="form-control" id="part-quantity" name="quantity" type="number" min="1"
                                    max="{{ \App\Models\InventoryItem::MAX_QUANTITY }}"
                                    value="{{ is_scalar(old('quantity')) ? old('quantity') : '' }}" required>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit">Record parts used and deduct stock</button>
                    </form>
                @endif
            @endif
        </section>
    @endif
@endsection
