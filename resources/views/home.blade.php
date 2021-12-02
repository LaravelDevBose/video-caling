@extends('layouts.app')

@section('content')
<example-component :users="{{ $users }}"></example-component>
@endsection
@section('js')
    @if(auth()->check())
        <script>
            window.authUser = @json(auth()->user())
        </script>
    @endif
@endsection
