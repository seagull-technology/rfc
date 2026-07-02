(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("#form-wizard1").forEach(function (form) {
            const fieldsets = Array.from(form.querySelectorAll("fieldset"));

            if (fieldsets.length === 0) {
                return;
            }

            const innerTabButtons = Array.from(fieldsets[0].querySelectorAll(".streamit-tabs [data-bs-toggle='pill']"));
            const innerTabPanes = innerTabButtons.map(function (button) {
                const target = button.getAttribute("data-bs-target");

                return target ? form.querySelector(target) : null;
            });
            const steps = [
                document.getElementById("step1"),
                document.getElementById("step2"),
            ];
            let currentFieldset = 0;
            const nextButtons = Array.from(form.querySelectorAll(".request-wizard-next"));
            const previousButtons = Array.from(form.querySelectorAll(".request-wizard-previous"));

            const activeInnerTabIndex = function () {
                const activeIndex = innerTabButtons.findIndex(function (button) {
                    return button.classList.contains("active");
                });

                return activeIndex >= 0 ? activeIndex : 0;
            };

            const updateNavigationState = function () {
                const isAtFirstTab = currentFieldset === 0 && activeInnerTabIndex() === 0;

                previousButtons.forEach(function (button) {
                    button.disabled = isAtFirstTab;
                    button.classList.toggle("disabled", isAtFirstTab);
                });
            };

            const activateInnerTab = function (index) {
                if (innerTabButtons.length === 0) {
                    return;
                }

                const safeIndex = Math.max(0, Math.min(index, innerTabButtons.length - 1));

                innerTabButtons.forEach(function (button, buttonIndex) {
                    const isActive = buttonIndex === safeIndex;
                    const pane = innerTabPanes[buttonIndex];

                    button.classList.toggle("active", isActive);
                    button.setAttribute("aria-selected", isActive ? "true" : "false");

                    if (pane) {
                        pane.classList.toggle("active", isActive);
                        pane.classList.toggle("show", isActive);
                    }
                });

                updateNavigationState();
            };

            const showInvalidControl = function (control) {
                const tabPane = control.closest(".tab-pane");

                if (tabPane) {
                    const tabIndex = innerTabPanes.indexOf(tabPane);

                    if (tabIndex >= 0) {
                        activateInnerTab(tabIndex);
                    }
                }

                control.scrollIntoView({ behavior: "smooth", block: "center" });

                if (typeof control.reportValidity === "function") {
                    control.reportValidity();
                }

                if (typeof control.focus === "function") {
                    control.focus({ preventScroll: true });
                }
            };

            const controlIsInvalid = function (control) {
                return ! control.disabled
                    && control.type !== "hidden"
                    && typeof control.checkValidity === "function"
                    && ! control.checkValidity();
            };

            const validateControls = function (controls) {
                const invalidControl = controls.find(controlIsInvalid);

                if (invalidControl) {
                    showInvalidControl(invalidControl);

                    return false;
                }

                return true;
            };

            const currentStepControls = function () {
                if (currentFieldset === 0) {
                    const activePane = innerTabPanes[activeInnerTabIndex()];
                    const generalControls = Array.from(fieldsets[0].querySelectorAll("input, select, textarea"))
                        .filter(function (control) {
                            return ! control.closest(".tab-pane");
                        });
                    const activePaneControls = activePane
                        ? Array.from(activePane.querySelectorAll("input, select, textarea"))
                        : [];

                    return generalControls.concat(activePaneControls);
                }

                return Array.from(fieldsets[currentFieldset].querySelectorAll("input, select, textarea"));
            };

            const validateCurrentStep = function () {
                return validateControls(currentStepControls());
            };

            const validateGeneralStep = function () {
                return validateControls(Array.from(fieldsets[0].querySelectorAll("input, select, textarea")));
            };

            const scrollToWizardTop = function () {
                window.requestAnimationFrame(function () {
                    const top = form.getBoundingClientRect().top + window.pageYOffset - 24;

                    window.scrollTo({
                        top: Math.max(0, top),
                        behavior: "smooth",
                    });
                });
            };

            const setActiveStep = function (index) {
                steps.forEach(function (step, stepIndex) {
                    if (! step) {
                        return;
                    }

                    step.classList.toggle("active", stepIndex === index);
                    step.classList.toggle("done", stepIndex < index);

                    if (stepIndex > index) {
                        step.classList.remove("done");
                    }
                });
            };

            const showFieldset = function (index, innerTabIndex) {
                currentFieldset = Math.max(0, Math.min(index, fieldsets.length - 1));

                fieldsets.forEach(function (fieldset, fieldsetIndex) {
                    fieldset.style.display = fieldsetIndex === currentFieldset ? "block" : "none";
                });

                setActiveStep(currentFieldset);

                if (currentFieldset === 0) {
                    activateInnerTab(typeof innerTabIndex === "number" ? innerTabIndex : activeInnerTabIndex());
                }

                updateNavigationState();
            };

            const goNext = function () {
                if (! validateCurrentStep()) {
                    return;
                }

                if (currentFieldset === 0 && innerTabButtons.length > 0) {
                    const nextInnerTab = activeInnerTabIndex() + 1;

                    if (nextInnerTab < innerTabButtons.length) {
                        activateInnerTab(nextInnerTab);

                        return;
                    }
                }

                showFieldset(currentFieldset + 1);
                scrollToWizardTop();
            };

            const goPrevious = function () {
                if (currentFieldset === 0 && innerTabButtons.length > 0) {
                    const previousInnerTab = activeInnerTabIndex() - 1;

                    if (previousInnerTab >= 0) {
                        activateInnerTab(previousInnerTab);
                    }

                    return;
                }

                if (currentFieldset === 1 && innerTabButtons.length > 0) {
                    showFieldset(0, innerTabButtons.length - 1);

                    return;
                }

                showFieldset(currentFieldset - 1);
            };

            nextButtons.forEach(function (button) {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    goNext();
                });
            });

            previousButtons.forEach(function (button) {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    goPrevious();
                });
            });

            innerTabButtons.forEach(function (button, buttonIndex) {
                button.addEventListener("click", function (event) {
                    if (buttonIndex > activeInnerTabIndex() && ! validateCurrentStep()) {
                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();

                        return;
                    }

                    activateInnerTab(buttonIndex);
                });
            });

            steps.forEach(function (step, stepIndex) {
                if (! step) {
                    return;
                }

                step.addEventListener("click", function () {
                    if (stepIndex > currentFieldset && ! validateGeneralStep()) {
                        return;
                    }

                    showFieldset(stepIndex, stepIndex === 0 ? activeInnerTabIndex() : undefined);
                });
            });

            showFieldset(currentFieldset, activeInnerTabIndex());
            setupApprovalRoutePreview(form);
        });
    });

    const setupApprovalRoutePreview = function (form) {
        const preview = form.querySelector("[data-approval-route-preview]");

        if (! preview) {
            return;
        }

        const list = preview.querySelector("[data-approval-route-list]");
        const emptyState = preview.querySelector("[data-approval-route-empty]");
        const emptyLabel = preview.getAttribute("data-empty-label") || "";
        const unassignedLabel = preview.getAttribute("data-unassigned-label") || "";
        const statusLabel = preview.getAttribute("data-status-label") || "";
        let rules = [];

        try {
            rules = JSON.parse(preview.getAttribute("data-rules") || "[]");
        } catch (error) {
            rules = [];
        }

        const valueFor = function (name) {
            const field = form.querySelector("[name='" + name + "']");

            return field ? String(field.value || "") : "";
        };

        const conditionValuesFor = function (key, value) {
            const values = value !== "" ? [value] : [];

            if (key === "project_nationalities" && value !== "" && value !== "jordanian" && value !== "international") {
                values.push("international");
            }

            return values;
        };

        const conditionMatches = function (conditions, key, value) {
            const allowed = Array.isArray(conditions[key]) ? conditions[key].filter(Boolean).map(String) : [];
            const actualValues = conditionValuesFor(key, value);

            return allowed.length === 0 || actualValues.some(function (actualValue) {
                return allowed.includes(actualValue);
            });
        };

        const render = function () {
            const values = {
                project_nationalities: valueFor("project_nationality"),
                work_categories: valueFor("work_category"),
                release_methods: valueFor("release_method"),
            };
            const seen = new Set();
            const matches = rules.filter(function (rule) {
                const conditions = rule.conditions || {};

                return conditionMatches(conditions, "project_nationalities", values.project_nationalities)
                    && conditionMatches(conditions, "work_categories", values.work_categories)
                    && conditionMatches(conditions, "release_methods", values.release_methods);
            }).filter(function (rule) {
                const key = String(rule.approval_code || "") + "|" + String(rule.target_entity_id || "none");

                if (seen.has(key)) {
                    return false;
                }

                seen.add(key);

                return true;
            });

            if (list) {
                list.innerHTML = "";
            }

            if (matches.length === 0) {
                if (emptyState) {
                    emptyState.textContent = emptyLabel;
                    emptyState.classList.remove("d-none");
                }

                return;
            }

            if (emptyState) {
                emptyState.classList.add("d-none");
            }

            matches.forEach(function (rule) {
                const item = document.createElement("li");
                const target = rule.target_entity_name || unassignedLabel;

                item.className = "border rounded bg-white p-3 mb-2 d-flex justify-content-between align-items-start gap-3 flex-wrap";
                item.innerHTML = [
                    "<span>",
                    "<span class=\"fw-600 d-block\">" + escapeHtml(rule.approval_label || rule.approval_code || "") + "</span>",
                    "<small class=\"text-muted\">" + escapeHtml(target) + "</small>",
                    "</span>",
                    "<span class=\"badge bg-primary\">" + escapeHtml(statusLabel) + "</span>",
                ].join("");

                if (list) {
                    list.appendChild(item);
                }
            });
        };

        ["project_nationality", "work_category", "release_method"].forEach(function (name) {
            const field = form.querySelector("[name='" + name + "']");

            if (field) {
                field.addEventListener("change", render);
                field.addEventListener("input", render);
            }
        });

        render();
    };

    const escapeHtml = function (value) {
        const wrapper = document.createElement("div");

        wrapper.textContent = String(value);

        return wrapper.innerHTML;
    };
})();
