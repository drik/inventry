<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation Expired — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 text-center">
            {{-- Logo --}}
            <div class="mb-6">
                <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-8 mx-auto dark:hidden">
                <img src="{{ asset('images/logo_white.png') }}" alt="{{ config('app.name') }}" class="h-8 mx-auto hidden dark:block">
            </div>

            {{-- Icon --}}
            <div class="mb-4">
                <svg class="w-16 h-16 mx-auto text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>

            <h1 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                Invitation Expired
            </h1>

            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                This invitation is no longer valid. Please contact the organization administrator to receive a new invitation.
            </p>

            <a href="{{ url('/app') }}"
                class="inline-block py-2.5 px-6 bg-amber-500 hover:bg-amber-600 text-white font-medium rounded-lg text-sm transition-colors">
                Go to Login
            </a>
        </div>
    </div>
</body>
</html>
