@extends('platform.layouts.app')

@section('title', 'Paramètres Plateforme')

@section('content')
<div class="space-y-6" x-data="{ activeTab: 'general' }">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Paramètres Globaux</h1>
            <p class="mt-1 text-sm text-slate-400">Configuration du SaaS, du branding et du mode maintenance.</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-white/5 flex gap-8">
        <button @click="activeTab = 'general'" :class="activeTab === 'general' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-300'" class="pb-4 text-sm font-semibold border-b-2 transition-all">Général</button>
        <button @click="activeTab = 'branding'" :class="activeTab === 'branding' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-300'" class="pb-4 text-sm font-semibold border-b-2 transition-all">Branding</button>
        <button @click="activeTab = 'security'" :class="activeTab === 'security' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-300'" class="pb-4 text-sm font-semibold border-b-2 transition-all">Sécurité & Maintenance</button>
    </div>

    <form action="{{ route('platform.settings.update') }}" method="POST" class="space-y-8">
        @csrf
        @method('PUT')

        <!-- General Tab -->
        <div x-show="activeTab === 'general'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-500">
            <div class="rounded-2xl border border-white/5 bg-slate-900/50 p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Nom de la Plateforme</label>
                        <input type="text" name="platform_name" value="{{ \App\Models\PlatformSetting::get('platform_name', 'Leopardo RH') }}" class="w-full rounded-xl border-slate-800 bg-slate-950 px-4 py-3 text-sm text-white focus:ring-2 focus:ring-indigo-500/40 transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Email Support</label>
                        <input type="email" name="contact_email" value="{{ \App\Models\PlatformSetting::get('contact_email', 'support@leopardo.com') }}" class="w-full rounded-xl border-slate-800 bg-slate-950 px-4 py-3 text-sm text-white focus:ring-2 focus:ring-indigo-500/40 transition-all">
                    </div>
                </div>
            </div>
        </div>

        <!-- Branding Tab -->
        <div x-show="activeTab === 'branding'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-500" x-cloak>
            <div class="rounded-2xl border border-white/5 bg-slate-900/50 p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Couleur Primaire (HEX)</label>
                        <div class="flex gap-4">
                            <input type="color" value="{{ \App\Models\PlatformSetting::get('primary_color', '#6366f1') }}" class="w-12 h-12 rounded-lg bg-transparent border-none cursor-pointer">
                            <input type="text" name="primary_color" value="{{ \App\Models\PlatformSetting::get('primary_color', '#6366f1') }}" class="flex-1 rounded-xl border-slate-800 bg-slate-950 px-4 py-3 text-sm text-white font-mono uppercase">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Logo URL (Optionnel)</label>
                        <input type="text" name="logo_url" value="{{ \App\Models\PlatformSetting::get('logo_url') }}" placeholder="https://..." class="w-full rounded-xl border-slate-800 bg-slate-950 px-4 py-3 text-sm text-white focus:ring-2 focus:ring-indigo-500/40 transition-all">
                    </div>
                </div>
            </div>
        </div>

        <!-- Security & Maintenance Tab -->
        <div x-show="activeTab === 'security'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-500" x-cloak>
            <div class="rounded-2xl border border-white/5 bg-slate-900/50 p-6 space-y-8">
                <div class="flex items-center justify-between p-4 rounded-xl bg-red-500/5 border border-red-500/10">
                    <div>
                        <h4 class="font-bold text-red-400">Mode Maintenance</h4>
                        <p class="text-xs text-slate-500 mt-0.5">Si activé, seuls les SuperAdmins pourront accéder aux dashboards.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="maintenance_mode" value="0">
                        <input type="checkbox" name="maintenance_mode" value="1" class="sr-only peer" {{ \App\Models\PlatformSetting::get('maintenance_mode', false) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-400 after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                    </label>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest">Message de maintenance</label>
                    <textarea name="maintenance_message" rows="3" class="w-full rounded-xl border-slate-800 bg-slate-950 px-4 py-3 text-sm text-white focus:ring-2 focus:ring-indigo-500/40 transition-all">{{ \App\Models\PlatformSetting::get('maintenance_message', 'La plateforme est actuellement en maintenance.') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center justify-end pt-6 border-t border-white/5">
            <button type="submit" class="rounded-xl bg-indigo-600 px-8 py-3 text-sm font-bold text-white hover:bg-indigo-500 shadow-xl shadow-indigo-500/20 active:scale-95 transition-all">
                Enregistrer les modifications
            </button>
        </div>
    </form>
</div>
@endsection
