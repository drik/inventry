<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept Invitation — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
            {{-- Logo --}}
            <div class="text-center mb-6">
                <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-8 mx-auto dark:hidden">
                <img src="{{ asset('images/logo_white.png') }}" alt="{{ config('app.name') }}" class="h-8 mx-auto hidden dark:block">
            </div>

            {{-- Header --}}
            <div class="text-center mb-6">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Join {{ $organization->name }}
                </h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    You've been invited to join as <span class="font-medium">{{ $invitation->role->getLabel() }}</span>.
                </p>
            </div>

            {{-- Errors --}}
            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <ul class="text-sm text-red-600 dark:text-red-400 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('invitation.accept.store', $invitation->token) }}" class="space-y-4">
                @csrf

                {{-- Email (read-only) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <input type="email" value="{{ $invitation->email }}" disabled
                        class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 text-sm">
                </div>

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                        class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none">
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                    <input type="password" id="password" name="password" required minlength="8"
                        class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none">
                </div>

                {{-- Confirm Password --}}
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8"
                        class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none">
                </div>

                {{-- Submit --}}
                <button type="submit"
                    class="w-full py-2.5 px-4 bg-amber-500 hover:bg-amber-600 text-white font-medium rounded-lg text-sm transition-colors">
                    Accept Invitation & Create Account
                </button>
            </form>

            {{-- Footer --}}
            <p class="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">
                This invitation expires on {{ $invitation->expires_at->format('M d, Y') }}.
            </p>
        </div>
    </div>
</body>
</html>
