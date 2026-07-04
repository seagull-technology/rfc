@php
    $roleLabels = $entities
        ->flatMap(fn ($entity) => $entity->group?->roles ?? collect())
        ->pluck('name')
        ->unique()
        ->values()
        ->mapWithKeys(fn ($roleName) => [$roleName => __('app.roles.'.$roleName)]);
@endphp

<style>
    .admin-role-picker-field .select2-container--default .select2-selection--multiple {
        min-height: 55px;
        display: flex;
        align-items: center;
        border-color: #d8dee8;
        background-color: #fff;
    }

    .admin-role-picker-field.is-disabled .select2-container--default .select2-selection--multiple {
        cursor: not-allowed;
        background-color: #edf0f4;
        border-color: #d8dee8;
    }

    .admin-role-picker-field.is-disabled .select2-container--default .select2-selection--multiple::after {
        opacity: .35;
    }

    .admin-role-picker-field.is-disabled .select2-search__field {
        cursor: not-allowed;
        color: #697181;
    }

    .admin-role-picker-field .select2-search__field {
        width: 100% !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const entitySelect = document.getElementById('entity_id');
        const roleSelect = document.getElementById('roles');
        const rolePickerField = roleSelect?.closest('[data-role-picker-field]');

        if (!entitySelect || !roleSelect) {
            return;
        }

        let selectedRoles = @json(collect(old('roles', []))->filter()->values()->all());
        const labels = @json($roleLabels);

        const getJquery = () => {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                return null;
            }

            return window.jQuery;
        };

        const destroyRoleSelect2 = () => {
            const jquery = getJquery();

            if (!jquery) {
                return;
            }

            const roleSelectUi = jquery(roleSelect);

            if (roleSelectUi.data('select2')) {
                roleSelectUi.select2('destroy');
            }
        };

        const getPlaceholder = () => {
            if (roleSelect.disabled) {
                return roleSelect.dataset.disabledPlaceholder || roleSelect.dataset.placeholder || '';
            }

            return roleSelect.dataset.activePlaceholder || roleSelect.dataset.placeholder || '';
        };

        const syncPlaceholder = () => {
            roleSelect.dataset.placeholder = getPlaceholder();
            roleSelect.setAttribute('data-placeholder', getPlaceholder());
        };

        const refreshRoleSelect2 = () => {
            const jquery = getJquery();

            if (!jquery) {
                return;
            }

            const roleSelectUi = jquery(roleSelect);
            const placeholder = getPlaceholder();

            roleSelectUi.data('placeholder', placeholder);

            roleSelectUi.select2({
                width: '100%',
                placeholder: placeholder,
                dir: document.documentElement.dir || 'rtl',
            });

            roleSelectUi.prop('disabled', roleSelect.disabled);
            roleSelectUi.trigger('change.select2');
        };

        const setRoleSelectDisabled = (disabled) => {
            roleSelect.disabled = disabled;
            roleSelect.toggleAttribute('disabled', disabled);
            roleSelect.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            rolePickerField?.classList.toggle('is-disabled', disabled);
            syncPlaceholder();
        };

        const populateRoles = () => {
            const previouslySelected = Array.from(roleSelect.selectedOptions).map((option) => option.value);
            const preferredSelection = new Set([...selectedRoles, ...previouslySelected]);
            const selectedOption = entitySelect.options[entitySelect.selectedIndex];
            const roles = (selectedOption?.dataset.roles || '')
                .split(',')
                .map((roleName) => roleName.trim())
                .filter(Boolean);

            destroyRoleSelect2();
            roleSelect.innerHTML = '';

            if (!roles.length) {
                roleSelect.dataset.disabledPlaceholder = selectedOption?.value
                    ? (roleSelect.dataset.emptyPlaceholder || roleSelect.dataset.disabledPlaceholder || '')
                    : @json(__('app.admin.users.choose_entity_first'));
                setRoleSelectDisabled(true);
                selectedRoles = [];
                refreshRoleSelect2();
                roleSelect.dispatchEvent(new Event('change', { bubbles: true }));

                return;
            }

            setRoleSelectDisabled(false);

            roles.forEach((roleName) => {
                const option = document.createElement('option');

                option.value = roleName;
                option.textContent = labels[roleName] || roleName;
                option.selected = preferredSelection.has(roleName);

                roleSelect.appendChild(option);
            });

            selectedRoles = [];
            refreshRoleSelect2();
            roleSelect.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const stopDisabledOpen = (event) => {
            if (roleSelect.disabled) {
                event.preventDefault();
            }
        };

        const jquery = getJquery();

        if (jquery) {
            jquery(roleSelect).on('select2:opening', stopDisabledOpen);
        }

        entitySelect.addEventListener('change', populateRoles);
        populateRoles();
        window.addEventListener('load', populateRoles, { once: true });
        window.setTimeout(populateRoles, 100);
        });
</script>
