@extends('core::admin.layout')

@section('title', 'Nutzer')

@section('content')

<div>
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <div>
            <h1 style="font-size:22px; font-weight:800; color:#1e293b;">Nutzer</h1>
            <p style="font-size:13px; color:#94a3b8; margin-top:2px;">{{ $users->total() }} Nutzer registriert</p>
        </div>
        <button onclick="openUserModal('create')"
                style="background:#0a1628; color:white; border:none; border-radius:8px; padding:9px 18px; font-size:14px; font-weight:600; cursor:pointer;">
            + Nutzer anlegen
        </button>
    </div>

    {{-- Tabelle --}}
    <div style="background:white; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse;">
            <thead style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                <tr>
                    <th style="text-align:left; padding:12px 16px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Nutzer</th>
                    <th style="text-align:left; padding:12px 16px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Rolle</th>
                    <th style="text-align:left; padding:12px 16px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Erstellt</th>
                    <th style="padding:12px 16px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 16px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:36px; height:36px; border-radius:50%; background:#0a1628; color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0;">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-size:14px; font-weight:600; color:#1e293b;">{{ $user->name }}</div>
                                <div style="font-size:12px; color:#94a3b8;">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 16px;">
                        @foreach($user->roles as $role)
                            <span style="background:#dbeafe; color:#1d4ed8; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; margin-right:4px;">{{ $role->name }}</span>
                        @endforeach
                        @if($user->roles->isEmpty() && $user->permissions->isNotEmpty())
                            <span style="background:#f3e8ff; color:#7e22ce; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px;">Benutzerdefiniert</span>
                        @endif
                        @if($user->roles->isEmpty() && $user->permissions->isEmpty())
                            <span style="color:#94a3b8; font-size:12px;">Keine Rechte</span>
                        @endif
                    </td>
                    <td style="padding:14px 16px; font-size:13px; color:#64748b;">{{ $user->created_at->format('d.m.Y') }}</td>
                    <td style="padding:14px 16px; text-align:right;">
                        <button onclick="openUserModal('edit', {{ $user->id }})"
                                style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; color:#475569; margin-right:6px;">
                            Bearbeiten
                        </button>
                        @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display:inline;"
                              onsubmit="return confirm('{{ $user->name }} wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:#fef2f2; border:1px solid #fca5a5; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; color:#991b1b;">
                                Löschen
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding:40px; text-align:center; color:#94a3b8; font-size:14px;">Keine Nutzer vorhanden.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($users->hasPages())
        <div style="padding:12px 16px; border-top:1px solid #f1f5f9;">{{ $users->links() }}</div>
        @endif
    </div>
</div>

