@extends('public.layout')

@section('title', $form->name)

@section('content')
    <h1>{{ $form->name }}</h1>
    <p class="lead">{{ $form->settings['intro'] ?? 'Fill in your details and we\'ll be in touch.' }}</p>

    <form method="POST" action="{{ url('/f/'.$form->slug) }}">
        {{-- Honeypot: real users never fill this. --}}
        <div class="hp" aria-hidden="true">
            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        @foreach ($form->fields as $field)
            <label for="f-{{ $field['name'] }}">
                {{ $field['label'] }}@if ($field['required'] ?? false) <span style="color:#b42318">*</span>@endif
            </label>
            @if (($field['type'] ?? 'text') === 'textarea')
                <textarea id="f-{{ $field['name'] }}" name="{{ $field['name'] }}">{{ old($field['name']) }}</textarea>
            @else
                <input id="f-{{ $field['name'] }}" type="{{ $field['type'] ?? 'text' }}"
                       name="{{ $field['name'] }}" value="{{ old($field['name']) }}">
            @endif
            @error($field['name'])
                <div class="error">{{ $message }}</div>
            @enderror
        @endforeach

        <button type="submit">{{ $form->settings['button_label'] ?? 'Submit' }}</button>
    </form>
@endsection
