<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance - Leopardo RH</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        .bg-obsidian { background-color: #050505; }
        .glow { box-shadow: 0 0 50px -10px rgba(99, 102, 241, 0.2); }
    </style>
</head>
<body class="min-h-screen bg-obsidian text-slate-100 flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center space-y-8 animate-in fade-in zoom-in duration-700">
        <div class="relative inline-block">
            <div class="w-24 h-24 rounded-3xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center glow mx-auto">
                <svg class="w-12 h-12 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
        </div>

        <div class="space-y-3">
            <h1 class="text-3xl font-bold tracking-tight text-white">Maintenance en cours</h1>
            <p class="text-slate-400 leading-relaxed">{{ $message }}</p>
        </div>

        <div class="pt-6">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-white/5 bg-white/5 text-[10px] uppercase tracking-[0.2em] font-bold text-slate-500">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                Retour imminent
            </div>
        </div>

        <div class="pt-12 border-t border-white/5">
            <p class="text-[10px] text-slate-600 font-medium uppercase tracking-widest">Leopardo RH Platform</p>
        </div>
    </div>
</body>
</html>
