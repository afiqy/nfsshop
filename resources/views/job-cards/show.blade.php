@extends('layouts.app')

@section('title', __('Job card'))

@section('content')
    <a href="{{ route($staffView ? 'my-jobs.index' : 'job-cards.index') }}">{{ $staffView ? __('Back to my jobs') : __('Back to job cards') }}</a>
    <h1 class="h3 mt-2 mb-3">{{ __('Job #:id', ['id' => $job->id]) }}</h1>
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
    @error('vehicle_update')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('mileage_km')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('vehicle_condition')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('quantity')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('inventory_item_id')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('job')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror
    <div class="card shadow-sm p-3">
        <dl>
            <dt>{{ __('Status') }}</dt>
            <dd>{{ __(\App\Models\JobCard::LABELS[$job->status]) }}</dd>
            @unless ($staffView)
                <dt>{{ __('Assigned mechanic') }}</dt>
                <dd>{{ $job->assigned_mechanic_id ? ($assigneeName ?? __('Unavailable')) : __('Unassigned') }}</dd>
            @endunless
            <dt>{{ __('Customer') }}</dt>
            <dd>{{ $job->customer?->name ?? __('Unavailable') }}</dd>
            <dt>{{ __('Vehicle') }}</dt>
            <dd>{{ $job->vehicle?->plate_number ?? __('Unavailable') }}</dd>
            @if ($job->appointment)
                <dt>{{ __('Appointment') }}</dt>
                <dd>
                    @if ($staffView)
                        {{ substr($job->appointment->start, 0, 16) }} MYT
                    @else
                        <a href="{{ route('calendar.index', ['week' => substr($job->appointment->start, 0, 10)]) }}">{{ substr($job->appointment->start, 0, 16) }} MYT</a>
                    @endif
                    ({{ __($job->appointment->status) }})
                </dd>
            @endif
            @if ($job->completed_at)
                <dt>{{ __('Completed at') }}</dt>
                <dd>{{ $job->completed_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }} MYT</dd>
            @endif
            <dt>{{ __('Target completion date') }}</dt>
            <dd>
                {{ $job->due_date?->locale(app()->getLocale())->translatedFormat('d M Y') ?? __('Not set') }}
                @if ($job->isOverdue($today))
                    <span class="badge badge-danger">{{ __('Overdue') }}</span>
                @endif
            </dd>
            <dt>{{ __('Work requested') }}</dt>
            <dd>{!! nl2br(e($job->description)) !!}</dd>
            @if ($job->appointment?->service_quote)
                <dt>{{ __('Requested services') }}</dt>
                <dd>{{ $job->appointment->service_quote['name'] }}: {{ collect($job->appointment->service_quote['services'])->pluck('name')->join(', ') }}
                    @if ($job->appointment->service_quote['notes'])<br>{{ $job->appointment->service_quote['notes'] }}@endif
                </dd>
            @endif
        </dl>
        @if (! $staffView && $job->nextStatus())
            <form method="POST" action="{{ route('job-cards.assign', $job) }}" class="mb-3">
                @csrf
                @method('PATCH')
                <label for="assigned_mechanic_id">{{ __('Change assignment') }}</label>
                <select class="form-control mb-2" id="assigned_mechanic_id" name="assigned_mechanic_id">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($mechanics as $mechanic)
                        <option value="{{ $mechanic->id }}" @selected($job->assigned_mechanic_id === $mechanic->id)>{{ $mechanic->name }} (#{{ $mechanic->id }})</option>
                    @endforeach
                </select>
                @if ($mechanics->isEmpty())
                    <p class="text-muted">{{ __('No active mechanics are available in this workshop.') }}</p>
                @endif
                <button type="submit" class="btn btn-outline-primary">{{ __('Save assignment') }}</button>
            </form>
        @endif
        @if ($nextStatus = $job->nextStatus())
            <form method="POST" action="{{ route($staffView ? 'my-jobs.advance' : 'job-cards.advance', $job) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $nextStatus }}">
                <button type="submit" class="btn btn-primary">{{ $nextStatus === \App\Models\JobCard::COMPLETED ? __('Mark completed') : __('Start work') }}</button>
            </form>
        @endif
        @if ($staffView && $job->status === \App\Models\JobCard::COMPLETED && auth()->user()->can_create_job_invoices)
            <p class="mt-3 mb-0"><a href="{{ route('staff-invoices.create', $job) }}">{{ __('Create invoice for this completed job') }}</a></p>
        @endif
    </div>

    <section class="card shadow-sm p-3 mt-3" aria-labelledby="job-updates">
        <h2 class="h5" id="job-updates">{{ __('Progress and service notes') }}</h2>
        @if ($job->updates->isEmpty())
            <p>{{ __('No progress history recorded for this job yet.') }}</p>
        @else
            <ol class="list-group list-group-flush mb-3">
                @foreach ($job->updates as $update)
                    <li class="list-group-item">
                        <strong>
                            @if ($update->type === \App\Models\JobCardUpdate::TYPE_OPENED)
                                {{ __('Job opened') }}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_STATUS)
                                {{ __('Status changed to :status', ['status' => __(\App\Models\JobCard::LABELS[$update->status] ?? 'Updated')]) }}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_PART_USED)
                                {{ __('Part used') }}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_PART_RETURNED)
                                {{ __('Unused part returned') }}
                            @elseif ($update->type === \App\Models\JobCardUpdate::TYPE_VEHICLE_UPDATE)
                                {{ __('Vehicle update') }}
                            @else
                                {{ __('Service note') }}
                            @endif
                        </strong>
                        <span class="d-block text-muted">
                            {{ $update->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }} MYT
                            &middot; {{ $update->actor_name }}
                        </span>
                        @if ($update->type === \App\Models\JobCardUpdate::TYPE_VEHICLE_UPDATE)
                            @if ($update->mileage_km !== null)
                                <span class="d-block">{{ __('Mileage:') }} {{ number_format($update->mileage_km) }} km</span>
                            @endif
                            @if ($update->vehicle_condition)
                                <div>{{ __('Condition:') }} {!! nl2br(e($update->vehicle_condition)) !!}</div>
                            @endif
                            @if ($update->note)
                                <div>{!! nl2br(e($update->note)) !!}</div>
                            @endif
                        @elseif (in_array($update->type, [
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
                <label for="note">{{ __('Add a service note') }}</label>
                <textarea class="form-control" id="note" name="note" rows="4" maxlength="4000" required>{{ is_string(old('note')) ? old('note') : '' }}</textarea>
                <small class="form-text text-muted">{{ __('Notes are permanent and visible to workshop admins and mechanics assigned to this job. Do not include passwords or payment-card details.') }}</small>
            </div>
            <button type="submit" class="btn btn-outline-primary">{{ __('Add note') }}</button>
        </form>
    </section>

    @if ($staffView && $job->nextStatus())
        <section class="card shadow-sm p-3 mt-3" aria-labelledby="vehicle-update">
            <h2 class="h5" id="vehicle-update">{{ __('Vehicle update') }}</h2>
            <p>{{ __('Record the current mileage, condition, or a service note. Updates are permanent and appear in vehicle service history.') }}</p>
            <form method="POST" action="{{ route('my-jobs.vehicle-updates.store', $job) }}">
                @csrf
                <div class="form-group">
                    <label for="mileage_km">{{ __('Odometer (km, optional)') }}</label>
                    <input class="form-control" id="mileage_km" name="mileage_km" type="number" min="0"
                        max="4294967295" value="{{ old('mileage_km') }}">
                </div>
                <div class="form-group">
                    <label for="vehicle_condition">{{ __('Vehicle condition (optional)') }}</label>
                    <textarea class="form-control" id="vehicle_condition" name="vehicle_condition" rows="3"
                        maxlength="1000">{{ old('vehicle_condition') }}</textarea>
                </div>
                <div class="form-group">
                    <label for="vehicle-update-note">{{ __('Service note (optional)') }}</label>
                    <textarea class="form-control" id="vehicle-update-note" name="note" rows="3"
                        maxlength="4000">{{ old('note') }}</textarea>
                </div>
                <button class="btn btn-outline-primary" type="submit">{{ __('Add vehicle update') }}</button>
            </form>
        </section>
    @endif

    @if ($canManageInventory)
        <section class="card shadow-sm p-3 mt-3" aria-labelledby="job-parts">
            <h2 class="h5" id="job-parts">{{ __('Parts used') }}</h2>
            @if ($job->parts->isEmpty())
                <p>{{ __('No inventory parts recorded for this job.') }}</p>
            @else
                <ul class="list-group mb-3">
                    @foreach ($job->parts as $part)
                        @php
                            $returned = $part->returns->sum('quantity');
                            $remaining = $part->quantity - $returned;
                        @endphp
                        <li class="list-group-item">
                            {{ $part->item_sku }} &middot; {{ $part->item_name }}:
                            {{ __(':quantity used, :returned returned, :remaining net used', ['quantity' => $part->quantity, 'returned' => $returned, 'remaining' => $remaining]) }}
                            <span class="d-block text-muted">
                                {{ __('Recorded :date MYT', ['date' => $part->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i')]) }}
                                &middot; {{ __('User #:id', ['id' => $part->recorded_by_user_id]) }}
                            </span>
                            @if ($remaining > 0 && $job->nextStatus())
                                <form class="form-inline mt-2" method="POST"
                                    action="{{ route($staffView ? 'my-jobs.parts.return' : 'job-cards.parts.return', [$job, $part]) }}">
                                    @csrf
                                    <label class="sr-only" for="return-quantity-{{ $part->id }}">{{ __('Quantity to return') }}</label>
                                    <input class="form-control mr-2" id="return-quantity-{{ $part->id }}" name="quantity"
                                        type="number" min="1" max="{{ $remaining }}" value="1" required>
                                    <button class="btn btn-outline-secondary" type="submit">{{ __('Return unused stock') }}</button>
                                </form>
                            @endif
                            @foreach ($part->returns as $return)
                                <span class="d-block text-muted">
                                    {{ __('Returned :quantity on :date MYT', ['quantity' => $return->quantity, 'date' => $return->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i')]) }}
                                    &middot; {{ __('User #:id', ['id' => $return->recorded_by_user_id]) }}
                                </span>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($job->nextStatus())
                @if ($inventoryItems->isEmpty())
                    <p>{{ __('No in-stock items are available. Add stock through Inventory before recording parts used.') }}</p>
                @else
                    <form method="POST" action="{{ route($staffView ? 'my-jobs.parts.store' : 'job-cards.parts.store', $job) }}">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="inventory_item_id">{{ __('Inventory item') }}</label>
                                <select class="form-control" id="inventory_item_id" name="inventory_item_id" required>
                                    <option value="">{{ __('Select an in-stock item') }}</option>
                                    @foreach ($inventoryItems as $item)
                                        <option value="{{ $item->id }}" @selected((string) old('inventory_item_id') === (string) $item->id)>
                                            {{ $item->sku }} &middot; {{ $item->name }} ({{ $item->quantity }} on hand)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="part-quantity">{{ __('Quantity used') }}</label>
                                <input class="form-control" id="part-quantity" name="quantity" type="number" min="1"
                                    max="{{ \App\Models\InventoryItem::MAX_QUANTITY }}"
                                    value="{{ is_scalar(old('quantity')) ? old('quantity') : '' }}" required>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit">{{ __('Record parts used and deduct stock') }}</button>
                    </form>
                @endif
            @endif
        </section>
    @endif
@endsection
