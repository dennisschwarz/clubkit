@extends('core::admin.layout')
@section('title', 'Vereinskasse')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">💰 Vereinskasse</h1>
        <p class="ck-page-subtitle">Buchungen, Konten und Beitragsverwaltung</p>
    </div>
</div>

@if(session('success'))
    <div class="ck-alert ck-alert--success ck-mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="ck-alert ck-alert--danger ck-mb-4">{{ session('error') }}</div>
@endif

{{-- ── Local sub-tabs ────────────────────────────────────────────────────── --}}
<div class="ck-local-tabs ck-mb-5">
    <button class="ck-local-tab ck-local-tab--blue {{ request('tab', 'zusammenfassung') === 'zusammenfassung' ? 'ck-local-tab--active' : '' }}"
            onclick="ckLocalTab('treasuryTab-zusammenfassung', this)">
        📊 Zusammenfassung
    </button>
    <button class="ck-local-tab ck-local-tab--blue {{ request('tab') === 'buchungen' ? 'ck-local-tab--active' : '' }}"
            onclick="ckLocalTab('treasuryTab-buchungen', this)">
        📒 Buchungen
    </button>
    @can('treasury.accounts.manage')
    <button class="ck-local-tab ck-local-tab--purple {{ request('tab') === 'konten' ? 'ck-local-tab--active' : '' }}"
            onclick="ckLocalTab('treasuryTab-konten', this)">
        🏦 Konten
    </button>
    @endcan
    @can('treasury.contributions.manage')
    <button class="ck-local-tab ck-local-tab--green {{ request('tab') === 'beitraege' ? 'ck-local-tab--active' : '' }}"
            onclick="ckLocalTab('treasuryTab-beitraege', this)">
        📋 Beiträge
    </button>
    @endcan
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     TAB: Summary (zusammenfassung)
══════════════════════════════════════════════════════════════════════════ --}}
<div id="treasuryTab-zusammenfassung"
     class="ck-local-section {{ request('tab', 'zusammenfassung') === 'zusammenfassung' ? 'ck-local-section--active' : '' }}">

    {{-- Account selector --}}
    @if($visibleAccounts->count() > 1)
    <div class="ck-summary-selector ck-mb-5">
        <label class="ck-field-label" for="summaryAccountDropdown">Kasse auswählen</label>
        <select id="summaryAccountDropdown"
                class="ck-field-input ck-summary-selector__select"
                onchange="treasurySummaryFilter(this.value)">
            <option value="">Alle Konten</option>
            @foreach($visibleAccounts->sortBy('name') as $a)
            <option value="{{ $a->id }}">{{ $a->name }}</option>
            @endforeach
        </select>
    </div>
    @endif

    {{-- Stats cards --}}
    <div class="ck-stats-row ck-mb-5">
        <div class="ck-stat-card ck-stat-card--green">
            <div class="ck-stat-card__label">Einnahmen</div>
            <div class="ck-stat-card__value" id="summaryIncome">
                {{ number_format($totalIncome, 2, ',', '.') }} €
            </div>
        </div>
        <div class="ck-stat-card ck-stat-card--red">
            <div class="ck-stat-card__label">Ausgaben</div>
            <div class="ck-stat-card__value" id="summaryExpense">
                {{ number_format($totalExpense, 2, ',', '.') }} €
            </div>
        </div>
        <div id="summaryBalanceCard"
             class="ck-stat-card {{ $totalBalance >= 0 ? 'ck-stat-card--blue' : 'ck-stat-card--orange' }}">
            <div class="ck-stat-card__label">Saldo</div>
            <div class="ck-stat-card__value" id="summaryBalance">
                {{ number_format($totalBalance, 2, ',', '.') }} €
            </div>
        </div>
    </div>

    {{-- Recent transactions (no member names) --}}
    <x-ck-card>
        <x-slot:header>Letzte Buchungen</x-slot:header>
        <table class="ck-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Konto</th>
                    <th>Kategorie</th>
                    <th>Beschreibung</th>
                    <th class="ck-table__col--right">Betrag</th>
                </tr>
            </thead>
            <tbody id="summaryRecentTbody">
                @forelse($recentJs as $tx)
                <tr data-account-id="{{ $tx['account_id'] }}">
                    <td>{{ $tx['transaction_date'] }}</td>
                    <td>{{ $tx['account_name'] ?? '–' }}</td>
                    <td>
                        @if($tx['category_name'])
                            <x-ck-badge :color="$tx['category_color'] ?? 'gray'">{{ $tx['category_name'] }}</x-ck-badge>
                        @else
                            <span class="ck-muted">–</span>
                        @endif
                    </td>
                    <td>{{ $tx['description'] }}</td>
                    <td class="ck-table__col--right">
                        <span class="{{ $tx['type'] === 'income' ? 'ck-amount--income' : 'ck-amount--expense' }}">
                            {{ $tx['type'] === 'income' ? '+' : '−' }}
                            {{ number_format((float) $tx['amount'], 2, ',', '.') }} €
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="ck-empty-state">Noch keine Buchungen vorhanden.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-ck-card>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     TAB: Transactions (buchungen) – 50/50 split
══════════════════════════════════════════════════════════════════════════ --}}
<div id="treasuryTab-buchungen"
     class="ck-local-section {{ request('tab') === 'buchungen' ? 'ck-local-section--active' : '' }}">

    {{-- Filter bar + add transaction --}}
    {{--
        URL format: ?filter[account]=1&filter[category]=2&filter[date_from]=...
        The buchungen tab parameter is preserved as a plain ?tab=buchungen query string.
    --}}
    <div class="ck-buchungen-toolbar ck-mb-4">
        <form method="GET" action="{{ route('treasury.index') }}" class="ck-filter-bar">
            <input type="hidden" name="tab" value="buchungen">

            <x-ck-field name="filter[account]" type="select" :value="$filters['account']"
                :options="['' => 'Alle Konten'] + $visibleAccounts->pluck('name', 'id')->toArray()" />

            <x-ck-field name="filter[category]" type="select" :value="$filters['category']"
                :options="['' => 'Alle Kategorien'] + $categories->pluck('name', 'id')->toArray()" />

            <x-ck-field name="filter[date_from]" type="date" :value="$filters['date_from']" placeholder="Von" />
            <x-ck-field name="filter[date_to]"   type="date" :value="$filters['date_to']"   placeholder="Bis" />
            <x-ck-field name="filter[q]"         type="text" :value="$filters['q']"         placeholder="Suche…" />

            <x-ck-button type="submit" variant="secondary" size="sm">Filtern</x-ck-button>
            @if(array_filter($filters))
                <x-ck-button :href="route('treasury.index', ['tab' => 'buchungen'])" variant="secondary" size="sm">
                    Zurücksetzen
                </x-ck-button>
            @endif
        </form>

        @can('treasury.transactions.manage')
        <x-ck-button variant="success" onclick="treasuryOpen('transaction', 'create')">
            + Buchung erfassen
        </x-ck-button>
        @endcan
    </div>

    {{-- 50 / 50 Split --}}
    <div class="ck-buchungen-split">

        {{-- Left: Income (Einnahmen) --}}
        <div class="ck-buchungen-col ck-buchungen-col--income">
            <div class="ck-buchungen-col__header">
                <span>↑ Einnahmen</span>
                <span class="ck-amount--income">
                    {{ number_format($filteredIncomeSum, 2, ',', '.') }} €
                </span>
            </div>
            @if($incomeTransactions->isEmpty())
                <div class="ck-buchungen-col__empty">Keine Einnahmen gefunden.</div>
            @else
                <table class="ck-table ck-buchungen-col__table">
                    <thead>
                        <tr>
                            <x-ck-sort-header column="transaction_date" label="Datum" />
                            <th>Kategorie</th>
                            <x-ck-sort-header column="description" label="Beschreibung" />
                            <x-ck-sort-header column="amount" label="Betrag" justify="end" />
                            @can('treasury.transactions.manage')
                            <th class="ck-table__col--actions"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($incomeTransactions as $tx)
                        <tr>
                            <td>{{ $tx->transaction_date->format('d.m.Y') }}</td>
                            <td>
                                @if($tx->category)
                                    <x-ck-badge :color="$tx->category->color ?? 'gray'">{{ $tx->category->name }}</x-ck-badge>
                                @else
                                    <span class="ck-muted">–</span>
                                @endif
                            </td>
                            <td>{{ $tx->description }}</td>
                            <td class="ck-table__col--right">
                                <span class="ck-amount--income">
                                    +{{ number_format((float) $tx->amount, 2, ',', '.') }} €
                                </span>
                            </td>
                            @can('treasury.transactions.manage')
                            <td class="ck-table__col--actions">
                                <x-ck-button variant="secondary" size="sm"
                                    onclick="treasuryOpen('transaction', 'edit', {{ $tx->id }})">
                                    Bearbeiten
                                </x-ck-button>
                                <form method="POST" action="{{ route('treasury.transactions.destroy', $tx->id) }}">
                                    @csrf @method('DELETE')
                                    <x-ck-button type="submit" variant="danger" size="sm"
                                        :confirm="'Buchung »' . $tx->description . '« wirklich löschen?'">
                                        Löschen
                                    </x-ck-button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($incomeTransactions->count() >= 50)
                    <p class="ck-buchungen-col__more">
                        Mehr als 50 Einnahmen – Filter verwenden um einzugrenzen.
                    </p>
                @endif
            @endif
        </div>

        {{-- Right: Expenses (Ausgaben) --}}
        <div class="ck-buchungen-col ck-buchungen-col--expense">
            <div class="ck-buchungen-col__header">
                <span>↓ Ausgaben</span>
                <span class="ck-amount--expense">
                    {{ number_format($filteredExpenseSum, 2, ',', '.') }} €
                </span>
            </div>
            @if($expenseTransactions->isEmpty())
                <div class="ck-buchungen-col__empty">Keine Ausgaben gefunden.</div>
            @else
                <table class="ck-table ck-buchungen-col__table">
                    <thead>
                        <tr>
                            <x-ck-sort-header column="transaction_date" label="Datum" />
                            <th>Kategorie</th>
                            <x-ck-sort-header column="description" label="Beschreibung" />
                            <x-ck-sort-header column="amount" label="Betrag" justify="end" />
                            @can('treasury.transactions.manage')
                            <th class="ck-table__col--actions"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expenseTransactions as $tx)
                        <tr>
                            <td>{{ $tx->transaction_date->format('d.m.Y') }}</td>
                            <td>
                                @if($tx->category)
                                    <x-ck-badge :color="$tx->category->color ?? 'gray'">{{ $tx->category->name }}</x-ck-badge>
                                @else
                                    <span class="ck-muted">–</span>
                                @endif
                            </td>
                            <td>{{ $tx->description }}</td>
                            <td class="ck-table__col--right">
                                <span class="ck-amount--expense">
                                    −{{ number_format((float) $tx->amount, 2, ',', '.') }} €
                                </span>
                            </td>
                            @can('treasury.transactions.manage')
                            <td class="ck-table__col--actions">
                                <x-ck-button variant="secondary" size="sm"
                                    onclick="treasuryOpen('transaction', 'edit', {{ $tx->id }})">
                                    Bearbeiten
                                </x-ck-button>
                                <form method="POST" action="{{ route('treasury.transactions.destroy', $tx->id) }}">
                                    @csrf @method('DELETE')
                                    <x-ck-button type="submit" variant="danger" size="sm"
                                        :confirm="'Buchung »' . $tx->description . '« wirklich löschen?'">
                                        Löschen
                                    </x-ck-button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($expenseTransactions->count() >= 50)
                    <p class="ck-buchungen-col__more">
                        Mehr als 50 Ausgaben – Filter verwenden um einzugrenzen.
                    </p>
                @endif
            @endif
        </div>

    </div>{{-- end .ck-buchungen-split --}}
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     TAB: Accounts (konten)
══════════════════════════════════════════════════════════════════════════ --}}
@can('treasury.accounts.manage')
<div id="treasuryTab-konten"
     class="ck-local-section {{ request('tab') === 'konten' ? 'ck-local-section--active' : '' }}">

    <div class="ck-row ck-row--between ck-mb-4">
        <div></div>
        <x-ck-button variant="success" onclick="treasuryOpen('account', 'create')">
            + Neues Konto
        </x-ck-button>
    </div>

    <x-ck-card>
        @if($visibleAccounts->isEmpty())
            <p class="ck-empty-state">Noch keine Konten angelegt.</p>
        @else
            <table class="ck-table">
                <thead>
                    <tr>
                        <th>Konto</th>
                        <th>Typ</th>
                        <th>Sichtbarkeit</th>
                        <th class="ck-table__col--right">Saldo</th>
                        <th class="ck-table__col--actions">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visibleAccounts->sortBy('name') as $account)
                    <tr>
                        <td>
                            @if($account->parent_id)
                                <span class="ck-muted">↳ </span>
                            @endif
                            <strong>{{ $account->name }}</strong>
                            @if($account->description)
                                <small class="ck-muted ck-block">{{ $account->description }}</small>
                            @endif
                        </td>
                        <td>
                            @if($account->parent_id)
                                <x-ck-badge color="gray">Unterkonto</x-ck-badge>
                            @else
                                <x-ck-badge color="blue">Hauptkonto</x-ck-badge>
                            @endif
                        </td>
                        <td>
                            @if($account->visibility === 'public')
                                <x-ck-badge color="green">Öffentlich</x-ck-badge>
                            @else
                                <x-ck-badge color="orange">Team-intern</x-ck-badge>
                            @endif
                        </td>
                        <td class="ck-table__col--right">
                            @php $bal = $accountStats[$account->id]['balance'] ?? 0; @endphp
                            <span class="{{ $bal >= 0 ? 'ck-amount--income' : 'ck-amount--expense' }}">
                                {{ number_format($bal, 2, ',', '.') }} €
                            </span>
                        </td>
                        <td class="ck-table__col--actions">
                            <x-ck-button variant="secondary" size="sm"
                                onclick="treasuryOpen('account', 'edit', {{ $account->id }})">
                                Bearbeiten
                            </x-ck-button>
                            <form method="POST" action="{{ route('treasury.accounts.destroy', $account->id) }}">
                                @csrf @method('DELETE')
                                <x-ck-button type="submit" variant="danger" size="sm"
                                    :confirm="'Konto »' . $account->name . '« wirklich löschen?'">
                                    Löschen
                                </x-ck-button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ck-card>
