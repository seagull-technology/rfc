<script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-notification-trigger]').forEach(function (trigger) {
            var isSubmitting = false;

            trigger.addEventListener('click', function () {
                var badge = trigger.querySelector('[data-notification-count]');

                if (! badge || isSubmitting) {
                    return;
                }

                isSubmitting = true;
                badge.classList.add('d-none');

                fetch(@json(route('notifications.read')), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                }).then(function (response) {
                    if (! response.ok) {
                        throw new Error('Unable to update notification state.');
                    }

                    badge.remove();
                }).catch(function () {
                    badge.classList.remove('d-none');
                    isSubmitting = false;
                });
            });
        });
    });
</script>
