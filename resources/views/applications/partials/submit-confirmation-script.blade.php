@once
    <div class="application-submit-confirmation-modal" id="applicationSubmitConfirmationModal" role="dialog" tabindex="-1" aria-labelledby="applicationSubmitConfirmationTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-body p-4 p-md-5">
                    <div class="application-submit-confirmation-icon mx-auto mb-4">!</div>
                    <h2 class="application-submit-confirmation-title mb-3" id="applicationSubmitConfirmationTitle"></h2>
                    <p class="application-submit-confirmation-text text-muted mb-4" id="applicationSubmitConfirmationText"></p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <button type="button" class="btn btn-danger application-submit-confirmation-cancel"></button>
                        <button type="button" class="btn btn-success application-submit-confirmation-confirm"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            .application-submit-confirmation-modal {
                align-items: center;
                display: none;
                inset: 0;
                justify-content: center;
                overflow-y: auto;
                padding: 1rem;
                pointer-events: none;
                position: fixed;
                z-index: 1060;
            }

            .application-submit-confirmation-modal.show {
                display: flex !important;
                pointer-events: auto;
            }

            .application-submit-confirmation-modal .modal-dialog {
                display: block;
                margin: 0;
                max-width: 512px;
                min-height: auto;
                pointer-events: auto;
                width: min(100%, 512px);
            }

            .application-submit-confirmation-modal .modal-content {
                background: #fff;
                border: 0;
                border-radius: 6px;
                box-shadow: 0 18px 45px rgba(15, 23, 42, .28);
                width: 100%;
            }

            .application-submit-confirmation-backdrop {
                background: rgba(17, 24, 39, .52);
                inset: 0;
                position: fixed;
                z-index: 1050;
            }

            .application-submit-confirmation-modal .application-submit-confirmation-icon {
                align-items: center;
                border: 4px solid #f97316;
                border-radius: 50%;
                color: #f97316;
                display: flex;
                font-size: 3.5rem;
                font-weight: 700;
                height: 5.5rem;
                justify-content: center;
                line-height: 1;
                width: 5.5rem;
            }

            .application-submit-confirmation-modal .application-submit-confirmation-title {
                color: #111827;
                font-size: 1.65rem;
                font-weight: 700;
                letter-spacing: 0;
            }

            .application-submit-confirmation-modal .application-submit-confirmation-text {
                font-size: 1rem;
                line-height: 1.8;
            }

            .application-submit-confirmation-modal .btn {
                border-radius: 4px;
                min-width: 4.75rem;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function () {
                const modalElement = document.getElementById('applicationSubmitConfirmationModal');
                const titleElement = document.getElementById('applicationSubmitConfirmationTitle');
                const textElement = document.getElementById('applicationSubmitConfirmationText');
                const confirmButton = modalElement ? modalElement.querySelector('.application-submit-confirmation-confirm') : null;
                const cancelButton = modalElement ? modalElement.querySelector('.application-submit-confirmation-cancel') : null;
                let pendingForm = null;
                let activeTrigger = null;

                if (modalElement && modalElement.parentElement !== document.body) {
                    document.body.appendChild(modalElement);
                }

                const hideModal = function () {
                    if (!modalElement) {
                        return;
                    }

                    modalElement.classList.remove('show');
                    modalElement.setAttribute('aria-hidden', 'true');
                    modalElement.removeAttribute('aria-modal');
                    modalElement.style.display = 'none';
                    document.querySelectorAll('.application-submit-confirmation-backdrop').forEach(function (backdrop) {
                        backdrop.remove();
                    });

                    if (activeTrigger && typeof activeTrigger.focus === 'function') {
                        activeTrigger.focus();
                    }

                    activeTrigger = null;
                };

                const showModal = function () {
                    if (!modalElement) {
                        return;
                    }

                    document.querySelectorAll('.application-submit-confirmation-backdrop').forEach(function (backdrop) {
                        backdrop.remove();
                    });

                    const backdrop = document.createElement('div');
                    backdrop.className = 'application-submit-confirmation-backdrop';
                    document.body.appendChild(backdrop);
                    modalElement.style.display = 'flex';
                    modalElement.removeAttribute('aria-hidden');
                    modalElement.setAttribute('aria-modal', 'true');
                    modalElement.classList.add('show');

                    if (confirmButton) {
                        confirmButton.focus();
                    }
                };

                if (confirmButton) {
                    confirmButton.addEventListener('click', function () {
                        if (!pendingForm) {
                            hideModal();
                            return;
                        }

                        const form = pendingForm;
                        pendingForm = null;
                        form.dataset.confirmedSubmit = '1';
                        activeTrigger = null;
                        hideModal();
                        form.submit();
                    });
                }

                if (cancelButton) {
                    cancelButton.addEventListener('click', function () {
                        pendingForm = null;
                        hideModal();
                    });
                }

                document.addEventListener('submit', function (event) {
                    const form = event.target.closest('[data-application-submit-confirm]');

                    if (!form || form.dataset.confirmedSubmit === '1') {
                        return;
                    }

                    event.preventDefault();

                    if (!modalElement || !titleElement || !textElement || !confirmButton || !cancelButton) {
                        form.dataset.confirmedSubmit = '1';
                        form.submit();
                        return;
                    }

                    pendingForm = form;
                    activeTrigger = event.submitter || document.activeElement;
                    titleElement.textContent = form.dataset.confirmTitle || '';
                    textElement.textContent = form.dataset.confirmText || '';
                    confirmButton.textContent = form.dataset.confirmButton || '';
                    cancelButton.textContent = form.dataset.cancelButton || '';
                    showModal();
                }, true);

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modalElement && modalElement.classList.contains('show')) {
                        pendingForm = null;
                        hideModal();
                    }
                });
            })();
        </script>
    @endpush
@endonce
