@php
$cfObjectType  = 'team';
$cfModalId     = 'teamModal';
$cfTabId       = 'teamTab-cf';
$cfFormId      = 'teamCfForm';
$cfHintId      = 'teamCfCreateHint';
$cfFieldPrefix = 'tCf_';
$cfDefs        = $teamCfDefs ?? [];
@endphp
@include('custom-fields::_cf-fields-form')
