@once
    <style>
        .sla-live-counter {
            --sla-counter-color: #5e1d19;
            --sla-counter-soft: rgba(94, 29, 25, .1);
            --sla-counter-border: rgba(94, 29, 25, .24);
            display: inline-flex;
            align-items: stretch;
            flex-wrap: wrap;
            gap: .4rem;
            max-width: 100%;
            margin-top: .35rem;
            padding: .45rem;
            border: 1px solid var(--sla-counter-border);
            border-radius: 6px;
            background: var(--sla-counter-soft);
            color: var(--sla-counter-color);
            direction: ltr;
        }

        .sla-live-counter.is-overdue {
            --sla-counter-color: #b42318;
            --sla-counter-soft: rgba(180, 35, 24, .1);
            --sla-counter-border: rgba(180, 35, 24, .24);
        }

        .sla-live-counter__caption {
            display: inline-flex;
            align-items: center;
            padding: .35rem .5rem;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 700;
            line-height: 1;
            background: #fff;
            color: var(--sla-counter-color);
            white-space: nowrap;
        }

        .sla-live-counter__segment {
            min-width: 3.25rem;
            padding: .35rem .45rem;
            border-radius: 6px;
            background: var(--sla-counter-color);
            color: #fff;
            text-align: center;
            line-height: 1;
        }

        .sla-live-counter__value {
            display: block;
            font-size: 1rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0;
        }

        .sla-live-counter__label {
            display: block;
            margin-top: .25rem;
            font-size: .62rem;
            font-weight: 600;
            opacity: .84;
            white-space: nowrap;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const counters = Array.from(document.querySelectorAll('[data-sla-countdown]'));

            if (!counters.length) {
                return;
            }

            const pad = (value) => String(value).padStart(2, '0');
            const labels = {
                remaining: @json(__('app.admin.authority_escalations.countdown_remaining_label')),
                overdue: @json(__('app.admin.authority_escalations.countdown_overdue_label')),
                days: @json(__('app.admin.authority_escalations.countdown_units.days')),
                hours: @json(__('app.admin.authority_escalations.countdown_units.hours')),
                minutes: @json(__('app.admin.authority_escalations.countdown_units.minutes')),
                seconds: @json(__('app.admin.authority_escalations.countdown_units.seconds')),
            };

            const durationParts = (milliseconds) => {
                const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));

                return {
                    days: Math.floor(totalSeconds / 86400),
                    hours: Math.floor((totalSeconds % 86400) / 3600),
                    minutes: Math.floor((totalSeconds % 3600) / 60),
                    seconds: totalSeconds % 60,
                };
            };

            const ensureCounterStructure = (counter) => {
                if (counter.dataset.slaCounterReady === '1') {
                    return;
                }

                counter.classList.add('sla-live-counter');
                counter.classList.remove('small', 'text-muted');
                counter.innerHTML = '';

                const caption = document.createElement('span');
                caption.className = 'sla-live-counter__caption';
                caption.dataset.counterCaption = 'true';
                counter.appendChild(caption);

                ['days', 'hours', 'minutes', 'seconds'].forEach((part) => {
                    const segment = document.createElement('span');
                    segment.className = 'sla-live-counter__segment';

                    const value = document.createElement('span');
                    value.className = 'sla-live-counter__value';
                    value.dataset.counterPart = part;

                    const label = document.createElement('span');
                    label.className = 'sla-live-counter__label';
                    label.textContent = labels[part];

                    segment.appendChild(value);
                    segment.appendChild(label);
                    counter.appendChild(segment);
                });

                counter.dataset.slaCounterReady = '1';
            };

            const render = () => {
                const now = Date.now();

                counters.forEach((counter) => {
                    const dueAt = Date.parse(counter.dataset.dueAt || '');

                    if (Number.isNaN(dueAt)) {
                        return;
                    }

                    const isOverdue = dueAt <= now;
                    const parts = durationParts(Math.abs(dueAt - now));

                    ensureCounterStructure(counter);
                    counter.classList.toggle('is-overdue', isOverdue);

                    counter.querySelector('[data-counter-caption]').textContent = isOverdue
                        ? labels.overdue
                        : labels.remaining;

                    counter.querySelector('[data-counter-part="days"]').textContent = pad(parts.days);
                    counter.querySelector('[data-counter-part="hours"]').textContent = pad(parts.hours);
                    counter.querySelector('[data-counter-part="minutes"]').textContent = pad(parts.minutes);
                    counter.querySelector('[data-counter-part="seconds"]').textContent = pad(parts.seconds);
                });
            };

            render();
            window.setInterval(render, 1000);
        });
    </script>
@endonce
