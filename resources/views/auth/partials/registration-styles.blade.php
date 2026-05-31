<style>
    .registration-auth-page {
        padding: 1.5rem 0;
    }

    .registration-card {
        width: min(100%, 980px);
        border: 1px solid rgba(255, 255, 255, .14);
        border-radius: .75rem;
        background: rgba(31, 33, 38, .9);
        padding: clamp(1.25rem, 3vw, 2rem);
    }

    .registration-card-wide {
        width: min(100%, 1120px);
    }

    .registration-card-narrow {
        width: min(100%, 780px);
    }

    .registration-page-data {
        padding-top: 1rem;
    }

    .registration-logo-link {
        display: inline-flex;
        justify-content: center;
        width: 100%;
    }

    .registration-logo {
        max-height: 72px;
        width: auto;
    }

    .registration-header {
        margin: 1.25rem auto 1.5rem;
        max-width: 760px;
        text-align: center;
    }

    .registration-title {
        color: var(--bs-white);
        font-size: clamp(1.35rem, 2.2vw, 2rem);
        font-weight: 700;
        line-height: 1.25;
        margin-bottom: .5rem;
    }

    .registration-subtitle {
        color: rgba(255, 255, 255, .72);
        margin-bottom: 0;
    }

    .registration-tabs {
        display: grid;
        gap: .75rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }

    .registration-tabs .nav-item {
        min-width: 0;
    }

    .registration-tabs .nav-link {
        align-items: center;
        border: 1px solid rgba(255, 255, 255, .16);
        border-radius: .5rem;
        color: rgba(255, 255, 255, .78);
        display: flex;
        font-weight: 700;
        justify-content: center;
        min-height: 3.25rem;
        padding: .75rem .875rem;
        text-align: center;
        white-space: normal;
    }

    .registration-tabs .nav-link.active {
        border-color: rgba(var(--bs-danger-rgb), .85);
        background: var(--bs-danger);
        color: var(--bs-white);
    }

    .registration-form-panel,
    .registration-info-panel {
        border: 1px solid rgba(255, 255, 255, .14);
        border-radius: .75rem;
        background: rgba(255, 255, 255, .055);
        padding: clamp(1rem, 2.3vw, 1.5rem);
    }

    .registration-form-panel .form-label,
    .registration-info-panel .form-label {
        font-weight: 700;
    }

    .registration-form-panel .row {
        row-gap: .25rem;
    }

    .registration-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        margin-top: 1.25rem;
    }

    .registration-actions > .btn,
    .registration-actions > form,
    .registration-actions > form .btn {
        flex: 1 1 12rem;
    }

    .registration-secondary-action {
        margin-top: 1rem;
        text-align: center;
    }

    .registration-secondary-action a {
        color: rgba(255, 255, 255, .82);
        font-weight: 700;
    }

    .registration-alert {
        border-radius: .5rem;
    }

    .registration-summary-grid {
        display: grid;
        gap: .875rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .registration-summary-block,
    .registration-history-item,
    .registration-update-block {
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: .5rem;
        background: rgba(255, 255, 255, .045);
        padding: 1rem;
    }

    .registration-block-label {
        color: rgba(255, 255, 255, .68);
        display: block;
        font-size: .8125rem;
        font-weight: 700;
        margin-bottom: .4rem;
    }

    .registration-history-list {
        display: flex;
        flex-direction: column;
        gap: .75rem;
    }

    @media (max-width: 991.98px) {
        .registration-tabs {
            display: flex;
            overflow-x: auto;
            padding-bottom: .25rem;
            scrollbar-width: thin;
        }

        .registration-tabs .nav-item {
            flex: 0 0 min(12rem, 72vw);
        }
    }

    @media (max-width: 767.98px) {
        .registration-card {
            padding: 1rem;
        }

        .registration-summary-grid {
            grid-template-columns: 1fr;
        }

        .registration-actions {
            flex-direction: column;
        }

        .registration-actions > .btn,
        .registration-actions > form,
        .registration-actions > form .btn {
            flex-basis: auto;
            width: 100%;
        }
    }
</style>
