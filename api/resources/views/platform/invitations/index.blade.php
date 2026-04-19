@extends('platform.layouts.app')

@section('title', 'Invitations en attente')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Relances Managers</h1>
        <p class="mt-1 text-sm text-slate-400">Suivi des invitations envoyées aux futurs gestionnaires de société.</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-950/60 text-left text-slate-400">
                <tr>
                    <th class="px-6 py-4 font-medium uppercase tracking-wider text-xs">Destinataire</th>
                    <th class="px-6 py-4 font-medium uppercase tracking-wider text-xs">Société / Rôle</th>
                    <th class="px-6 py-4 font-medium uppercase tracking-wider text-xs">Dernier envoi</th>
                    <th class="px-6 py-4 font-medium uppercase tracking-wider text-xs">Expiration</th>
                    <th class="px-6 py-4 font-medium uppercase tracking-wider text-xs text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($invitations as $invitation)
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-medium text-white">{{ $invitation->email }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if($invitation->company)
                                <div class="text-indigo-400 font-medium">{{ $invitation->company->name }}</div>
                            @else
                                <div class="text-slate-500 italic">Société inconnue</div>
                            @endif
                            <div class="text-[10px] text-slate-500 uppercase tracking-tighter">{{ $invitation->role }} / {{ $invitation->manager_role ?? 'General' }}</div>
                        </td>
                        <td class="px-6 py-4 text-slate-400">
                            {{ $invitation->last_sent_at ? $invitation->last_sent_at->diffForHumans() : 'Jamais' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($invitation->expires_at->isPast())
                                <span class="inline-flex items-center rounded-full bg-red-400/10 px-2 py-0.5 text-xs font-medium text-red-400 border border-red-500/20">Expiré</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-emerald-400/10 px-2 py-0.5 text-xs font-medium text-emerald-400 border border-emerald-500/20">Actif</span>
                                <div class="text-[10px] text-slate-600 mt-1">Expire le {{ $invitation->expires_at->format('d/m/Y') }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <form action="{{ route('platform.invitations.resend', $invitation) }}" method="POST" class="inline-block">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-indigo-500/20 bg-indigo-500/10 px-3 py-1.5 text-xs font-medium text-indigo-400 hover:bg-indigo-500 hover:text-white transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    Relancer
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="text-slate-500 mb-2">
                                <svg class="w-12 h-12 mx-auto mb-4 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Aucune invitation en attente.
                            </div>
                            <p class="text-xs text-slate-600">Tous vos managers ont activé leur compte ou aucune invitation n'a été envoyée.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $invitations->links() }}
    </div>
</div>
@endsection
