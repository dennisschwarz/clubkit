{{--
    YouthClubMode – Hook View: member.page.scripts

    Provides the data bridge for youth-club-mode.js.
    Contains: CSRF token, route base, all members, all relations.
    Must be loaded after members-modal.js (ckEmit/ckOn already available).
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
@vite('resources/js/modules/youth-club-mode.js')