</div>
@endcan

{{-- ══════════════════════════════════════════════════════════════════════════
     TAB: Contributions (beitraege)
══════════════════════════════════════════════════════════════════════════ --}}
@can('treasury.contributions.manage')
<div id="treasuryTab-beitraege"
     class="ck-local-section {{ request('tab') === 'beitraege' ? 'ck-local-section--active' : '' }}">

    <div class="ck-row ck-row--between ck-mb-4">
        <div></div>
        <x-ck-button variant="success" onclick="treasuryOpen('contribution', 'create')">
            + Aufgabe zuweisen
        </x-ck-button>
    </div>

    @if($taskMetas->isEmpty())
        <x-ck-card>
            <p class="ck-empty-state">
                Keine Beitragsaufgaben vorhanden.
                Weise eine Aufgabe einer Kasse zu, um die Beitragsverwaltung zu nutzen.
            </p>
        </x-ck-card>
    @else
        @foreach($taskMetas as $meta)
        <x-ck-card class="ck-mb-4">
            <x-slot:header>
                <div class="ck-row ck-row--between">
                    <div class="ck-row ck-row--gap">
                        <strong>{{ $meta->task?->name }}</strong>
                        <span class="ck-muted">→ {{ $meta->account?->name }}</span>
                        @if($meta->due_date)
                            <x-ck-badge color="gray">Fällig: {{ $meta->due_date->format('d.m.Y') }}</x-ck-badge>
                        @endif
                    </div>
                    <div class="ck-row">
                        <x-ck-button variant="secondary" size="sm"
                            onclick="treasuryOpen('paymentMember', 'create', {{ $meta->id }})">
                            + Mitglied
                        </x-ck-button>
                        <form method="POST" action="{{ route('treasury.contributions.destroy', $meta->id) }}">
                            @csrf @method('DELETE')
                            <x-ck-button type="submit" variant="danger" size="sm"
                                :confirm="'Aufgabe »' . $meta->task?->name . '« aus der Kasse entfernen?'">
                                Entfernen
                            </x-ck-button>
                        </form>
                    </div>
                </div>
            </x-slot:header>

            @if($meta->payments->isEmpty())
                <p class="ck-empty-state ck-empty-state--small">Noch keine Mitglieder zugewiesen.</p>
            @else
                <table class="ck-table">
                    <thead>
                        <tr>
                            <th>Mitglied</th>
                            <th class="ck-table__col--right">Betrag</th>
                            <th>Status</th>
                            <th>Bezahlt am</th>
                            <th class="ck-table__col--actions">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($meta->payments as $payment)
                        <tr>
                            <td>{{ $payment->member?->full_name ?? '–' }}</td>
                            <td class="ck-table__col--right">
                                {{ number_format((float) $payment->amount, 2, ',', '.') }} €
                            </td>
                            <td>
                                @if($payment->isPaid())
                                    <x-ck-badge color="green">Bezahlt</x-ck-badge>
                                @else
                                    <x-ck-badge color="orange">Ausstehend</x-ck-badge>
                                @endif
                            </td>
                            <td>{{ $payment->paid_at ? $payment->paid_at->format('d.m.Y') : '–' }}</td>
                            <td class="ck-table__col--actions">
                                @if(! $payment->isPaid())
                                    <form method="POST" action="{{ route('treasury.contributions.payments.pay', [$meta->id, $payment->id]) }}">
                                        @csrf @method('PATCH')
                                        <x-ck-button type="submit" variant="success" size="sm">
                                            Als bezahlt markieren
                                        </x-ck-button>
                                    </form>
                                    <form method="POST" action="{{ route('treasury.contributions.payments.destroy', [$meta->id, $payment->id]) }}">
                                        @csrf @method('DELETE')
                                        <x-ck-button type="submit" variant="danger" size="sm"
                                            :confirm="'Mitglied aus der Liste entfernen?'">
                                            Entfernen
                                        </x-ck-button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('treasury.contributions.payments.unpay', [$meta->id, $payment->id]) }}">
                                        @csrf @method('PATCH')
                                        <x-ck-button type="submit" variant="secondary" size="sm"
                                            :confirm="'Zahlung zurücksetzen? Die dazugehörige Buchung wird gelöscht.'">
                                            Zurücksetzen
                                        </x-ck-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Progress bar: paid/total counts pre-computed by controller in $taskMetaStats. --}}
                @if(($taskMetaStats[$meta->id]['total'] ?? 0) > 0)
                <div class="ck-contribution-progress ck-mt-3">
                    <span class="ck-muted">
                        {{ $taskMetaStats[$meta->id]['paid'] }} / {{ $taskMetaStats[$meta->id]['total'] }} bezahlt
                    </span>
                    <div class="ck-progress-bar">
                        <div class="ck-progress-bar__fill"
                             data-width="{{ round($taskMetaStats[$meta->id]['paid'] / $taskMetaStats[$meta->id]['total'] * 100) }}">
                        </div>
                    </div>
                </div>
                @endif
            @endif
        </x-ck-card>
        @endforeach
    @endif
