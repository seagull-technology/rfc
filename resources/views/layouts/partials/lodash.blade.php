{{-- Change the cache key with the deployed bytes, including future vendor updates. --}}
<script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('js/lodash.min.js') }}?v={{ hash_file('sha256', public_path('js/lodash.min.js')) }}"></script>
