@once
    @push('scripts')
        <script>
            (function () {
                const markerAttribute = 'data-auto-required-marker';
                let requiredMarkerSyncQueued = false;

                function hasVisibleRequiredMarker(label) {
                    const markers = Array.from(label.querySelectorAll('.text-danger'));
                    const parent = label.parentElement;

                    if (parent) {
                        Array.from(parent.children).forEach(function (sibling) {
                            if (sibling !== label && sibling.matches('.text-danger')) {
                                markers.push(sibling);
                            }
                        });
                    }

                    return markers.some(function (marker) {
                        return marker.textContent.trim() === '*'
                            && !marker.classList.contains('d-none')
                            && marker.getAttribute('hidden') === null;
                    });
                }

                function fieldLabel(field) {
                    if (field.id) {
                        const explicitLabel = document.querySelector('label[for="' + CSS.escape(field.id) + '"]');

                        if (explicitLabel) {
                            return explicitLabel;
                        }
                    }

                    const wrappingLabel = field.closest('label');

                    if (wrappingLabel) {
                        return wrappingLabel;
                    }

                    const fieldContainer = field.closest('.form-check, [class*="col-"], .form-group, .mb-3, td');

                    if (!fieldContainer) {
                        return null;
                    }

                    const localLabel = fieldContainer.querySelector('label.form-label, label.form-check-label, label');

                    if (localLabel) {
                        return localLabel;
                    }

                    if (fieldContainer.tagName !== 'TD') {
                        return null;
                    }

                    const table = fieldContainer.closest('table');
                    const headingCells = table ? table.querySelectorAll('thead tr:last-child th') : [];

                    return headingCells[fieldContainer.cellIndex] || null;
                }

                function appendRequiredMarker(label) {
                    if (!label || hasVisibleRequiredMarker(label)) {
                        return;
                    }

                    const marker = document.createElement('span');
                    marker.className = 'text-danger ms-1';
                    marker.setAttribute(markerAttribute, '');
                    marker.setAttribute('aria-hidden', 'true');
                    marker.textContent = '*';
                    label.appendChild(marker);
                }

                function syncRequiredFieldMarkers() {
                    document.querySelectorAll('[' + markerAttribute + ']').forEach(function (marker) {
                        marker.remove();
                    });

                    document.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (field) {
                        if (field.disabled || field.type === 'hidden') {
                            return;
                        }

                        appendRequiredMarker(fieldLabel(field));
                    });
                }

                function queueRequiredFieldMarkerSync() {
                    if (requiredMarkerSyncQueued) {
                        return;
                    }

                    requiredMarkerSyncQueued = true;
                    window.requestAnimationFrame(function () {
                        requiredMarkerSyncQueued = false;
                        syncRequiredFieldMarkers();
                    });
                }

                document.addEventListener('DOMContentLoaded', queueRequiredFieldMarkerSync);
                document.addEventListener('input', queueRequiredFieldMarkerSync, true);
                document.addEventListener('change', queueRequiredFieldMarkerSync, true);
                document.addEventListener('shown.bs.offcanvas', queueRequiredFieldMarkerSync);

                new MutationObserver(function (mutations) {
                    const meaningfulMutation = mutations.some(function (mutation) {
                        if (mutation.type === 'attributes') {
                            return true;
                        }

                        return Array.from(mutation.addedNodes).concat(Array.from(mutation.removedNodes)).some(function (node) {
                            return node.nodeType === Node.ELEMENT_NODE
                                && !node.hasAttribute(markerAttribute);
                        });
                    });

                    if (meaningfulMutation) {
                        queueRequiredFieldMarkerSync();
                    }
                }).observe(document.documentElement, {
                    subtree: true,
                    childList: true,
                    attributes: true,
                    attributeFilter: ['required', 'disabled'],
                });

                window.syncApplicationRequiredFieldMarkers = queueRequiredFieldMarkerSync;
                queueRequiredFieldMarkerSync();
            })();
        </script>
    @endpush
@endonce