</div>
@endcan

{{-- ══════════════════════════════════════════════════════════════════════════
     Modals
══════════════════════════════════════════════════════════════════════════ --}}

{{-- Modal: Buchung erfassen / bearbeiten (2-spaltig) --}}
@can('treasury.transactions.manage')
<x-ck-modal id="treasuryTransactionModal" title="Buchung" size="md">
    <form id="treasuryTransactionForm" method="POST">
        @csrf
        <div id="treasuryTransactionMethodField"></div>

        {{-- Konto: volle Breite – wichtigster Kontextgeber --}}
        <x-ck-field label="Konto" name="account_id" type="select"
            :options="['' => '– Konto wählen –']" :required="true" />

        {{-- 2-spaltig: Typ | Betrag, Datum | Kategorie, Referenz | Mitglied --}}
        <div class="ck-form-grid ck-form-grid--2">
            <x-ck-field label="Typ" name="type" type="select"
                :options="['income' => '↑ Einnahme', 'expense' => '↓ Ausgabe']" :required="true" />
            <x-ck-field label="Betrag (€)" name="amount" type="number"
                placeholder="0.00" :required="true" />
            <x-ck-field label="Datum" name="transaction_date" type="date" :required="true" />
            <x-ck-field label="Kategorie" name="category_id" type="select"
                :options="['' => '– Keine Kategorie –']" />
            <x-ck-field label="Referenz-Nr." name="reference_number" type="text"
                placeholder="RE-12345 (optional)" />
            <x-ck-field label="Mitglied zuordnen" name="member_id" type="select"
                :options="['' => '– Kein Mitglied –']" />
        </div>

        {{-- Beschreibung: volle Breite --}}
        <x-ck-field label="Beschreibung" name="description" type="text"
            placeholder="Buchungstext" :required="true" />

        <div class="ck-form-actions">
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'treasuryTransactionModal')">Abbrechen</x-ck-button>
            <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
        </div>
    </form>
