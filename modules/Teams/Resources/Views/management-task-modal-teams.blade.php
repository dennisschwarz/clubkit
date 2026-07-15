{{--
    Teams hook: team select dropdown inside the management task create/edit form.
    Rendered by @ckHook('management.task.modal.teams') in Management/index.blade.php.

    Variables supplied by View Composer (TeamsServiceProvider):
      $ckAllTeams  Collection  All teams ordered by name.

    The select is left blank in edit mode — team assignment is managed
    by drag & drop. Only pre-filled for create via mgmtModalOpen().
--}}
@if(isset($ckAllTeams) && $ckAllTeams->isNotEmpty())
@php
    $ckTeamOptions = ['' => '– ' . __('management.field.no_team') . ' –'];
    foreach ($ckAllTeams as $ckTeam) {
        $ckTeamOptions[$ckTeam->id] = $ckTeam->name;
    }
@endphp
<x-ck-field type="select" name="team_id" id="mgmtTaskTeamId"
    :label="__('management.field.team')"
    :options="$ckTeamOptions" />
@endif
