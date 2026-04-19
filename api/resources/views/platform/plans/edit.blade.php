@extends('platform.layouts.app')

@section('title', 'Modifier le Plan')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('platform.plans.index') }}" class="group flex h-10 w-10 items-center justify-center rounded-full border border-slate-800 bg-slate-900 transition-colors hover:border-slate-700">
            <svg class="h-5 w-5 text-slate-500 group-hover:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold">Modifier : {{ $plan->name }}</h1>
            <p class="text-sm text-slate-500">Mise à jour des tarifs et des caractéristiques.</p>
        </div>
    </div>

    <form action="{{ route('platform.plans.update', $plan) }}" method="POST" class="space-y-8">
        @csrf
        @method('PUT')
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-300">Nom du plan</label>
                    <input type="text" name="name" required placeholder="ex: Starter, Business..." value="{{ old('name', $plan->name) }}" class="mt-1 block w-full rounded-lg border-slate-800 bg-slate-950 text-white focus:border-indigo-500 @error('name') border-red-500 @enderror">
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300">Prix Mensuel (€)</label>
                    <input type="number" step="0.01" name="price_monthly" required value="{{ old('price_monthly', $plan->price_monthly) }}" class="mt-1 block w-full rounded-lg border-slate-800 bg-slate-950 text-white focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300">Prix Annuel (€)</label>
                    <input type="number" step="0.01" name="price_yearly" required value="{{ old('price_yearly', $plan->price_yearly) }}" class="mt-1 block w-full rounded-lg border-slate-800 bg-slate-950 text-white focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300">Max Employés (vide = illimité)</label>
                    <input type="number" name="max_employees" value="{{ old('max_employees', $plan->max_employees) }}" placeholder="∞" class="mt-1 block w-full rounded-lg border-slate-800 bg-slate-950 text-white focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300">Période d'essai (jours)</label>
                    <input type="number" name="trial_days" required value="{{ old('trial_days', $plan->trial_days) }}" class="mt-1 block w-full rounded-lg border-slate-800 bg-slate-950 text-white focus:border-indigo-500">
                </div>

                <div class="md:col-span-2 py-2">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $plan->is_active) ? 'checked' : '' }} class="h-5 w-5 rounded border-slate-800 bg-slate-950 text-indigo-500 focus:ring-indigo-500">
                        <label for="is_active" class="text-sm font-medium text-slate-300">Plan actif et visible pour les nouvelles inscriptions</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
            <h2 class="text-lg font-bold mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                Fonctionnalités & Modules activés
            </h2>

            <div class="grid gap-4 sm:grid-cols-2">
                @php
                    $featuresList = [
                        'biometric' => 'Pointeuse Biométrique',
                        'tasks' => 'Gestion des Tâches',
                        'advanced_reports' => 'Rapports Avancés',
                        'excel_export' => 'Export Excel',
                        'bank_export' => 'Export Bancaire',
                        'billing_auto' => 'Facturation Auto',
                        'multi_managers' => 'Multi-Managers',
                        'photo_attendance' => 'Pointage avec Photo',
                        'api_public' => 'Accès API Publique',
                        'evaluations' => 'Évaluations Annuelles',
                        'schema_isolation' => 'Isolation Schéma PostgreSQL (Entreprise)',
                    ];
                @endphp

                @foreach($featuresList as $key => $label)
                    <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-800 bg-slate-950 hover:bg-slate-800/40 transition-colors">
                        <input type="checkbox" name="features[{{ $key }}]" id="feat_{{ $key }}" value="1" {{ old("features.$key", $plan->hasFeature($key)) ? 'checked' : '' }} class="h-5 w-5 rounded border-slate-700 bg-slate-900 text-indigo-500 focus:ring-indigo-500">
                        <label for="feat_{{ $key }}" class="text-sm text-slate-300 cursor-pointer">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end pt-4">
            <button type="submit" class="rounded-xl bg-emerald-500 px-8 py-3 font-bold text-slate-950 shadow-lg shadow-emerald-500/30 hover:bg-emerald-400 transition-all flex items-center gap-2">
                Mettre à jour le plan
            </button>
        </div>
    </form>
</div>
@endsection
