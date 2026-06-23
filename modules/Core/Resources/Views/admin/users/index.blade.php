@extends('core::admin.layout')
@section('title', 'Nutzer')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Nutzer</h1>
        <p class="ck-page-subtitle">{{ $users->total() }} Nutzer registriert</p>
    </div>
    {{-- FIX: usersModalOpen('create') – nicht ckModalOpen() direkt.
         ckModalOpen() setzt keine action/method → PATCH an aktuelle URL --}}
    <x-ck-button variant="primary" onclick="usersModalOpen('create')">
        + Nutzer anlegen
    </x-ck-button>
</div>

{{-- Tabelle --}}
<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>Nutzer</th>
                <th>Rolle</th>
                <th>Erstellt</th>
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td>
                    <div class="ck-row">
                        <div class="ck-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                        <div>
                            <div class="ck-table__bold">{{ $user->name }}</div>
                            <div class="ck-text-muted">{{ $user->email }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    @foreach($user->roles as $role)
                        <x-ck-badge color="blue">{{ $role->name }}</x-ck-badge>
                    @endforeach
                    @if($user->roles->isEmpty() && $user->permissions->isNotEmpty())
                        <x-ck-badge color="purple">Benutzerdefiniert</x-ck-badge>
                    @endif
                    @if($user->roles->isEmpty() && $user->permissions->isEmpty())
                        <span class="ck-text-muted">Keine Rechte</span>
                    @endif
                </td>
                <td class="ck-text-muted">{{ $user->created_at->format('d.m.Y') }}</td>
                <td>
                    <div class="ck-table__action-cell">
                        <x-ck-button variant="secondary" size="sm"
                            onclick="usersModalOpen('edit', {{ $user->id }})">
                            Bearbeiten
                        </x-ck-button>
                        @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="sm" type="submit"
                                :confirm="$user->name . ' wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.'">
                                Löschen
                            </x-ck-button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="ck-empty-state">
                    Keine Nutzer vorhanden.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($users->hasPages())
    <div class="ck-table__pagination">{{ $users->links() }}</div>
    @endif
</div>

{{-- Modal ──────────────────────────────────────────────────────────── --}}
<x-ck-modal id="userModal" title="Nutzer" size="lg">

    <x-slot:tabs>
        <button id="userTab-login-btn" class="ck-modal-tab ck-modal-tab--active"
                onclick="ckModalTab('userModal', 'userTab-login', this)">
            🔑 Login-Infos
        </button>
        {{-- Rights-Tab: wird in create-Modus deaktiviert (usersModalOpen setzt ck-modal-tab--disabled) --}}
        <button id="userTab-rights-btn" class="ck-modal-tab"
                onclick="ckModalTab('userModal', 'userTab-rights', this)">
            🔒 Rechte &amp; Rollen
        </button>
    </x-slot:tabs>

    {{-- Tab 1: Login-Infos --}}
    <div id="userTab-login" class="ck-modal__section ck-modal__section--active">
        <form id="userLoginForm" method="POST">
            @csrf
            {{-- Wird von usersModalOpen() auf 'POST' (create) oder 'PATCH' (edit) gesetzt --}}
            <input type="hidden" name="_method" id="userLoginMethod" value="POST">
            <div class="ck-form-grid ck-form-grid--2">
                <x-ck-field label="Name"   name="name"  id="fieldName"  :required="true" />
                <x-ck-field label="E-Mail" name="email" id="fieldEmail" type="email" :required="true" />
                <x-ck-field label="Neues Passwort" name="password" type="password"
                    id="fieldPassword" hint="(leer lassen = nicht ändern)" />
                <x-ck-field label="Passwort wiederholen" name="password_confirmation" type="password" />
            </div>
            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'userModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>

    {{-- Tab 2: Rechte & Rollen --}}
    <div id="userTab-rights" class="ck-modal__section">

        {{-- Hinweis in create-Modus (wird von JS eingeblendet) --}}
        <div id="userRightsCreateHint" class="ck-flash ck-flash--warning is-hidden">
            Zuerst den Nutzer anlegen (Tab „Login-Infos"), dann Rechte vergeben.
        </div>

        <form id="userRightsForm" method="POST">
            @csrf
            {{-- Wird von usersModalOpen() auf 'PATCH' (edit) gesetzt --}}
            <input type="hidden" name="_method" id="userRightsMethod" value="PATCH">
            <input type="hidden" name="rights_only" value="1">

            <div class="ck-section-label ck-mt-4">System-Rolle</div>

            <div class="ck-space-y-4 ck-mt-4">
                @foreach($roles as $role)
                <label class="ck-role-option" id="roleOption-{{ $role->name }}">
                    <input type="radio" name="role" value="{{ $role->name }}"
                           id="roleRadio-{{ $role->name }}" onchange="usersRoleChanged(this)">
                    <div>
                        <div class="ck-role-option__title">{{ ucfirst($role->name) }}</div>
                        <div class="ck-role-option__desc">
                            @switch($role->name)
                                @case('admin')   Vollzugriff auf alle Module und Einstellungen @break
                                @case('trainer') Spieltage, Training, Teams, Mitglieder (lesend/schreibend) @break
                                @default         {{ ucfirst($role->name) }}-Zugriff
                            @endswitch
                        </div>
                    </div>
                </label>
                @endforeach

                <label class="ck-role-option" id="roleOption-custom">
                    <input type="radio" name="role" value="custom"
                           id="roleRadio-custom" onchange="usersRoleChanged(this)">
                    <div>
                        <div class="ck-role-option__title ck-text-purple">Benutzerdefiniert</div>
                        <div class="ck-role-option__desc">Individuelle Rechte vergeben</div>
                    </div>
                </label>
            </div>

            <div id="customPermissions" class="is-hidden ck-mt-5">
                @if($permissions->isEmpty())
                <div class="ck-flash ck-flash--warning">
                    Keine Permissions vorhanden. Module installieren.
                </div>
                @else
                @php
                    $grouped = [];
                    foreach ($permissions as $perm) {
                        $parts  = explode(' ', $perm->name, 2);
                        $module = isset($parts[1]) ? $parts[1] : 'Sonstiges';
                        $grouped[$module][] = $perm;
                    }
                @endphp
                @foreach($grouped as $module => $perms)
                <div class="ck-perm-group">
                    <div class="ck-perm-group__title">{{ ucfirst($module) }}</div>
                    <div class="ck-perm-grid">
                        @foreach($perms as $perm)
                        <label class="ck-perm-item">
                            <input type="checkbox" name="permissions[]" value="{{ $perm->name }}">
                            {{ $perm->name }}
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
                @endif
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Rechte speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'userModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>

</x-ck-modal>

@push('scripts')
<script>
    window.CK_Users = {
        users:  @json($usersJs),
        routes: {
            store:  "{{ route('admin.users.store') }}",
            update: "{{ url('admin/users') }}"
        }
    };
</script>
<script src="{{ asset('js/modules/users-modal.js') }}"></script>
@endpush

@endsection
