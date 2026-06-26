@php
$cfObjectType  = 'member';
$cfModalId     = 'memberModal';
$cfTabId       = 'memberTab-cf';
$cfFormId      = 'memberCfForm';
$cfHintId      = 'memberCfCreateHint';
$cfFieldPrefix = 'mCf_';
$cfDefs        = $memberCfDefs ?? [];
@endphp
@include('custom-fields::_cf-fields-form')