</x-ck-modal>
@endcan

{{-- Modal: Konto anlegen / bearbeiten --}}
@can('treasury.accounts.manage')
<x-ck-modal id="treasuryAccountModal" title="Konto" size="md">
    <form id="treasuryAccountForm" method="POST">
        @csrf
        <div id="treasuryAccountMethodField"></div>

        <x-ck-field label="Kontoname" name="name" type="text"
            placeholder="z.B. Jugendkasse" :required="true" />
        <x-ck-field label="Beschreibung" name="description" type="text" placeholder="Optional" />
        <x-ck-field label="Übergeordnetes Konto (Unterkonto von)" name="parent_id" type="select"
            :options="['' => '– Kein übergeordnetes Konto –']" />
        <x-ck-field label="Sichtbarkeit" name="visibility" type="select"
            :options="['public' => 'Öffentlich', 'team_restricted' => 'Nur für Teammitglieder']"
            :required="true" onchange="treasuryAccountVisibilityChange(this.value)" />

        {{-- Team-Zuweisung: via .ck-multiselect-list mit Checkboxes, per JS befüllt --}}
        <div id="treasuryAccountTeamSection" class="is-hidden">
            <div class="ck-field">
                <span class="ck-field__label">Team(s) zuweisen</span>
                <div class="ck-multiselect-list ck-multiselect-list--scrollable"
                     id="treasuryAccountTeamList">
                    {{-- via JS befüllt (populateTeamCheckboxes) --}}
                </div>
            </div>
        </div>

        <div class="ck-form-actions">
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'treasuryAccountModal')">Abbrechen</x-ck-button>
            <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
        </div>
    </form>
