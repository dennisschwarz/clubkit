@extends('core::admin.layout')

@section('title', 'Nutzer')

@section('content')

{{-- ═══ SEITE ═══ --}}
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
                            <span style="background:#dbeafe; color:#1d4ed8; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; margin-right:4px;">
                                {{ $role->name }}
                            </span>
                        @endforeach
                        @if($user->roles->isEmpty() && $user->permissions->isNotEmpty())
                            <span style="background:#f3e8ff; color:#7e22ce; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px;">
                                Benutzerdefiniert
                            </span>
                        @endif
                        @if($user->roles->isEmpty() && $user->permissions->isEmpty())
                            <span style="color:#94a3b8; font-size:12px;">Keine Rechte</span>
                        @endif
                    </td>
                    <td style="padding:14px 16px; font-size:13px; color:#64748b;">
                        {{ $user->created_at->format('d.m.Y') }}
                    </td>
                    <td style="padding:14px 16px; text-align:right;">
                        <button onclick="openUserModal('edit', {{ $user->id }})"
                                style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; color:#475569; margin-right:6px;">
                            Bearbeiten
                        </button>
                        @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display:inline;"
                              onsubmit="return confirm('{{ $user->name }} wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="background:#fef2f2; border:1px solid #fca5a5; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; color:#991b1b;">
                                Löschen
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding:40px; text-align:center; color:#94a3b8; font-size:14px;">
                        Keine Nutzer vorhanden.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($users->hasPages())
        <div style="padding:12px 16px; border-top:1px solid #f1f5f9;">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>

