{{--
    YouthClubMode – Hook View: member.table.row
    Fügt Guardian-Zellen pro Tabellenzeile ein.
    $member ist automatisch im Scope (übergeben via @ckHook im @foreach-Loop).
--}}
<td class="ck-text-muted">
    @if($member->fatherLink?->parent)
        {{ $member->fatherLink->parent->last_name }},
        {{ $member->fatherLink->parent->first_name }}
    @else
        –
    @endif
</td>
<td class="ck-text-muted">
    @if($member->motherLink?->parent)
        {{ $member->motherLink->parent->last_name }},
        {{ $member->motherLink->parent->first_name }}
    @else
        –
    @endif
</td>
