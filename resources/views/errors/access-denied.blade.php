<x-guest-layout>
    <div class="text-center py-20">
        <h1 class="text-3xl font-bold text-red-600 mb-4">Access Denied</h1>
        <p class="text-gray-700 mb-6">You do not have permission to access this area.</p>
        <a href="{{ route('dashboard') }}" class="text-indigo-600 underline hover:text-indigo-800">
            Return to Dashboard
        </a>
    </div>
</x-guest-layout>
