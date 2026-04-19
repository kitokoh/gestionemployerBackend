@extends('platform.layouts.app')

@section('title', 'Entreprises')

@section('content')
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Entreprises clientes</h1>
            <p class="mt-1 text-sm text-slate-400">Gerez vos clients, surveillez leur facturation, et controlez les acces du SaaS.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('platform.companies.export') }}" class="rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 font-medium text-white hover:bg-slate-700 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export CSV
            </a>
            <a href="{{ route('platform.companies.create') }}" class="rounded-lg bg-emerald-500 px-4 py-3 font-medium text-slate-950 hover:bg-emerald-400">Nouvelle entreprise</a>
        </div>
    </div>

    <form method="GET" action="{{ route('platform.companies.index') }}" class="mt-6 grid gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-4 md:grid-cols-[1fr,220px,auto]">
        <input
            type="text"
            name="q"
            value="{{ request('q') }}"
            placeholder="Rechercher par nom, email, ville ou slug"
            class="rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-500"
        >
        <select name="status" class="rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white">
            <option value="">Tous les statuts</option>
            @foreach(['active' => 'Active', 'trial' => 'Trial', 'suspended' => 'Suspendu', 'expired' => 'Expire'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-indigo-500 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-400">Filtrer</button>
            @if(request()->filled('q') || request()->filled('status'))
                <a href="{{ route('platform.companies.index') }}" class="rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-medium text-slate-200 hover:bg-slate-700">Reset</a>
            @endif
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950/20">
            <span class="text-sm font-medium text-slate-400 text-uppercase tracking-wider">{{ $companies->total() }} entreprise(s) trouvee(s)</span>
        </div>
        <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-950/60 text-left text-slate-400">
                <tr>
                    <th class="px-6 py-4 font-medium uppercase tracking-wider text-xs">Entreprise / Tenant</th>
                    <th class="px-6 py-4 font-medium uppercase tracking-wider text-xs">Facturation</th>
                    <th class="px-6 py-4 font-medium uppercase tracking-wider text-xs">Statut</th>
                    <th class="px-6 py-4 font-medium uppercase tracking-wider text-xs text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($companies as $company)
                    <tr class="hover:bg-slate-800/20 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-medium text-white">{{ $company->name }}</div>
                            <div class="text-xs text-slate-500 mt-1">{{ $company->email }} • {{ $company->city }}</div>
                            <div class="text-[10px] uppercase font-mono text-slate-600 mt-1">Tenant: {{ $company->schema_name }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-blue-500/20 bg-blue-500/10 text-blue-400">
                                {{ $company->plan?->name ?? ('Plan #' . $company->plan_id) }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($company->status === 'active')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Active
                                </span>
                            @elseif($company->status === 'trial')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium border border-orange-500/20 bg-orange-500/10 text-orange-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                                    Trial
                                </span>
                            @elseif($company->status === 'suspended')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium border border-red-500/20 bg-red-500/10 text-red-500">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Suspendu
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium border border-slate-500/20 bg-slate-500/10 text-slate-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                    {{ ucfirst($company->status) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <a href="{{ route('platform.companies.show', $company) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                                Gerer
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-800 mb-4 text-slate-500">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-white mb-1">Aucune entreprise</h3>
                            <p class="text-slate-400">Commencez par ajouter votre premier client sur la plateforme.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $companies->links() }}
    </div>
@endsection
