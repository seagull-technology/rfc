@php
    $resolvedStatusCode = isset($exception) && method_exists($exception, 'getStatusCode')
        ? $exception->getStatusCode()
        : 400;
@endphp
@include('errors.layout', ['statusCode' => $resolvedStatusCode, 'translationKey' => '4xx', 'icon' => 'ph-warning'])
