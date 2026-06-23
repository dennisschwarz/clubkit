@extends('core::admin.layout')

@section('title', 'Mitglieder')

@section('content')

<div>
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <div>
            <h1 style="font-size:22px; font-weight:800; color:#1e293b;">Mitglieder</h1>
            <p style="font-size:13px; color:#94a3b8; margin-top:2px;">{{ $members->total() }} Mitglieder gesamt</p>
        </div>
        <button onclick="openMemberModal('create')"
                style="background:#0a1628; color:white; border:none; border-radius:8px; padding:9px 18px; font-size:14px; font-weight:600; cursor:pointer;">
            + Mitglied hinzufügen
        </button>
    </div>

    {{-- Suche & Filter --}}
    <form method="GET" style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Name suchen…"
               style="flex:1; min-width:200px; border:1px solid #e2e8f0; border-radius:8px; padding:9px 14px; font-size:14px;">
        <select name="status" style="border:1px solid #e2e8f0; border-radius:8px; padding:9px 14px; font-size:14px; background:white; cursor:pointer;">
            <option value="">Alle Status</option>
            <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Aktiv</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inaktiv</option>
        </select>
        <button type="submit" style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:9px 16px; font-size:14px; font-weight:600; cursor:pointer; color:#475569;">
            Suchen
        </button>
        @if(request('q') || request('status'))
        <a href="{{ route('members.index') }}" style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:9px 16px; font-size:14px; color:#94a3b8; text-decoration:none;">
            Zurücksetzen
        </a>
        @endif
    </form>

    {{-- Tabelle --}}
    <div style="background:white; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse;">
            <thead style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                <tr>
                    <th style="text-align:left; padding:12px 16px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Name</th>
                    <th style="text-align:left; padding:12px 16px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Geb. / Alter</th>
                    <th style="text-align:left; padding:12px 16px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Geschlecht</th>
                    <th style="text-align:left; padding:12px 16px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Spielberechtigt</th>
                    <th style="text-align:left; padding:12px 16px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Status</th>
                    <th style="padding:12px 16px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $member)
                <tr style="border-bottom:1px solid #f1f5f9; cursor:pointer;" onclick="openMemberModal('edit', {{ $member->id }})">
                    <td style="padding:14px 16px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:34px; height:34px; border-radius:50%; background:#0a1628; color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0;">
                                {{ strtoupper(substr($member->last_name, 0, 1)) }}
                            </div>
                            <div style="font-size:14px; font-weight:600; color:#1e293b;">{{ $member->last_name }}, {{ $member->first_name }}</div>
                        </div>
                    </td>
                    <td style="padding:14px 16px; font-size:13px; color:#64748b;">
                        {{ $member->date_of_birth?->format('d.m.Y') ?? '–' }}
                        {{ $member->age ? '(' . $member->age . ')' : '' }}
                    </td>
                    <td style="padding:14px 16px; font-size:13px; color:#64748b;">
                        @switch($member->gender)
                            @case('male')    Männlich @break
                            @case('female')  Weiblich @break
                            @case('diverse') Divers   @break
                            @default –
                        @endswitch
                    </td>
                    <td style="padding:14px 16px;">
                        @if($member->eligible_to_play)
                            <span style="background:#dcfce7; color:#166534; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px;">✓ Ja</span>
                        @else
                            <span style="background:#f1f5f9; color:#64748b; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px;">Nein</span>
                        @endif
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="background:{{ $member->status === 'active' ? '#dcfce7' : '#f1f5f9' }}; color:{{ $member->status === 'active' ? '#166534' : '#64748b' }}; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px;">
                            {{ $member->status === 'active' ? 'Aktiv' : 'Inaktiv' }}
                        </span>
                    </td>
                    <td style="padding:14px 16px; text-align:right;" onclick="event.stopPropagation()">
                        <form method="POST" action="{{ route('members.destroy', $member) }}" style="display:inline;"
                              onsubmit="return confirm('{{ $member->last_name }}, {{ $member->first_name }} wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:#fef2f2; border:1px solid #fca5a5; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; color:#991b1b;">
                                Löschen
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding:40px; text-align:center; color:#94a3b8; font-size:14px;">
                        Keine Mitglieder gefunden.
                        <a href="javascript:void(0)" onclick="openMemberModal('create')" style="color:#1a6fc4; text-decoration:none; margin-left:6px;">Jetzt anlegen</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($members->hasPages())
        <div style="padding:12px 16px; border-top:1px solid #f1f5f9;">
            {{ $members->links() }}
        </div>
        @endif
    </div>
