@php
$cfObjectType  = 'management_function';
$cfModalId     = 'mgmtFunctionModal';
$cfTabId       = 'mgmtFunctionTab-cf';
$cfFormId      = 'mgmtFunctionCfForm';
$cfHintId      = 'mgmtFunctionCfCreateHint';
$cfFieldPrefix = 'fnCf_';
$cfDefs        = $mgmtFunctionCfDefs ?? [];
@endphp
@include('custom-fields::_cf-fields-form')
