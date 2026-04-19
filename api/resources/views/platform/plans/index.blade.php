@extends('platform.layouts.app')

@section('title', 'Gestion des Plans Tarifaires')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Plans & Tarification</h1>
            <p class="mt-1 text-sm text-slate-400">Configurez les paliers d'abonnement et les fonctionnalités associées.</p>
        </div>
        <a href="{{ route('platform.plans.create') }}" class="rounded-lg bg-indigo-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-400 shadow-lg shadow-indigo-500/20 transition-all">Nouveau Plan</a>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach($plans as $plan)
            <div class="relative overflow-hidden rounded-2xl border {{ $plan->is_active ? 'border-slate-800 bg-slate-900' : 'border-slate-800 bg-slate-950 opacity-60' }} p-6 flex flex-col shadow-xl">
                @if(!$plan->is_active)
                    <div class="absolute top-3 right-3 rounded bg-slate-800 px-2 py-0.5 text-[10px] uppercase font-bold text-slate-500">Inactif</div>
                @endif

                <div class="mb-4">
                    <h3 class="text-lg font-bold text-white">{{ $plan->name }}</h3>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-3xl font-extrabold text-indigo-400">{{ number_format($plan->price_monthly, 0) }}€</span>
                        <span class="text-sm text-slate-500">/ mois</span>
                    </div>
                    <div class="text-xs text-slate-500 italic">ou {{ number_format($plan->price_yearly, 0) }}€ / an</div>
                </div>

                <div class="flex-1 space-y-3 border-t border-slate-800 pt-4 mb-6">
                    <div class="flex items-center gap-2 text-sm text-slate-300">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span>Jusqu'à <strong>{{ $plan->max_employees ?? '∞' }}</strong> employés</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-300">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span>{{ $plan->trial_days }} jours d'essai gratuit</span>
                    </div>

                    <div class="mt-4 space-y-1">
                        <div class="text-[10px] uppercase font-bold text-slate-600 mb-1">Fonctionnalités :</div>
                        @php
                            $allFeatures = ['biometric', 'tasks', 'advanced_reports', 'excel_export', 'bank_export', 'billing_auto', 'multi_managers', 'photo_attendance'];
                        @endphp
                        @foreach($allFeatures as $feature)
                             @if($plan->hasFeature($feature))
                                <div class="flex items-center gap-2 text-[11px] text-slate-400">
                                    <div class="w-1 h-1 rounded-full bg-indigo-500"></div>
                                    <span>{{ Str::headline($feature) }}</span>
                                </div>
                             @endif
                        @endforeach
                    </div>
                </div>

                <div class="mt-auto">
                    <a href="{{ route('platform.plans.edit', $plan) }}" class="flex w-full items-center justify-center rounded-lg border border-slate-800 bg-slate-800/50 px-4 py-2 text-sm font-medium hover:bg-slate-800 hover:text-indigo-400 transition-colors">
                        Modifier les paramètres
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
