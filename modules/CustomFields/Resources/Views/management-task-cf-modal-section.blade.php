@php
$cfObjectType  = 'management_task';
$cfModalId     = 'mgmtTaskModal';
$cfTabId       = 'mgmtTaskTab-cf';
$cfFormId      = 'mgmtTaskCfForm';
$cfHintId      = 'mgmtTaskCfCreateHint';
$cfFieldPrefix = 'taskCf_';
$cfDefs        = $mgmtTaskCfDefs ?? [];
@endphp
@include('custom-fields::_cf-fields-form')
