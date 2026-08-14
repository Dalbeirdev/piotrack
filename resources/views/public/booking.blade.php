@extends('public.layout')

@section('title', $page->name)

@section('content')
    <h1>{{ $page->name }}</h1>
    <p class="lead">{{ $page->meeting_type }} · {{ $page->duration_minutes }} minutes</p>

    <form method="POST" action="{{ url('/b/'.$page->slug) }}">
        <label for="name">Name <span style="color:#b42318">*</span></label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required>
        @error('name')<div class="error">{{ $message }}</div>@enderror

        <label for="email">Email <span style="color:#b42318">*</span></label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <label for="scheduled_at">Preferred time <span style="color:#b42318">*</span></label>
        <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" required>
        @error('scheduled_at')<div class="error">{{ $message }}</div>@enderror

        <label for="notes">Anything we should know?</label>
        <textarea id="notes" name="notes">{{ old('notes') }}</textarea>

        <button type="submit">Book {{ $page->meeting_type }}</button>
    </form>
@endsection
