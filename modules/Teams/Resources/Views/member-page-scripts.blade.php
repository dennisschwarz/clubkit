<script>
    /**
     * CK_MemberTeamsBridge
     *
     * memberTeams: memberId (int) → array of teamId (int) for the current page.
     *              Populated by the TeamsServiceProvider ViewComposer.
     *              Only contains members on the current paginated page.
     *
     * route: base URL for syncMemberTeams – append "/{memberId}/sync" to build the endpoint.
     */
    window.CK_MemberTeamsBridge = {
        memberTeams: @json($ckMemberTeamMap),
        route: "{{ url('teams/member') }}"
    };
</script>
@vite('resources/js/modules/member-teams.js')
