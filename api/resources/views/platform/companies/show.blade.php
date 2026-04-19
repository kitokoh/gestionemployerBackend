@extends('platform.layouts.app')

@section('title', 'Détails de l\'entreprise : ' . $company->name)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $company->name }}</h1>
            <p class="mt-1 text-sm text-slate-400">Tenant: <span class="font-mono">{{ $company->schema_name }}</span></p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('platform.companies.edit', $company) }}" class="rounded-lg bg-slate-800 border border-slate-700 px-4 py-2 font-medium text-white hover:bg-slate-700">Modifier</a>

            @if($company->status !== 'suspended')
                <form action="{{ route('platform.companies.suspend', $company) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-lg bg-red-500/10 border border-red-500/20 px-4 py-2 font-medium text-red-500 hover:bg-red-500 hover:text-white transition-colors" onclick="return confirm('Attention: Cela bloquera tout accès (managers, employés, kiosques). Êtes-vous sûr ?')">
                        Suspendre l'accès
                    </button>
                </form>
            @else
                <form action="{{ route('platform.companies.reactivate', $company) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-4 py-2 font-medium text-emerald-500 hover:bg-emerald-500 hover:text-white transition-colors">
                        Sauf Reçu — Réactiver
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Alertes de statut -->
    @if(session('status'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-medium">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Informations Générales -->
        <div class="md:col-span-2 space-y-6">
            <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
                <div class="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950/30">
                    <h2 class="font-medium text-white">Profil de l'entreprise</h2>
                    @if($company->status === 'active')
                        <span class="px-2.5 py-1 rounded-md text-xs font-medium border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">Active</span>
                    @elseif($company->status === 'trial')
                        <span class="px-2.5 py-1 rounded-md text-xs font-medium border border-orange-500/20 bg-orange-500/10 text-orange-400">En test (Trial)</span>
                    @else
                        <span class="px-2.5 py-1 rounded-md text-xs font-medium border border-red-500/20 bg-red-500/10 text-red-500">Suspendue</span>
                    @endif
                </div>
                <div class="p-6 grid grid-cols-2 gap-y-6 gap-x-4">
                    <div>
                        <div class="text-sm font-medium text-slate-500">Localisation</div>
                        <div class="mt-1 text-sm text-white">{{ $company->city }}, {{ $company->country }}</div>
                        <div class="text-xs text-slate-400">{{ $company->address ?? 'Non spécifiée' }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-slate-500">Contact Facturation</div>
                        <div class="mt-1 text-sm text-white">{{ $company->email }}</div>
                        <div class="text-xs text-slate-400">{{ $company->phone ?? 'Aucun téléphone' }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-slate-500">Secteur</div>
                        <div class="mt-1 text-sm text-white">{{ $company->sector }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-slate-500">Paramètres HR</div>
                        <div class="mt-1 text-sm text-white">{{ strtoupper($company->currency ?? '-') }} • {{ strtoupper($company->language ?? '-') }}</div>
                        <div class="text-xs text-slate-400">{{ $company->timezone ?? 'Default Timezone' }}</div>
                    </div>
                </div>
            </div>

            <!-- Paramètres techniques -->
            <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
                <div class="p-4 border-b border-slate-800 bg-slate-950/30">
                    <h2 class="font-medium text-white">Isolation & Sécurité (Tenancy)</h2>
                </div>
                <div class="p-6 flex flex-wrap gap-6">
                    <div class="bg-slate-800 rounded-lg p-4 flex-1">
                        <div class="text-xs text-slate-400 uppercase tracking-widest font-semibold mb-1">Architecture</div>
                        <div class="font-mono text-lg text-emerald-400">{{ strtoupper($company->tenancy_type) }}</div>
                        <p class="text-xs text-slate-500 mt-2">
                            @if($company->tenancy_type === 'shared')
                                Données isolées logiquement dans "shared_tenants".
                            @else
                                Schéma PostgreSQL dédié unique.
                            @endif
                        </p>
                    </div>
                    <div class="bg-slate-800 rounded-lg p-4 flex-1">
                        <div class="text-xs text-slate-400 uppercase tracking-widest font-semibold mb-1">Stockage DB</div>
                        <div class="font-mono text-sm text-white mt-1">{{ $company->schema_name }}</div>
                        <div class="text-xs text-slate-500 mt-2">ID: {{ $company->id }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar (Stats et Manager) -->
        <div class="space-y-6">
            <!-- Abonnement -->
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-widest mb-4">Abonnement</h3>
                <div class="flex items-center justify-between">
                    <span class="text-white font-medium">Plan Actuel</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border border-blue-500/20 bg-blue-500/10 text-blue-400">ID: {{ $company->plan_id }}</span>
                </div>
                <div class="mt-4 pt-4 border-t border-slate-800">
                    <div class="text-xs text-slate-400">Inscrit le</div>
                    <div class="text-sm text-white">{{ $company->created_at->format('d M Y') }}</div>
                </div>
            </div>

            <!-- Statistiques d'usage -->
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-widest mb-4">Utilisation RH</h3>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-white">{{ $employeesCount }}</div>
                        <div class="text-xs text-slate-400">Employés actifs</div>
                    </div>
                </div>
            </div>

            <!-- Manager Principal -->
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-widest mb-4">Gérant / Master Admin</h3>
                @if($managerLookup)
                    <div class="text-sm text-white font-medium break-all">{{ $managerLookup->email }}</div>
                    <div class="text-xs text-slate-400 mt-1">Employé interne ID: {{ $managerLookup->employee_id }}</div>
                @else
                    <div class="text-sm text-yellow-500 bg-yellow-500/10 px-3 py-2 rounded">
                        Aucun manager activé détecté. L'entreprise pourrait ne pas avoir complété son onboarding.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
