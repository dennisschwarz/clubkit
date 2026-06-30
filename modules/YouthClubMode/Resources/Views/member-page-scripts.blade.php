{{--
    YouthClubMode – Hook View: member.page.scripts

    Stellt die Data Bridge für youth-club-mode.js bereit.
    Enthält: CSRF-Token, Routen-Basis, alle Mitglieder, alle Verbindungen.
    Muss nach members-modal.js geladen werden (ckEmit/ckOn bereits verfügbar).
--}}
<script>
    window.CK_YouthClubMode = {
        csrf: "{{ csrf_token() }}",
        routes: {
            relationsBase: "{{ url('members') }}"
        },
        allMembers: @json($allMembersJs ?? []),
        relations:  @json($relationsJs  ?? [])
    };
</script>
@vite(['resources/js/modules/youth-club-mode.js'])
