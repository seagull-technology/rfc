<style>
    .auth-visual-page {
        background-color: #111315;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        isolation: isolate;
        overflow-x: hidden;
    }

    .auth-visual-page::before {
        background: rgba(7, 9, 11, .62);
        content: "";
        inset: 0;
        pointer-events: none;
        position: absolute;
        z-index: 0;
    }

    .auth-visual-page > .container {
        position: relative;
        z-index: 1;
    }

    .auth-visual-card {
        background: rgba(22, 23, 26, .9) !important;
        border: 1px solid rgba(255, 255, 255, .2);
        box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, .5) !important;
        -webkit-backdrop-filter: blur(4px);
        backdrop-filter: blur(4px);
    }

    .auth-visual-card :is(
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        label,
        .form-label,
        .links,
        .or-section,
        .registration-title,
        .registration-subtitle,
        .registration-tabs .nav-link
    ) {
        text-shadow: 0 2px 5px rgba(0, 0, 0, .92);
    }

    .auth-visual-card label,
    .auth-visual-card .form-label,
    .auth-visual-card .links,
    .auth-visual-card .or-section {
        color: rgba(255, 255, 255, .94);
    }

    .auth-visual-card .form-control:not(:disabled):not([readonly]),
    .auth-visual-card .form-select:not(:disabled):not([readonly]) {
        background-color: rgba(7, 9, 11, .84);
        border-color: rgba(255, 255, 255, .28);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
        color: #fff;
    }

    .auth-visual-card .form-control:not(:disabled):not([readonly])::placeholder {
        color: rgba(255, 255, 255, .62);
        opacity: 1;
    }

    .auth-visual-card .form-control:not(:disabled):not([readonly]):focus,
    .auth-visual-card .form-select:not(:disabled):not([readonly]):focus {
        background-color: rgba(4, 6, 8, .94);
        border-color: rgba(var(--bs-danger-rgb), .9);
        box-shadow: 0 0 0 .2rem rgba(var(--bs-danger-rgb), .2);
        color: #fff;
    }

    .auth-visual-card .input-group-text {
        background: rgba(7, 9, 11, .92);
        border-color: rgba(255, 255, 255, .28);
        color: rgba(255, 255, 255, .9);
    }

    @media (max-width: 767.98px) {
        .auth-visual-page {
            background-position: 52% center;
            overflow-y: auto;
        }

        .auth-visual-page::before {
            background: rgba(7, 9, 11, .68);
        }

        .auth-visual-card {
            -webkit-backdrop-filter: blur(2px);
            backdrop-filter: blur(2px);
        }
    }
</style>
