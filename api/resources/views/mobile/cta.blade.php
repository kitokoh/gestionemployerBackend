@extends('layouts.app')

@section('title', 'Leopardo RH mobile')

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-rh/15 text-rh">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>
                </svg>
            </div>

            <h1 class="mt-4 text-2xl font-semibold tracking-tight">Bonjour {{ $employee->first_name ?? $employee->email }}</h1>

            <p class="mt-3 text-sm text-slate-300">
                Leopardo RH est concu comme une experience mobile-first.
                En tant qu employe, vous pointez, consultez vos heures et
                votre gain estime depuis l app Leopardo RH sur votre smartphone.
            </p>

            <div class="mt-6 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                <a href="#" class="rounded-lg bg-rh px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-rh-dark">
                    Telecharger sur Google Play
                </a>
                <a href="#" class="rounded-lg border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-100 hover:bg-slate-800">
                    Telecharger sur l App Store
                </a>
            </div>

            <p class="mt-6 text-xs text-slate-500">
                Liens de telechargement a venir des que les builds seront publies sur les stores.
            </p>

            <div class="mt-8 border-t border-slate-800 pt-4">
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-slate-400 underline hover:text-slate-200">
                        Se deconnecter
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
