@extends('core::admin.layout')

@section('title', 'Module')

@section('content')
<div class="space-y-6">

    <div>
        <h1 style="font-size:22px; font-weight:800; color:#1e293b;">Module</h1>
        <p style="font-size:13px; color:#94a3b8; margin-top:4px;">
            Installierte und verfügbare Module verwalten.
        </p>
    </div>

    <!-- Installierte Module -->
    <div>
        <div style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:10px;">
            Installiert ({{ count($installed) }})
        </div>

        @forelse($installed as $slug => $module)
        <div style="background:white; border:1px solid #e2e8f0; border-left:4px solid {{ $module->is_active ? '#1a6fc4' : '#94a3b8' }}; border-radius:12px; padding:16px; margin-bottom:10px;">
            <div style="display:flex; align-items:flex-start; gap:14px;">
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                        <span style="font-size:15px; font-weight:700; color:#1e293b;">{{ $module->name }}</span>
                        <span style="font-size:11px; color:#94a3b8;">v{{ $module->version }}</span>
                        @if($module->is_active)
                            <span style="background:#dcfce7; color:#166534; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px;">Aktiv</span>
                        @else
                            <span style="background:#f1f5f9; color:#64748b; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px;">Inaktiv</span>
                        @endif
                        @if($slug === 'core')
                            <span style="background:#dbeafe; color:#1d4ed8; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px;">Pflicht</span>
                        @endif
                    </div>
                    <div style="font-size:12px; color:#64748b;">
                        Installiert: {{ \Carbon\Carbon::parse($module->installed_at)->format('d.m.Y H:i') }}
                    </div>
                </div>

                @if($slug !== 'core')
                <div style="display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap;">
                    @if($module->is_active)
                    <form method="POST" action="{{ route('admin.modules.deactivate', $slug) }}">
                        @csrf
                        <button type="submit"
                                style="background:#fef3c7; color:#92400e; border:1px solid #fde047; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer;">
                            Deaktivieren
                        </button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('admin.modules.activate', $slug) }}">
                        @csrf
                        <button type="submit"
                                style="background:#dcfce7; color:#166534; border:1px solid #86efac; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer;">
                            Aktivieren
                        </button>
                    </form>
                    @endif

                    <form method="POST" action="{{ route('admin.modules.remove', $slug) }}"
                          onsubmit="return confirm('Modul {{ $module->name }} wirklich entfernen?\n\nAchtung: Alle Tabellen und Daten dieses Moduls werden GELÖSCHT.')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                style="background:#fef2f2; color:#991b1b; border:1px solid #fca5a5; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer;">
                            Entfernen
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>
        @empty
        <div style="background:white; border:1px solid #e2e8f0; border-radius:12px; padding:24px; text-align:center; color:#94a3b8; font-size:13px;">
            Keine Module installiert.
        </div>
        @endforelse
    </div>

    <!-- Verfügbare (noch nicht installierte) Module -->
    @php
        $notInstalled = array_filter($available, fn($slug) => !isset($installed[$slug]), ARRAY_FILTER_USE_KEY);
    @endphp

    @if(!empty($notInstalled))
    <div>
        <div style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:10px;">
            Verfügbar – nicht installiert ({{ count($notInstalled) }})
        </div>

        @foreach($notInstalled as $slug => $config)
        <div style="background:white; border:1px dashed #cbd5e1; border-radius:12px; padding:16px; margin-bottom:10px;">
            <div style="display:flex; align-items:flex-start; gap:14px;">
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                        <span style="font-size:15px; font-weight:700; color:#1e293b;">{{ $config['name'] }}</span>
                        <span style="font-size:11px; color:#94a3b8;">v{{ $config['version'] ?? '1.0.0' }}</span>
                    </div>
                    @if(!empty($config['description']))
                    <div style="font-size:12px; color:#64748b; margin-bottom:6px;">{{ $config['description'] }}</div>
                    @endif
                    @if(!empty(array_filter($config['requires'] ?? [], fn($r) => $r !== 'core')))
                    <div style="font-size:11px; color:#d97706;">
                        Benötigt: {{ implode(', ', array_filter($config['requires'], fn($r) => $r !== 'core')) }}
                    </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.modules.install', $slug) }}" style="flex-shrink:0;">
                    @csrf
                    <button type="submit"
                            style="background:#0a1628; color:white; border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;">
                        + Installieren
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Dateien vorhanden aber kein module.json -->
    @if(empty($available))
    <div style="background:#fef9c3; border:1px solid #fde047; border-radius:12px; padding:16px; font-size:13px; color:#854d0e;">
        ⚠️ Keine Module in <code>modules/</code> gefunden. Prüfe ob die Modul-Ordner mit <code>module.json</code> vorhanden sind.
    </div>
    @endif

</div>
@endsection
