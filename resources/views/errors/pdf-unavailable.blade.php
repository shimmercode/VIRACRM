@extends('layouts.plain')

@section('title', 'سند قابل تولید نیست')

@section('content')
    {{--
        A refused PDF export is not a 500. The user asked for a document that the
        product will not produce — a draft contract, an empty credentials file —
        and the page has to say which rule stopped it and what to do next.
        Sending a 500 or an empty PDF teaches the user nothing.
    --}}
    <div class="v-errorpage">
        <span class="v-errorpage__icon"><x-icon name="document" :size="30" /></span>
        <p class="v-errorpage__code">۴۰۹</p>
        <h1 class="v-errorpage__title">سند قابل تولید نیست</h1>
        <p class="v-errorpage__desc">{{ $message }}</p>

        <div class="v-errorpage__actions">
            <a href="{{ url()->previous() }}" class="v-btn v-btn--primary">بازگشت</a>
        </div>
    </div>
@endsection
