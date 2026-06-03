<style>
    .registration-auth-page {
        padding: 1.5rem 0;
    }

    .registration-card {
        position: relative;
        width: min(100%, 980px);
        border: 1px solid rgba(255, 255, 255, .14);
        border-radius: .75rem;
        background: rgba(31, 33, 38, .9);
        overflow: hidden;
        padding: clamp(1.25rem, 3vw, 2rem);
    }

    .registration-card-wide {
        width: min(100%, 1120px);
    }

    .registration-card-narrow {
        width: min(100%, 780px);
    }

    .registration-brand-hero {
        align-items: center;
        background:
            linear-gradient(rgb(0 0 0 / 86%), rgb(0 0 0 / 68%)),
            url('{{ asset('images/3.webp') }}') center center / cover no-repeat;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: .5rem;
        display: flex;
        justify-content: center;
        min-height: 9rem;
        padding: 1.25rem;
    }

    .registration-page-data {
        padding-top: 1.25rem;
    }

    .registration-logo-link {
        display: inline-flex;
        justify-content: center;
    }

    .registration-logo-badge {
        align-items: center;
        background: rgba(255, 255, 255, .96);
        border: 4px solid var(--bs-white);
        border-radius: 50%;
        box-shadow: 0 1rem 2.5rem rgba(0, 0, 0, .35);
        height: 8.125rem;
        padding: .75rem;
        width: 8.125rem;
    }

    .registration-logo {
        max-height: 6.75rem;
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

    .registration-lookup-control {
        display: grid;
        gap: .75rem;
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .registration-lookup-button {
        align-items: center;
        display: inline-flex;
        gap: .45rem;
        justify-content: center;
        min-width: 9rem;
        white-space: nowrap;
    }

    .registration-inline-feedback {
        color: rgba(255, 255, 255, .72);
        font-size: .875rem;
        font-weight: 700;
        min-height: 1.35rem;
        padding-top: .35rem;
    }

    .registration-inline-feedback.is-error {
        color: #ffb4b4;
    }

    .registration-inline-feedback.is-success {
        color: #a7f3d0;
    }

    .student-lookup-fields,
    .student-account-fields {
        margin-top: .25rem;
    }

    .registration-password-rules {
        display: grid;
        gap: .35rem;
        list-style: none;
        margin: .75rem 0 0;
        padding: 0;
    }

    .registration-password-rules li {
        align-items: center;
        color: rgba(255, 255, 255, .68);
        display: flex;
        font-size: .8125rem;
        font-weight: 700;
        gap: .4rem;
    }

    .registration-password-rules li::before {
        content: "";
        border: 1px solid currentColor;
        border-radius: 50%;
        height: .55rem;
        opacity: .75;
        width: .55rem;
    }

    .registration-password-rules li.is-valid {
        color: #a7f3d0;
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
            flex-wrap: nowrap;
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

        .registration-tabs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            overflow-x: visible;
            padding-bottom: 0;
        }

        .registration-tabs .nav-item {
            flex: initial;
        }

        .registration-summary-grid {
            grid-template-columns: 1fr;
        }

        .registration-brand-hero {
            min-height: 7.5rem;
        }

        .registration-logo-badge {
            height: 6.75rem;
            width: 6.75rem;
        }

        .registration-logo {
            max-height: 5.5rem;
        }

        .registration-lookup-control {
            grid-template-columns: 1fr;
        }

        .registration-lookup-button {
            width: 100%;
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
