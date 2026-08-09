<script data-navigate-once>
    ;(() => {
        const isAdminPanel = () => document.body.classList.contains('fi-panel-admin')

        const sidebarGroupLabels = () =>
            [...document.querySelectorAll('.fi-main-sidebar .fi-sidebar-group[data-group-label]')]
                .map((group) => group.dataset.groupLabel)
                .filter(Boolean)

        const ensureCollapsedGroupsArray = (sidebar) => {
            if (Array.isArray(sidebar.collapsedGroups)) {
                return sidebar.collapsedGroups
            }

            try {
                const stored = JSON.parse(localStorage.getItem('collapsedGroups') ?? '[]')

                sidebar.collapsedGroups = Array.isArray(stored) ? stored : []
            } catch {
                sidebar.collapsedGroups = []
            }

            return sidebar.collapsedGroups
        }

        const openOnlySidebarGroup = (openGroup) => {
            if (! isAdminPanel()) {
                return
            }

            const sidebar = window.Alpine?.store('sidebar')

            if (! sidebar || ! openGroup) {
                return
            }

            const labels = sidebarGroupLabels()

            if (labels.length === 0) {
                return
            }

            ensureCollapsedGroupsArray(sidebar)
            sidebar.collapsedGroups = labels.filter((label) => label !== openGroup)
        }

        const syncActiveSidebarGroup = () => {
            const activeGroup = document.querySelector(
                '.fi-main-sidebar .fi-sidebar-group.fi-active[data-group-label]',
            )?.dataset.groupLabel

            if (activeGroup) {
                openOnlySidebarGroup(activeGroup)
            }
        }

        const installSidebarAccordion = () => {
            if (window.__adminSidebarAccordionInstalled || ! isAdminPanel()) {
                return
            }

            window.__adminSidebarAccordionInstalled = true

            document.addEventListener('click', (event) => {
                if (! isAdminPanel()) {
                    return
                }

                const toggle = event.target.closest(
                    '.fi-main-sidebar .fi-sidebar-group-button, .fi-main-sidebar .fi-sidebar-group-collapse-button',
                )

                if (! toggle) {
                    return
                }

                const group = toggle.closest('.fi-sidebar-group[data-group-label]')

                if (! group) {
                    return
                }

                const label = group.dataset.groupLabel

                queueMicrotask(() => {
                    const sidebar = window.Alpine?.store('sidebar')

                    if (! sidebar) {
                        return
                    }

                    ensureCollapsedGroupsArray(sidebar)

                    if (! sidebar.collapsedGroups.includes(label)) {
                        openOnlySidebarGroup(label)
                    }
                })
            })

            document.addEventListener('livewire:navigated', () => {
                queueMicrotask(syncActiveSidebarGroup)
            })
        }

        const bootSidebarAccordion = () => {
            installSidebarAccordion()
            syncActiveSidebarGroup()
        }

        document.addEventListener('alpine:initialized', bootSidebarAccordion)

        document.addEventListener('livewire:navigated', () => {
            queueMicrotask(syncActiveSidebarGroup)
        })

        bootSidebarAccordion()
    })()
</script>