{{-- ═══ MODAL ═══ --}}
<div id="userModal" onclick="closeUserModal(event)"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:500; align-items:center; justify-content:center; padding:20px;">

    <div onclick="event.stopPropagation()"
         style="background:white; border-radius:16px; width:90%; max-width:936px; max-height:90vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.3);">

        {{-- Modal Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid #e2e8f0;">
            <h2 id="modalTitle" style="font-size:18px; font-weight:800; color:#1e293b;">Nutzer bearbeiten</h2>
            <button onclick="closeUserModal()"
                    style="background:none; border:none; cursor:pointer; font-size:24px; color:#94a3b8; line-height:1; padding:0;">×</button>
        </div>

        {{-- Tab-Navigation --}}
        <div style="display:flex; border-bottom:2px solid #e2e8f0; padding:0 24px;">
            <button onclick="switchUserTab('login')" id="tabLoginBtn"
                    style="padding:12px 16px; font-size:13px; font-weight:600; cursor:pointer; background:none; border:none;
                           color:#0a1628; border-bottom:2px solid #0a1628; margin-bottom:-2px; white-space:nowrap;">
                🔑 Login-Infos
            </button>
            <button onclick="switchUserTab('rights')" id="tabRightsBtn"
                    style="padding:12px 16px; font-size:13px; font-weight:600; cursor:pointer; background:none; border:none;
                           color:#64748b; border-bottom:2px solid transparent; margin-bottom:-2px; white-space:nowrap;">
                🔒 Rechte &amp; Rollen
            </button>
        </div>

        {{-- Modal Body (scrollable) --}}
        <div style="overflow-y:auto; flex:1;">

            {{-- ── TAB 1: Login-Infos ── --}}
            <div id="tabLogin" style="padding:24px;">
                <form id="userLoginForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="loginFormMethod" value="PATCH">
                    <div style="display:grid; gap:16px;">

                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Name *</label>
                            <input type="text" name="name" id="fieldName"
                                   style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; box-sizing:border-box;" required>
                        </div>

                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">E-Mail *</label>
                            <input type="email" name="email" id="fieldEmail"
                                   style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; font-size:14px; box-sizing:border-box;" required>
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
                        <button type="submit"
                                style="background:#0a1628; color:white; border:none; border-radius:8px; padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer;">
                            Speichern
                        </button>
                        <button type="button" onclick="closeUserModal()"
                                style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:10px 18px; font-size:14px; font-weight:600; cursor:pointer; color:#475569;">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>

            {{-- ── TAB 2: Rechte & Rollen ── --}}
            <div id="tabRights" style="padding:24px; display:none;">
                <form id="userRightsForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="hidden" name="rights_only" value="1">

                    {{-- Rollen-Auswahl --}}
                    <div style="margin-bottom:20px;">
                        <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:12px;">System-Rolle</div>

                        <div style="display:flex; flex-direction:column; gap:8px;" id="roleRadios">
                            @foreach($roles as $role)
                            <label style="display:flex; align-items:flex-start; gap:10px; background:#f8fafc; border:2px solid #e2e8f0; border-radius:10px; padding:12px 14px; cursor:pointer;"
                                   id="roleLabel_{{ $role->id }}">
                                <input type="radio" name="role" value="{{ $role->name }}" id="role_{{ $role->id }}"
                                       style="margin-top:2px; accent-color:#1a6fc4; flex-shrink:0;"
                                       onchange="onRoleChange(this)">
                                <div>
                                    <div style="font-size:14px; font-weight:700; color:#1e293b;">{{ ucfirst($role->name) }}</div>
                                    <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                        @switch($role->name)
                                            @case('admin') Vollzugriff auf alle Module und Einstellungen @break
                                            @case('trainer') Spieltage, Training, Teams, Mitglieder @break
                                            @case('elternvertreter') Organisation, eingeschränkter Zugriff @break
                                            @default {{ ucfirst($role->name) }}-Zugriff
                                        @endswitch
                                    </div>
                                </div>
                            </label>
                            @endforeach

                            {{-- Benutzerdefiniert --}}
                            <label style="display:flex; align-items:flex-start; gap:10px; background:#f8fafc; border:2px solid #e2e8f0; border-radius:10px; padding:12px 14px; cursor:pointer;"
                                   id="roleLabel_custom">
                                <input type="radio" name="role" value="custom" id="role_custom"
                                       style="margin-top:2px; accent-color:#7c3aed; flex-shrink:0;"
                                       onchange="onRoleChange(this)">
                                <div>
                                    <div style="font-size:14px; font-weight:700; color:#7c3aed;">Benutzerdefiniert</div>
                                    <div style="font-size:12px; color:#64748b; margin-top:2px;">Individuelle Rechte vergeben</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Benutzerdefinierte Permissions (nur sichtbar wenn "Benutzerdefiniert" gewählt) --}}
                    <div id="customPermissions" style="display:none; margin-top:4px;">
                        <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:10px;">Individuelle Rechte</div>
                        @if($permissions->isEmpty())
                        <div style="background:#fef9c3; border:1px solid #fde047; border-radius:8px; padding:12px 14px; font-size:13px; color:#854d0e;">
                            Keine Permissions in der Datenbank. Module installieren um Permissions zu erhalten.
                        </div>
                        @else
                        @foreach($permissions->groupBy(fn($p) => explode(' ', $p->name)[1] ?? 'Sonstiges') as $module => $perms)
                        <div style="margin-bottom:16px;">
                            <div style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:8px;">{{ ucfirst($module) }}</div>
                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:6px;">
                                @foreach($perms as $perm)
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:8px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;"
                                       id="permLabel_{{ $perm->id }}">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
                                           id="perm_{{ $perm->id }}"
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
                        <button type="submit"
                                style="background:#0a1628; color:white; border:none; border-radius:8px; padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer;">
                            Rechte speichern
                        </button>
                        <button type="button" onclick="closeUserModal()"
                                style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:10px 18px; font-size:14px; font-weight:600; cursor:pointer; color:#475569;">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

{{-- Daten für JS --}}
<script>
const USERS_DATA = @json($users->keyBy('id')->map(fn($u) => [
    'id'          => $u->id,
    'name'        => $u->name,
    'email'       => $u->email,
    'roles'       => $u->roles->pluck('name')->toArray(),
    'permissions' => $u->permissions->pluck('name')->toArray(),
]));

const ROUTE_STORE  = "{{ route('admin.users.store') }}";
const ROUTE_UPDATE = (id) => `/admin/users/${id}`;

let currentUserId = null;

