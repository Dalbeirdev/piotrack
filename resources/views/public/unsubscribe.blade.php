@extends('public.layout')

@section('title', 'Unsubscribe')

@section('content')
    <h1>Unsubscribe</h1>
    <p class="lead">Confirm you no longer want to receive these messages.</p>
    <form method="POST" action="{{ url('/e/u/'.$token) }}">
        <button type="submit">Unsubscribe me</button>
    </form>
@endsection
