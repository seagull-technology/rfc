@php
    $roleLabels = $entities
        ->flatMap(fn ($entity) => $entity->group?->roles ?? collect())
        ->pluck('name')
        ->unique()
        ->values()
        ->mapWithKeys(fn ($roleName) => [$roleName => __('app.roles.'.$roleName)]);
@endphp

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const entitySelect = document.getElementById('entity_id');
        const roleSelect = document.getElementById('roles');

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

        const refreshRoleSelect2 = () => {
            const jquery = getJquery();

            if (!jquery) {
                return;
            }

            const roleSelectUi = jquery(roleSelect);

            roleSelectUi.select2({
                width: '100%',
                placeholder: roleSelect.dataset.placeholder || '',
            });

            roleSelectUi.trigger('change.select2');
        };

        const setRoleSelectDisabled = (disabled) => {
            roleSelect.disabled = disabled;
            roleSelect.toggleAttribute('disabled', disabled);
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

        entitySelect.addEventListener('change', populateRoles);
        populateRoles();
    });
</script>
