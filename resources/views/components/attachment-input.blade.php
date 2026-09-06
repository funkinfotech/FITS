@props(['label' => 'Attachments'])

@php
    $config = config('attachments');
    $accept = collect($config['allowed'])->keys()->map(fn ($e) => ".{$e}")->implode(',');
    $maxMb = rtrim(rtrim(number_format($config['max_size_kb'] / 1024, 1), '0'), '.');
@endphp

<div>
    <label class="block text-sm font-medium text-gray-700">
        {{ $label }} <span class="font-normal text-gray-400">(optional)</span>
    </label>

    <input
        type="file"
        name="attachments[]"
        multiple
        accept="{{ $accept }}"
        class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
    >

    <p class="mt-1 text-xs text-gray-400">
        Up to {{ $config['max_files'] }} files, {{ $maxMb }} MB each — images, PDF, Office documents, text/logs, or .zip.
    </p>

    <x-input-error :messages="$errors->get('attachments')" class="mt-1" />
    <x-input-error :messages="$errors->get('attachments.*')" class="mt-1" />
</div>