{{-- ═══ MODAL ═══ --}}
<div id="userModal" onclick="closeUserModal(event)"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:500; align-items:center; justify-content:center; padding:20px;">
    <div onclick="event.stopPropagation()"
         style="background:white; border-radius:16px; width:90%; max-width:936px; max-height:90vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.3);">

        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid #e2e8f0;">
            <h2 id="modalTitle" style="font-size:18px; font-weight:800; color:#1e293b;">Nutzer</h2>
            <button onclick="closeUserModal()" style="background:none; border:none; cursor:pointer; font-size:24px; color:#94a3b8; line-height:1; padding:0;">×</button>
        </div>

        <div style="display:flex; border-bottom:2px solid #e2e8f0; padding:0 24px;">
            <button onclick="switchUserTab('login')" id="tabLoginBtn"
                    style="padding:12px 16px; font-size:13px; font-weight:600; cursor:pointer; background:none; border:none; color:#0a1628; border-bottom:2px solid #0a1628; margin-bottom:-2px; white-space:nowrap;">
                🔑 Login-Infos
            </button>
            <button onclick="switchUserTab('rights')" id="tabRightsBtn"
                    style="padding:12px 16px; font-size:13px; font-weight:600; cursor:pointer; background:none; border:none; color:#64748b; border-bottom:2px solid transparent; margin-bottom:-2px; white-space:nowrap;">
                🔒 Rechte &amp; Rollen
            </button>
        </div>

        <div style="overflow-y:auto; flex:1;">

            {{-- Tab 1: Login-Infos --}}
            <div id="tabLogin" style="padding:24px;">
                <form id="userLoginForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="loginFormMethod" value="PATCH">
                    <div style="display:grid; gap:16px;">
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Name *</label>
                            <input type="text" name="name" id="fieldName" required
                                   style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">E-Mail *</label>
                            <input type="email" name="email" id="fieldEmail" required
                                   style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                                Neues Passwort
                                <span id="passwordHint" style="font-weight:400; color:#94a3b8; text-transform:none;">(leer lassen = nicht ändern)</span>
                            </label>
                            <input type="password" name="password" id="fieldPassword"
                                   style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; box-sizing:border-box;">
                        </div>
                        <div id="passwordConfirmRow">
                            <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Passwort wiederholen</label>
                            <input type="password" name="password_confirmation"
                                   style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; box-sizing:border-box;">
                        </div>
                    </div>
                    <div style="margin-top:24px; display:flex; gap:10px;">
                        <button type="submit" style="background:#0a1628; color:white; border:none; border-radius:8px; padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer;">Speichern</button>
                        <button type="button" onclick="closeUserModal()" style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:10px 18px; font-size:14px; font-weight:600; cursor:pointer; color:#475569;">Abbrechen</button>
                    </div>
                </form>
            </div>

            {{-- Tab 2: Rechte & Rollen --}}
            <div id="tabRights" style="padding:24px; display:none;">
                <form id="userRightsForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="hidden" name="rights_only" value="1">

                    <div style="margin-bottom:20px;">
                        <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:12px;">System-Rolle</div>
                        <div style="display:flex; flex-direction:column; gap:8px;" id="roleRadios">
                            @foreach($roles as $role)
                            <label style="display:flex; align-items:flex-start; gap:10px; background:#f8fafc; border:2px solid #e2e8f0; border-radius:10px; padding:12px 14px; cursor:pointer;" id="roleLabel_{{ $role->name }}">
                                <input type="radio" name="role" value="{{ $role->name }}" id="role_{{ $role->name }}"
                                       style="margin-top:2px; accent-color:#1a6fc4; flex-shrink:0;" onchange="onRoleChange(this)">
                                <div>
                                    <div style="font-size:14px; font-weight:700; color:#1e293b;">{{ ucfirst($role->name) }}</div>
                                    <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                        @switch($role->name)
                                            @case('admin') Vollzugriff auf alle Module und Einstellungen @break
                                            @case('trainer') Spieltage, Training, Teams, Mitglieder @break
                                            @default {{ ucfirst($role->name) }}-Zugriff
                                        @endswitch
                                    </div>
                                </div>
                            </label>
                            @endforeach

                            <label style="display:flex; align-items:flex-start; gap:10px; background:#f8fafc; border:2px solid #e2e8f0; border-radius:10px; padding:12px 14px; cursor:pointer;" id="roleLabel_custom">
                                <input type="radio" name="role" value="custom" id="role_custom"
                                       style="margin-top:2px; accent-color:#7c3aed; flex-shrink:0;" onchange="onRoleChange(this)">
                                <div>
                                    <div style="font-size:14px; font-weight:700; color:#7c3aed;">Benutzerdefiniert</div>
                                    <div style="font-size:12px; color:#64748b; margin-top:2px;">Individuelle Rechte vergeben</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div id="customPermissions" style="display:none;">
                        <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:10px;">Individuelle Rechte</div>
                        @if($permissions->isEmpty())
                        <div style="background:#fef9c3; border:1px solid #fde047; border-radius:8px; padding:12px 14px; font-size:13px; color:#854d0e;">
                            Keine Permissions vorhanden. Module installieren um Permissions zu erhalten.
                        </div>
                        @else
                        @foreach($permissions->groupBy(fn($p) => explode(' ', $p->name, 2)[1] ?? 'Sonstiges') as $module => $perms)
                        <div style="margin-bottom:16px;">
                            <div style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:8px;">{{ ucfirst($module) }}</div>
                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:6px;">
                                @foreach($perms as $perm)
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:8px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;" id="permLabel_{{ $perm->id }}">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->name }}" id="perm_{{ $perm->id }}"
                                           style="accent-color:#7c3aed; flex-shrink:0;">
                                    <span style="font-size:13px; color:#1e293b;">{{ $perm->name }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                        @endif
                    </div>

                    <div style="margin-top:24px; display:flex; gap:10px;">
                        <button type="submit" style="background:#0a1628; color:white; border:none; border-radius:8px; padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer;">Rechte speichern</button>
                        <button type="button" onclick="closeUserModal()" style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:10px 18px; font-size:14px; font-weight:600; cursor:pointer; color:#475569;">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ═══ DATA BRIDGE: Server → JS (nur Daten, keine Logik) ═══ --}}
@push('scripts')
<script>
    window.CK_Users = {
        users: @json($usersJs),
        routes: {
            store:  "{{ route('admin.users.store') }}",
            update: "{{ url('admin/users') }}"
        }
    };
</script>
<script src="{{ asset('js/modules/users-modal.js') }}"></script>
@endpush

@endsection
