@php
    $resolvedStatusCode = isset($exception) && method_exists($exception, 'getStatusCode')
        ? $exception->getStatusCode()
        : 500;
@endphp
@include('errors.layout', ['statusCode' => $resolvedStatusCode, 'translationKey' => '5xx', 'icon' => 'ph-warning-circle'])
