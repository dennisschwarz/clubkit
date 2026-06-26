{{--
    YouthClubMode – Hook View: member.table.row
    Zeigt alle familiären Verbindungen in einer Zelle.

    Verfügbare Variablen (automatisch übergeben via @ckHook):
      $member         – aktuelles Member-Objekt im @foreach-Loop
      $memberFamilies – Array [member_id => family{}], durch ViewComposer injiziert
--}}
@php
    $family = $memberFamilies[$member->id] ?? [];
    $hasFamilyData = ($family['father'] ?? null)
                  || ($family['mother'] ?? null)
                  || !empty($family['children'])
                  || !empty($family['siblings']);
@endphp
<td>
    @if($hasFamilyData)
        @if($family['father'] ?? null)
            <div class="ck-family-tag">
                <span>Vater:</span> {{ $family['father']['name'] }}
            </div>
        @endif
        @if($family['mother'] ?? null)
            <div class="ck-family-tag">
                <span>Mutter:</span> {{ $family['mother']['name'] }}
            </div>
        @endif
        @foreach($family['children'] ?? [] as $child)
            <div class="ck-family-tag">
                <span>Kind:</span> {{ $child['name'] }}
            </div>
        @endforeach
        @foreach($family['siblings'] ?? [] as $sibling)
            <div class="ck-family-tag">
                <span>Geschwister:</span> {{ $sibling['name'] }}
            </div>
        @endforeach
    @else
        <span class="ck-text-muted">–</span>
    @endif
</td>
