{{--
    Shared Partial: Eigene Felder – Formular-Block im Modal-Tab

    Erwartet folgende PHP-Variablen (werden vom jeweiligen Wrapper-View gesetzt):
      $cfObjectType  (string) – 'member' | 'team' | 'event'
      $cfModalId     (string) – z.B. 'memberModal'
      $cfTabId       (string) – z.B. 'memberTab-cf'
      $cfFormId      (string) – z.B. 'memberCfForm'
      $cfHintId      (string) – z.B. 'memberCfCreateHint'
      $cfFieldPrefix (string) – z.B. 'mCf_'
      $cfDefs        (array)  – Feld-Definitionen für diesen Objekt-Typ
--}}

@if(!empty($cfDefs))

{{-- Script einmalig in den Scripts-Stack injizieren (auch ohne @ckHook('*.page.scripts')) --}}
@pushOnce('scripts')
<script src="{{ asset('js/modules/custom-fields-modal.js') }}"></script>
@endPushOnce

<div id="{{ $cfTabId }}" class="ck-modal__section">

    {{-- Hinweis: Im Create-Modus können noch keine Felder befüllt werden --}}
    <div id="{{ $cfHintId }}" class="ck-alert ck-alert--warning ck-mb-4 is-hidden">
        Bitte zuerst speichern, dann eigene Felder bearbeiten.
    </div>

    {{-- Formular: action wird per JS beim Modal-Öffnen gesetzt --}}
    <form id="{{ $cfFormId }}" method="POST" action="">
        @csrf

        @foreach($cfDefs as $def)
        @php
            $cfFieldId   = $cfFieldPrefix . $def['id'];
            $cfFieldName = 'values[' . $def['id'] . ']';
        @endphp

        <div class="ck-field ck-mt-3">

            @if($def['field_type'] === 'checkbox')
                <label class="ck-field__checkbox">
                    <input type="checkbox"
                           name="{{ $cfFieldName }}"
                           id="{{ $cfFieldId }}"
                           value="1"
                           data-cf-def="{{ $def['id'] }}">
                    {{ $def['label'] }}
                </label>

            @elseif($def['field_type'] === 'select' && !empty($def['options']))
                <label class="ck-field__label" for="{{ $cfFieldId }}">
                    {{ $def['label'] }}
                    @if($def['is_required'])<span class="ck-field__required">*</span>@endif
                </label>
                <select name="{{ $cfFieldName }}"
                        id="{{ $cfFieldId }}"
                        class="ck-field__input"
                        data-cf-def="{{ $def['id'] }}"
                        {{ $def['is_required'] ? 'required' : '' }}>
                    <option value="">— auswählen —</option>
                    @foreach($def['options'] as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>

            @elseif($def['field_type'] === 'textarea')
                <label class="ck-field__label" for="{{ $cfFieldId }}">
                    {{ $def['label'] }}
                    @if($def['is_required'])<span class="ck-field__required">*</span>@endif
                </label>
                <textarea name="{{ $cfFieldName }}"
                          id="{{ $cfFieldId }}"
                          class="ck-field__input"
                          rows="3"
                          data-cf-def="{{ $def['id'] }}"
                          placeholder="{{ $def['placeholder'] ?? '' }}"
                          {{ $def['is_required'] ? 'required' : '' }}></textarea>

            @else
                @php
                    $cfInputType = match($def['field_type']) {
                        'number', 'decimal' => 'number',
                        'date'              => 'date',
                        'email'             => 'email',
                        default             => 'text',
                    };
                @endphp
                <label class="ck-field__label" for="{{ $cfFieldId }}">
                    {{ $def['label'] }}
                    @if($def['is_required'])<span class="ck-field__required">*</span>@endif
                </label>
                <input type="{{ $cfInputType }}"
                       name="{{ $cfFieldName }}"
                       id="{{ $cfFieldId }}"
                       class="ck-field__input"
                       data-cf-def="{{ $def['id'] }}"
                       placeholder="{{ $def['placeholder'] ?? '' }}"
                       {{ $def['is_required'] ? 'required' : '' }}>

            @endif

        </div>
        @endforeach

        <div class="ck-form-actions ck-mt-4">
            <x-ck-button type="submit" variant="primary">Felder speichern</x-ck-button>
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, '{{ $cfModalId }}')">Abbrechen</x-ck-button>
        </div>
    </form>

</div>
@endif