</div>

{{-- ═══ MODAL (nur HTML, kein JS) ═══ --}}
<div id="memberModal" onclick="closeMemberModal(event)"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:500; align-items:center; justify-content:center; padding:20px;">
    <div onclick="event.stopPropagation()"
         style="background:white; border-radius:16px; width:90%; max-width:936px; max-height:90vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.3);">

        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid #e2e8f0;">
            <h2 id="memberModalTitle" style="font-size:18px; font-weight:800; color:#1e293b;">Mitglied</h2>
            <button onclick="closeMemberModal()" style="background:none; border:none; cursor:pointer; font-size:24px; color:#94a3b8; line-height:1; padding:0;">×</button>
        </div>

        <div style="display:flex; border-bottom:2px solid #e2e8f0; padding:0 24px;">
            <button style="padding:12px 16px; font-size:13px; font-weight:600; cursor:pointer; background:none; border:none; color:#0a1628; border-bottom:2px solid #0a1628; margin-bottom:-2px;">
                👤 Stammdaten
            </button>
        </div>

        <div style="overflow-y:auto; flex:1; padding:24px;">
            <form id="memberForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="memberFormMethod" value="PATCH">

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Vorname *</label>
                        <input type="text" name="first_name" id="mFieldFirstName" required
                               style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Nachname *</label>
                        <input type="text" name="last_name" id="mFieldLastName" required
                               style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Geschlecht</label>
                        <select name="gender" id="mFieldGender"
                                style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; background:white; cursor:pointer; box-sizing:border-box;">
                            <option value="">– nicht angegeben –</option>
                            <option value="male">Männlich</option>
                            <option value="female">Weiblich</option>
                            <option value="diverse">Divers</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Geburtsdatum</label>
                        <input type="date" name="date_of_birth" id="mFieldDob"
                               max="{{ now()->subDay()->format('Y-m-d') }}"
                               style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Status</label>
                        <select name="status" id="mFieldStatus"
                                style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; background:white; cursor:pointer; box-sizing:border-box;">
                            <option value="active">Aktiv</option>
                            <option value="inactive">Inaktiv</option>
                        </select>
                    </div>
                    <div style="display:flex; align-items:center; padding-top:28px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; width:100%; box-sizing:border-box;">
                            <input type="checkbox" name="eligible_to_play" value="1" id="mFieldEligible"
                                   style="width:18px; height:18px; accent-color:#166534; flex-shrink:0; cursor:pointer;">
                            <span style="font-size:14px; font-weight:600; color:#1e293b;">Spielberechtigt</span>
                        </label>
                    </div>
                </div>

                <div style="margin-top:24px; display:flex; gap:10px;">
                    <button type="submit" style="background:#0a1628; color:white; border:none; border-radius:8px; padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer;">
                        Speichern
                    </button>
                    <button type="button" onclick="closeMemberModal()" style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:10px 18px; font-size:14px; font-weight:600; cursor:pointer; color:#475569;">
                        Abbrechen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══ DATA BRIDGE: Server → JS (nur Daten, keine Logik) ═══ --}}
@push('scripts')
<script>
    window.CK_Members = {
        members: @json($membersJs),
        routes: {
            store:  "{{ route('members.store') }}",
            update: "{{ url('members') }}"
        }
    };
</script>
<script src="{{ asset('js/modules/members-modal.js') }}"></script>
@endpush

@endsection
