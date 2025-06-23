<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FunkIT HelpDesk</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50 text-gray-800 flex items-center justify-center min-h-screen">

    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-6">
        <div class="max-w-2xl w-full text-center">
            <img src="{{ asset('images/funkit-logo.png') }}" alt="FunkIT Logo" class="mx-auto w-24 h-24 mb-6">

            <h1 class="text-4xl font-bold text-primary mb-4">Welcome to FunkIT HelpDesk</h1>
            <p class="text-gray-600 text-lg mb-8">{{ $tagline }}</p>

            <div class="flex justify-center gap-6">
                <a href="{{ route('login') }}"
                   class="px-6 py-3 bg-primary text-white rounded shadow hover:bg-opacity-90 transition">
                    Login
                </a>

                <a href="{{ route('register') }}"
                   class="px-6 py-3 border border-primary text-primary rounded hover:bg-primary hover:text-white transition">
                    Register
                </a>
            </div>
        </div>
    </div>

</body>
</html>
