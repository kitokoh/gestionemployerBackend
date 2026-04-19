@extends('platform.layouts.app')

@section('title', 'Modifier l\'entreprise : ' . $company->name)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-white">Modifier : {{ $company->name }}</h1>
        <a href="{{ route('platform.companies.show', $company) }}" class="text-sm text-slate-400 hover:text-white">Retour au profil</a>
    </div>

    <!-- Affichage des erreurs -->
    @if($errors->any())
        <div class="p-4 rounded-lg bg-red-500/10 border border-red-500/20 text-red-500 text-sm">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
        <form action="{{ route('platform.companies.update', $company) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-6">
                <!-- Nom de l'entreprise -->
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-300">Nom commercial de l'entreprise</label>
                    <input type="text" name="name" id="name" required value="{{ old('name', $company->name) }}" class="mt-1 block w-full rounded-md border-slate-700 bg-slate-800 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                <!-- Plan d'abonnement -->
                <div>
                    <label for="plan_id" class="block text-sm font-medium text-slate-300">Gérer l'abonnement (Plan)</label>
                    <div class="mt-1 rounded-md bg-slate-800/50 p-4 border border-slate-800">
                        <select name="plan_id" id="plan_id" class="block w-full rounded-md border-slate-700 bg-slate-800 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" {{ old('plan_id', $company->plan_id) == $plan->id ? 'selected' : '' }}>
                                    Plan #{{ $plan->id }} - {{ $plan->name }} ({{ $plan->price_monthly }} EUR)
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-amber-400">Attention: Un changement de plan n'ajuste pas la facturation Stripe automatiquement dans cette version.</p>
                    </div>
                </div>

                <!-- Status forcage -->
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-300">Statut Manuel</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-slate-700 bg-slate-800 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="trial" {{ old('status', $company->status) === 'trial' ? 'selected' : '' }}>Trial (Période d'essai)</option>
                        <option value="active" {{ old('status', $company->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ old('status', $company->status) === 'suspended' ? 'selected' : '' }}>Suspendue (Blocage complet)</option>
                        <option value="expired" {{ old('status', $company->status) === 'expired' ? 'selected' : '' }}>Abonnement Expiré</option>
                    </select>
                </div>
            </div>

            <div class="pt-6 mt-6 border-t border-slate-800 flex justify-end">
                <button type="submit" class="rounded-lg bg-indigo-500 px-6 py-2.5 font-medium text-white hover:bg-indigo-600 transition-colors">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
