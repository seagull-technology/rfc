<style>
    @keyframes rfc-submit-spin {
        to { transform: rotate(360deg); }
    }

    .rfc-submit-loading {
        align-items: center !important;
        cursor: wait !important;
        display: inline-flex !important;
        gap: 0.5rem;
        justify-content: center !important;
    }

    .rfc-submit-spinner {
        animation: rfc-submit-spin 0.7s linear infinite;
        border: 2px solid currentColor;
        border-inline-end-color: transparent;
        border-radius: 50%;
        display: inline-block;
        flex: 0 0 1em;
        height: 1em;
        width: 1em;
    }
</style>
<script>
    window.RfcSubmitStateConfig = {
        loadingLabel: @js(__('app.submitting')),
    };
</script>
<script src="{{ asset('js/form-submit-state.js') }}?v=1.0.0" defer></script>
