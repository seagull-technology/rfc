<style nonce="{{ $cspNonce ?? '' }}">
    html,
    body {
        max-width: 100%;
        overflow-x: hidden;
    }

    @supports (overflow: clip) {
        html,
        body {
            overflow-x: clip;
        }
    }
</style>
