@extends('layouts.app')

@section('content')
<div class="max-w-xl mx-auto py-10">
    <h1 class="text-2xl font-bold mb-4">Submit a Support Ticket</h1>
    <form method="POST" action="{{ route('tickets.store') }}">
        @csrf
        <input type="hidden" name="ticket_number" value="{{ mt_rand(10000000, 99999999) }}">
        
        <div class="mb-4">
            <label class="block font-medium mb-1">Subject</label>
            <input type="text" name="subject" class="w-full border rounded p-2" required>
        </div>

        <div class="mb-4">
            <label class="block font-medium mb-1">Message</label>
            <textarea name="message" class="w-full border rounded p-2" rows="5" required></textarea>
        </div>

        <button type="submit" class="bg-primary text-white px-4 py-2 rounded">Submit</button>
    </form>
</div>
@endsection
