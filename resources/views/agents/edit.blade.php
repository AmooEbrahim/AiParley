@extends('layouts.app')

@section('title', 'Edit agent · AiParley')

@section('content')
    <div class="mb-6">
        <a href="{{ route('agents.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">&larr; Back to agents</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Edit agent</h1>
    </div>

    <div class="card max-w-3xl p-5">
        @include('agents._form', ['agent' => $agent, 'isEdit' => true])
    </div>
@endsection
