@extends('platform.layouts.app')

@section('title', 'Logs d\'Audit Système')

@section('content')
<div class="space-y-6" x-data="{ 
    showModal: false, 
    modalContent: '{}', 
    openModal(content) { 
        this.modalContent = content; 
        this.showModal = true; 
    } 
}">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Sécurité & Audit</h1>
            <p class="mt-1 text-sm text-slate-400">Traçabilité complète des actions effectuées sur la plateforme.</p>
        </div>
        
        <form method="GET" action="{{ route('platform.audit.index') }}" class="flex flex-wrap items-center gap-3">
            <select name="actor_type" class="rounded-lg border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-all">
                <option value="">Tous les acteurs</option>
                <option value="super_admin" {{ request('actor_type') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                <option value="system" {{ request('actor_type') === 'system' ? 'selected' : '' }}>Système</option>
                <option value="manager" {{ request('actor_type') === 'manager' ? 'selected' : '' }}>Manager</option>
            </select>
            <div class="relative">
                <input type="text" name="action" placeholder="Filtrer par action..." value="{{ request('action') }}" class="rounded-lg border-slate-800 bg-slate-900 pl-3 pr-10 py-2 text-sm text-slate-300 placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-all min-w-[200px]">
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    <svg class="h-4 w-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 active:scale-95 transition-all">Filtrer</button>
            @if(request()->anyFilled(['actor_type', 'action']))
                <a href="{{ route('platform.audit.index') }}" class="text-xs text-slate-500 hover:text-indigo-400 font-medium">Réinitialiser</a>
            @endif
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/5 bg-slate-900/50 backdrop-blur-sm shadow-xl">
        <table class="min-w-full divide-y divide-white/5 text-sm">
            <thead class="bg-black/20 text-left text-slate-500">
                <tr>
                    <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[10px]">Date & Heure</th>
                    <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[10px]">Acteur</th>
                    <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[10px]">Action effectués</th>
                    <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[10px]">Cible</th>
                    <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[10px] text-right">Détails</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($logs as $log)
                    <tr class="hover:bg-white/[0.02] transition-colors group">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-slate-300 font-medium">{{ $log->created_at->format('d/m/Y') }}</div>
                            <div class="text-[10px] text-slate-500 font-mono">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full {{ $log->actor_type === 'super_admin' ? 'bg-indigo-500' : ($log->actor_type === 'system' ? 'bg-amber-500' : 'bg-slate-500') }}"></div>
                                <span class="text-xs font-semibold {{ $log->actor_type === 'super_admin' ? 'text-indigo-300' : 'text-slate-400' }}">
                                    {{ strtoupper($log->actor_type) }}
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-600 mt-1 font-mono">ID: {{ $log->actor_id }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-semibold text-white tracking-tight">{{ $log->action }}</div>
                            <div class="text-[10px] text-slate-500 font-mono mt-0.5">{{ $log->ip_address }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if($log->company_id)
                                <div class="flex items-center gap-1.5 text-xs text-indigo-400 font-medium">
                                    <svg class="w-3.5 h-3.5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    Société
                                </div>
                                <div class="text-[10px] text-slate-500 font-mono pl-5">{{ $log->company_id }}</div>
                            @else
                                <span class="text-xs text-slate-700 font-medium italic">Plateforme</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <button 
                                @click="openModal('{{ addslashes(json_encode($log->metadata, JSON_PRETTY_PRINT)) }}')"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-800 bg-slate-950/50 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition-all active:scale-95"
                            >
                                <svg class="w-3.5 h-3.5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Voir Meta
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-20 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-800/50 mb-4">
                                <svg class="w-8 h-8 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-slate-400">Aucun log trouvé</h3>
                            <p class="text-xs text-slate-600 mt-1 max-w-[200px] mx-auto">Modifiez vos filtres ou attendez que des actions soient enregistrées.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>

    <!-- Metadata Modal -->
    <template x-teleport="body">
        <div 
            x-show="showModal" 
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            x-cloak
        >
            <!-- Overlay -->
            <div 
                x-show="showModal" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="showModal = false" 
                class="absolute inset-0 bg-black/80 backdrop-blur-sm"
            ></div>

            <!-- Content -->
            <div 
                x-show="showModal" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative w-full max-w-2xl rounded-2xl border border-white/10 bg-slate-900 shadow-2xl overflow-hidden"
            >
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-white">Métadonnées de l'action</h3>
                    <button @click="showModal = false" class="text-slate-500 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-6 overflow-auto max-h-[60vh]">
                    <pre class="text-xs font-mono text-indigo-300 bg-black/40 p-6 rounded-xl border border-white/5 overflow-x-auto leading-relaxed" x-text="modalContent"></pre>
                </div>
                <div class="px-6 py-4 border-t border-white/5 bg-black/10 flex justify-end">
                    <button @click="showModal = false" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 transition-all">Fermer</button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