</x-ck-modal>
@endcan

{{-- Modal: Aufgabe einer Kasse zuweisen --}}
@can('treasury.contributions.manage')
<x-ck-modal id="treasuryContributionModal" title="Aufgabe der Kasse zuweisen" size="md">
    <form method="POST" action="{{ route('treasury.contributions.store') }}">
        @csrf

        <x-ck-field label="Aufgabe" name="task_id" type="select"
            :options="['' => '– Aufgabe wählen –']" :required="true" />
        <x-ck-field label="Kasse" name="account_id" type="select"
            :options="['' => '– Kasse wählen –']" :required="true" />

        <div class="ck-form-grid ck-form-grid--2">
            <x-ck-field label="Standard-Betrag (€)" name="default_amount" type="number"
                placeholder="0.00" />
            <x-ck-field label="Fälligkeitsdatum" name="due_date" type="date" />
        </div>

        <div class="ck-form-actions">
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'treasuryContributionModal')">Abbrechen</x-ck-button>
            <x-ck-button type="submit" variant="primary">Zuweisen</x-ck-button>
        </div>
    </form>
</x-ck-modal>

{{-- Modal: Mitglied zur Beitragsliste hinzufügen --}}
<x-ck-modal id="treasuryPaymentMemberModal" title="Mitglied hinzufügen" size="sm">
    <form id="treasuryPaymentMemberForm" method="POST">
        @csrf
        <x-ck-field label="Mitglied" name="member_id" type="select"
            :options="['' => '– Mitglied wählen –']" :required="true" />
        <x-ck-field label="Betrag (€)" name="amount" type="number" placeholder="0.00" :required="true" />
        <x-ck-field label="Notiz" name="notes" type="text" placeholder="Optional" />
        <div class="ck-form-actions">
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'treasuryPaymentMemberModal')">Abbrechen</x-ck-button>
            <x-ck-button type="submit" variant="primary">Hinzufügen</x-ck-button>
        </div>
    </form>
</x-ck-modal>
@endcan

@endsection

@push('scripts')
<script>
window.CK_Treasury = {
    accounts:       @json($accountsJs),
    categories:     @json($categoriesJs),
    transactions:   @json($transactionsJs),
    parentAccounts: @json($parentAccountsJs),
    teams:          @json($teamsJs),
    members:        @json($membersJs),
    recentTransactions: @json($recentJs),
    accountStats:   @json($accountStats),
    globalStats:    @json($globalStats),
    routes: {
        transactionStore:  "{{ route('treasury.transactions.store') }}",
        transactionUpdate: "{{ url('treasury/transactions') }}",
        accountStore:      "{{ route('treasury.accounts.store') }}",
        accountUpdate:     "{{ url('treasury/accounts') }}",
        paymentsStore:     "{{ url('treasury/contributions') }}",
    }
};
</script>
@vite(['resources/js/modules/treasury-modal.js'])
@endpush
