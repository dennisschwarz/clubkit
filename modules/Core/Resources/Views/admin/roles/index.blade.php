@extends('core::admin.layout')
@section('title', 'Rollen & Rechte')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Rollen & Rechte</h1>
        <p class="ck-page-subtitle">{{ $roles->count() }} Rollen · {{ $permissions->count() }} Berechtigungen</p>
    </div>
    <x-ck-button variant="primary" onclick="rolesModalOpen('create')">
        + Neue Rolle
    </x-ck-button>
</div>

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>Rollenname</th>
                <th>Berechtigungen</th>
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($roles as $role)
            @php $isSystem = in_array($role->name, ['super-admin', 'admin', 'user']); @endphp
            <tr>
                <td>
                    <span class="ck-table__bold">{{ $role->name }}</span>
                    @if($isSystem)
                        <x-ck-badge color="gray">System</x-ck-badge>
                    @endif
                    @if($role->name === 'super-admin')
                        <x-ck-badge color="red">Vollzugriff</x-ck-badge>
                    @endif
                </td>
                <td>
                    @if($role->name === 'super-admin')
                        <span class="ck-text-muted">Alle (via Gate::before Bypass)</span>
                    @elseif($role->permissions->isEmpty())
                        <span class="ck-text-muted">Keine</span>
                    @else
                        <x-ck-badge color="blue">{{ $role->permissions->count() }} Rechte</x-ck-badge>
                    @endif
                </td>
                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        <x-ck-button variant="warning" size="icon"
                            title="Rolle bearbeiten"
                            onclick="rolesModalOpen('edit', {{ $role->id }})">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                            </svg>
                        </x-ck-button>
                        @if(!$isSystem)
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="icon" type="submit"
                                title="Rolle löschen"
                                :confirm="'Rolle »' . $role->name . '« wirklich löschen?'">
                                <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </x-ck-button>
                        </form>
                        @else
                        {{-- Platzhalter damit Spaltenbreite gleich bleibt --}}
                        <div style="width:34px"></div>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="ck-empty-state">
                    Noch keine Rollen angelegt.
                    <a href="javascript:void(0)" onclick="rolesModalOpen('create')">Jetzt anlegen</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ── Verfügbare Permissions (Referenz) ── --}}
<x-ck-card class="ck-mt-6">
    <x-slot:header>Alle verfügbaren Berechtigungen ({{ $permissions->count() }})</x-slot:header>
    <div class="ck-permissions-grid">
        @foreach($permsByModule as $module => $perms)
        <div class="ck-permissions-group">
            <div class="ck-section-header">
                <div class="ck-section-header__icon ck-section-header__icon--blue">🔑</div>
                <span class="ck-section-header__title">{{ ucfirst($module) }}</span>
            </div>
            @foreach($perms as $p)
            <div class="ck-permission-item">
                <code class="ck-permission-item__name">{{ $p->name }}</code>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>
</x-ck-card>

{{-- ── Modal: Rolle anlegen / bearbeiten ── --}}
<x-ck-modal id="roleModal" title="Rolle" size="md">
    <div class="ck-modal__section ck-modal__section--active">
        <form id="roleForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="roleFormMethod" value="POST">

            <div id="roleNameField">
                <x-ck-field label="Rollenname" name="name" id="roleFieldName" :required="true"
                            placeholder="z.B. Trainer, Kassenwart" />
            </div>

            <div class="ck-field ck-mt-4">
                <span class="ck-field__label">Berechtigungen</span>
                @foreach($permsByModule as $module => $perms)
                <div class="ck-permissions-module-group ck-mt-3">
                    <div class="ck-permissions-module-label">{{ ucfirst($module) }}</div>
                    <div class="ck-permissions-checkboxes">
                        @foreach($perms as $p)
                        <label class="ck-perm-check">
                            <input type="checkbox" name="permissions[]"
                                   value="{{ $p->name }}"
                                   class="ck-perm-check__input"
                                   id="perm_{{ str_replace('.', '_', $p->name) }}">
                            <span class="ck-perm-check__label">{{ $p->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'roleModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>
</x-ck-modal>

@push('scripts')
<script>
    window.CK_Roles = {
        roles:  @json($rolesJs),
        routes: {
            store:  "{{ route('admin.roles.store') }}",
            update: "{{ url('admin/roles') }}"
        },
        systemRoles: ['super-admin', 'admin', 'user']
    };
</script>
<script src="{{ asset('js/modules/roles-modal.js') }}"></script>
@endpush

@endsection
