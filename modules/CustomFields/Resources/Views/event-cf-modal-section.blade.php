@php
$cfObjectType  = 'event';
$cfModalId     = 'evtModal';
$cfTabId       = 'evtTab-cf';
$cfFormId      = 'evtCfForm';
$cfHintId      = 'evtCfCreateHint';
$cfFieldPrefix = 'eCf_';
$cfDefs        = $eventCfDefs ?? [];
@endphp
@include('custom-fields::_cf-fields-form')
