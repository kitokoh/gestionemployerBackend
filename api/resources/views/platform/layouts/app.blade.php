<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Plateforme Leopardo RH')</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <header class="border-b border-slate-800 bg-slate-900 sticky top-0 z-50">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-8">
                <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-2 group">
                    <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center text-white font-bold group-hover:scale-110 transition-transform">
                        {{ substr(\App\Models\PlatformSetting::get('platform_name', 'Leopardo RH'), 0, 1) }}
                    </div>
                    @php
                        $nameParts = explode(' ', \App\Models\PlatformSetting::get('platform_name', 'Leopardo RH'), 2);
                    @endphp
                    <span class="text-lg font-bold tracking-tight">
                        {{ $nameParts[0] }} 
                        @if(isset($nameParts[1]))
                            <span class="text-indigo-400">{{ $nameParts[1] }}</span>
                        @endif
                    </span>
                </a>
                
                <nav class="hidden md:flex items-center gap-1">
                    <a href="{{ route('platform.dashboard') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('platform.dashboard') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50' }}">Dashboard</a>
                    <a href="{{ route('platform.companies.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('platform.companies.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50' }}">Entreprises</a>
                    <a href="{{ route('platform.plans.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('platform.plans.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50' }}">Plans</a>
                    <a href="{{ route('platform.invitations.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('platform.invitations.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50' }}">Invitations</a>
                    <a href="{{ route('platform.audit.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('platform.audit.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50' }}">Audit</a>
                    <a href="{{ route('platform.settings.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('platform.settings.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50' }}">Paramètres</a>
                </nav>
            </div>

            <div class="flex items-center gap-4">
                <div class="text-xs text-right hidden sm:block">
                    <div class="text-slate-300 font-medium">Session SuperAdmin</div>
                    <div class="text-slate-500">v4.1.4-beta</div>
                </div>
                <form method="POST" action="{{ route('platform.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 text-sm font-medium hover:bg-red-500/10 hover:text-red-400 hover:border-red-500/20 transition-all">Deconnexion</button>
                </form>
            </div>
        </div>
    </header>
    
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium text-emerald-200">{{ session('status') }}</span>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