function openUserModal(mode, userId = null) {
    currentUserId = userId;
    const modal = document.getElementById('userModal');
    const title = document.getElementById('modalTitle');

    if (mode === 'create') {
        title.textContent = 'Neuen Nutzer anlegen';
        document.getElementById('fieldName').value     = '';
        document.getElementById('fieldEmail').value    = '';
        document.getElementById('fieldPassword').value = '';
        document.getElementById('passwordHint').style.display = 'none';
        document.getElementById('loginFormMethod').value = 'POST';
        document.getElementById('userLoginForm').action  = ROUTE_STORE;
        document.getElementById('userRightsForm').action = ROUTE_STORE;
        document.querySelector('#fieldPassword').required = true;
    } else {
        const u = USERS_DATA[userId];
        title.textContent = `${u.name} bearbeiten`;
        document.getElementById('fieldName').value  = u.name;
        document.getElementById('fieldEmail').value = u.email;
        document.getElementById('fieldPassword').value = '';
        document.getElementById('passwordHint').style.display = '';
        document.getElementById('loginFormMethod').value  = 'PATCH';
        document.getElementById('userLoginForm').action   = ROUTE_UPDATE(userId);
        document.getElementById('userRightsForm').action  = ROUTE_UPDATE(userId);
        document.querySelector('#fieldPassword').required = false;

        // Vorauswahl Rollen
        document.querySelectorAll('input[name="role"]').forEach(r => r.checked = false);
        document.querySelectorAll('input[name="permissions[]"]').forEach(p => p.checked = false);
        document.getElementById('customPermissions').style.display = 'none';

        if (u.roles.length > 0) {
            const r = document.getElementById('role_' + u.roles[0]);
            if (r) { r.checked = true; highlightRole(u.roles[0]); }
        } else if (u.permissions.length > 0) {
            const custom = document.getElementById('role_custom');
            if (custom) { custom.checked = true; highlightRole('custom'); }
            document.getElementById('customPermissions').style.display = '';
            u.permissions.forEach(pName => {
                document.querySelectorAll('input[name="permissions[]"]').forEach(cb => {
                    if (cb.value === pName) cb.checked = true;
                });
            });
        }
    }

    switchUserTab('login');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeUserModal(e) {
    if (e && e.target !== document.getElementById('userModal')) return;
    document.getElementById('userModal').style.display = 'none';
    document.body.style.overflow = '';
}

function switchUserTab(tab) {
    const loginTab   = document.getElementById('tabLogin');
    const rightsTab  = document.getElementById('tabRights');
    const loginBtn   = document.getElementById('tabLoginBtn');
    const rightsBtn  = document.getElementById('tabRightsBtn');

    if (tab === 'login') {
        loginTab.style.display  = '';
        rightsTab.style.display = 'none';
        loginBtn.style.color        = '#0a1628';
        loginBtn.style.borderBottom = '2px solid #0a1628';
        rightsBtn.style.color        = '#64748b';
        rightsBtn.style.borderBottom = '2px solid transparent';
    } else {
        loginTab.style.display  = 'none';
        rightsTab.style.display = '';
        loginBtn.style.color        = '#64748b';
        loginBtn.style.borderBottom = '2px solid transparent';
        rightsBtn.style.color        = '#0a1628';
        rightsBtn.style.borderBottom = '2px solid #0a1628';
    }
}

function onRoleChange(input) {
    highlightRole(input.value);
    document.getElementById('customPermissions').style.display =
        input.value === 'custom' ? '' : 'none';
}

function highlightRole(value) {
    document.querySelectorAll('[id^="roleLabel_"]').forEach(el => {
        el.style.borderColor     = '#e2e8f0';
        el.style.backgroundColor = '#f8fafc';
    });
    const target = document.getElementById('roleLabel_' + value);
    if (target) {
        target.style.borderColor     = value === 'custom' ? '#7c3aed' : '#1a6fc4';
        target.style.backgroundColor = value === 'custom' ? '#faf5ff' : '#eff6ff';
    }
}

// ESC-Taste
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('userModal').style.display = 'none';
        document.body.style.overflow = '';
    }
});
</script>

@endsection
