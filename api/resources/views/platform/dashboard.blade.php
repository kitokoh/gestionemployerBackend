@extends('platform.layouts.app')

@section('title', 'Dashboard SuperAdmin')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold bg-gradient-to-br from-white to-slate-400 bg-clip-text text-transparent">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-400">Vue globale sur la plateforme Leopardo RH</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Companies -->
        <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-6 flex flex-col relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <dt class="text-sm font-medium text-slate-400 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                Total Entreprises
            </dt>
            <dd class="mt-2 text-3xl font-bold text-white">{{ $stats['total_companies'] }}</dd>
        </div>

        <!-- Active Companies -->
        <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-6 flex flex-col relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <dt class="text-sm font-medium text-slate-400 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Entreprises Actives
            </dt>
            <dd class="mt-2 text-3xl font-bold text-emerald-400">{{ $stats['active_companies'] }}</dd>
        </div>

        <!-- Card: MRR -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Revenu (MRR)</div>
                <div class="rounded-full bg-emerald-500/10 p-2 text-emerald-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
                <div class="text-3xl font-extrabold text-white">{{ number_format($stats['mrr'], 0) }}€</div>
                <div class="text-xs text-slate-500">/ mois</div>
            </div>
            <p class="mt-1 text-xs text-slate-400">Basé sur {{ $stats['active_companies'] }} clients actifs.</p>
        </div>

        <!-- Trial -->
        <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-6 flex flex-col relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <dt class="text-sm font-medium text-slate-400 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                En Période d'Essai
            </dt>
            <dd class="mt-2 text-3xl font-bold text-white">{{ $stats['trial_companies'] }}</dd>
        </div>

        <!-- Suspended -->
        <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-6 flex flex-col relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-red-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <dt class="text-sm font-medium text-slate-400 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                Suspendues
            </dt>
            <dd class="mt-2 text-3xl font-bold text-red-500">{{ $stats['suspended_companies'] }}</dd>
        </div>
    </div>

    <!-- Employes Metric -->
    <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
        <div class="p-6 border-b border-slate-800">
            <h2 class="text-lg font-medium">Charge Utilisateurs Globale</h2>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4">
                <div class="h-16 w-16 bg-indigo-500/20 text-indigo-400 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $stats['total_employees'] }}</div>
                    <div class="text-sm text-slate-400">Employés totaux enregistrés sur le serveur</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inscriptions recentes -->
    <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
        <div class="p-6 border-b border-slate-800 flex justify-between items-center">
            <h2 class="text-lg font-medium">Récentes inscriptions</h2>
            <a href="{{ route('platform.companies.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">Voir tout</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-950/60 text-left text-slate-400">
                    <tr>
                        <th class="px-6 py-3 font-medium">Société</th>
                        <th class="px-6 py-3 font-medium">Création</th>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($recentCompanies as $company)
                        <tr class="hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ $company->name }}</div>
                                <div class="text-xs text-slate-500">{{ $company->city }}, {{ $company->country }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-400">
                                {{ \Carbon\Carbon::parse($company->created_at)->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('platform.companies.show', $company) }}" class="text-sm text-indigo-400 hover:text-indigo-300 font-medium">Gérer</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-slate-500">Aucune entreprise trouvée.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
