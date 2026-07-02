@extends('core::admin.layout')
@section('title', 'Nutzer')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Nutzer</h1>
        <p class="ck-page-subtitle">{{ $users->total() }} Nutzer registriert</p>
    </div>
    <x-ck-button variant="primary" onclick="usersModalOpen('create')">
        + Nutzer anlegen
    </x-ck-button>
</div>

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <x-ck-sort-header column="name"       label="Nutzer" />
                <th>Rolle</th>
                <x-ck-sort-header column="created_at" label="Erstellt" />
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
                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        <x-ck-button variant="warning" size="icon"
                            title="{{ __('Edit') }}"
                            onclick="usersModalOpen('edit', {{ $user->id }})">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                            </svg>
                        </x-ck-button>
                        @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="icon" type="submit"
                                title="{{ __('Delete user') }}"
                                :confirm="$user->name . ' wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.'">
                                <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </x-ck-button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="ck-empty-state">Keine Nutzer vorhanden.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($users->hasPages())
    <div class="ck-table__pagination">{{ $users->links() }}</div>
    @endif
</div>

{{-- ── Modal ──────────────────────────────────────────────────────────── --}}
<x-ck-modal id="userModal" title="Nutzer" size="lg">

    <x-slot:tabs>
        <button id="userTab-login-btn" class="ck-modal-tab ck-modal-tab--active"
                onclick="ckModalTab('userModal', 'userTab-login', this)">
            🔑 Login-Infos
        </button>
        <button id="userTab-rights-btn" class="ck-modal-tab"
                onclick="ckModalTab('userModal', 'userTab-rights', this)">
            🔒 Rechte &amp; Rollen
        </button>
    </x-slot:tabs>

    {{-- Tab 1: Login credentials ──────────────────────────────────────── --}}
    <div id="userTab-login" class="ck-modal__section ck-modal__section--active">
        <form id="userLoginForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="userLoginMethod" value="POST">
            <div class="ck-form-grid ck-form-grid--2">
                <x-ck-field label="Name"   name="name"  id="fieldName"  :required="true" />
                <x-ck-field label="E-Mail" name="email" id="fieldEmail" type="email" :required="true" />
                <x-ck-field label="Neues Passwort" name="password" type="password"
                    id="fieldPassword" hint="(leer lassen = nicht ändern)" />
                <x-ck-field label="Passwort wiederholen" name="password_confirmation" type="password" />
            </div>
            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">{{ __('Save') }}</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'userModal')">{{ __('Cancel') }}</x-ck-button>
            </div>
        </form>
    </div>

    {{-- Tab 2: Permissions & roles ─────────────────────────────────────── --}}
    <div id="userTab-rights" class="ck-modal__section">

        <div id="userRightsCreateHint" class="ck-alert ck-alert--warning is-hidden">
            Zuerst den Nutzer anlegen (Tab „Login-Infos"), dann Rechte vergeben.
        </div>

        <form id="userRightsForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="userRightsMethod" value="PATCH">
            <input type="hidden" name="rights_only" value="1">

            {{-- Role dropdown ── --}}
            <div class="ck-field ck-mt-2">
                <label class="ck-field__label" for="roleSelect">Rolle zuweisen</label>
                <select name="role" id="roleSelect" class="ck-field__input" onchange="usersRoleChanged(this.value)">
                    <option value="">– Keine Rolle –</option>
                    @foreach($roles as $role)
                    <option value="{{ $role->name }}">{{ ucfirst($role->name) }}
                        @if($role->name === 'super-admin') (Vollzugriff)@endif
                    </option>
                    @endforeach
                    <option value="custom">— Benutzerdefiniert —</option>
                </select>
            </div>

            {{-- Permission preview for selected role ── --}}
            <div id="rolePermPreview" class="ck-role-perm-preview is-hidden">
                <div class="ck-role-perm-preview__label">Zugriff dieser Rolle:</div>
                <div id="rolePermList" class="ck-role-perm-preview__list"></div>
            </div>

            {{-- Super-admin notice ── --}}
            <div id="superAdminHint" class="ck-alert ck-alert--warning ck-mt-3 is-hidden">
                🔐 Super-Admin umgeht alle Berechtigungsprüfungen. Nur für Systemadministratoren vergeben.
            </div>

            {{-- Custom permissions (only visible when role = 'custom') ── --}}
            <div id="customPermissions" class="is-hidden ck-mt-4">
                @if($permissions->isEmpty())
                <div class="ck-alert ck-alert--warning">
                    Keine Permissions vorhanden. Module installieren und <code>php artisan db:seed --class=RoleSeeder</code> ausführen.
                </div>
                @else
                @foreach($permsByModule as $module => $perms)
                <div class="ck-perm-group ck-mt-3">
                    <div class="ck-perm-group__title">{{ ucfirst($module) }}</div>
                    <div class="ck-perm-grid">
                        @foreach($perms as $permName)
                        <label class="ck-perm-item">
                            <input type="checkbox" name="permissions[]"
                                   value="{{ $permName }}"
                                   id="custPerm_{{ str_replace(['.', '-'], '_', $permName) }}">
                            <span>{{ $permName }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
                @endif
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">{{ __('Save permissions') }}</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'userModal')">{{ __('Cancel') }}</x-ck-button>
            </div>
        </form>
    </div>

</x-ck-modal>

@push('scripts')
<script>
    window.CK_Users = {
        users:  @json($usersJs),
        roles:  @json($rolesJs),
        routes: {
            store:  "{{ route('admin.users.store') }}",
            update: "{{ url('admin/users') }}"
        }
    };
</script>
@vite('resources/js/modules/users-modal.js')
@endpush

@endsection
