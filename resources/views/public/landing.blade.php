@extends('public.layout')

@section('title', $page->headline)

@section('content')
    <h1>{{ $page->headline }}</h1>
    @if ($page->subheadline)
        <p class="lead">{{ $page->subheadline }}</p>
    @endif

    @if ($page->body_html)
        <div>{!! strip_tags($page->body_html, '<p><br><ul><ol><li><strong><em><a><h2><h3>') !!}</div>
    @endif

    @if ($form)
        <form method="POST" action="{{ url('/f/'.$form->slug) }}">
            <div class="hp" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            @foreach ($form->fields as $field)
                <label for="f-{{ $field['name'] }}">
                    {{ $field['label'] }}@if ($field['required'] ?? false) <span style="color:#b42318">*</span>@endif
                </label>
                @if (($field['type'] ?? 'text') === 'textarea')
                    <textarea id="f-{{ $field['name'] }}" name="{{ $field['name'] }}"></textarea>
                @else
                    <input id="f-{{ $field['name'] }}" type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}">
                @endif
            @endforeach
            <button type="submit">{{ $form->settings['button_label'] ?? 'Get started' }}</button>
        </form>
    @endif
@endsection
