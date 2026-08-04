<script data-navigate-once>
    ;(() => {
        const isTenantPanel = () => document.body.classList.contains('fi-panel-tenant')

        const isSettingsFooterGroup = (group) =>
            group instanceof HTMLElement && group.dataset.settingsFooter === '1'

        const sidebarGroups = () =>
            [...document.querySelectorAll('.fi-main-sidebar .fi-sidebar-group[data-group-label]')]

        const sidebarGroupLabels = () =>
            sidebarGroups()
                .filter((group) => ! isSettingsFooterGroup(group))
                .map((group) => group.dataset.groupLabel)
                .filter(Boolean)

        const settingsFooterLabel = () =>
            sidebarGroups().find((group) => isSettingsFooterGroup(group))?.dataset.groupLabel ?? null

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

        const keepSettingsFooterOpen = (sidebar) => {
            const label = settingsFooterLabel()

            if (! label || ! Array.isArray(sidebar.collapsedGroups)) {
                return
            }

            sidebar.collapsedGroups = sidebar.collapsedGroups.filter((item) => item !== label)
        }

        const openOnlySidebarGroup = (openGroup) => {
            if (! isTenantPanel()) {
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
            keepSettingsFooterOpen(sidebar)
        }

        const syncActiveSidebarGroup = () => {
            const activeGroup = document.querySelector(
                '.fi-main-sidebar .fi-sidebar-group.fi-active[data-group-label]',
            )?.dataset.groupLabel

            if (activeGroup && ! isSettingsFooterGroup(
                document.querySelector(`.fi-main-sidebar .fi-sidebar-group[data-group-label="${CSS.escape(activeGroup)}"]`)
            )) {
                openOnlySidebarGroup(activeGroup)
            } else {
                const sidebar = window.Alpine?.store('sidebar')

                if (sidebar) {
                    ensureCollapsedGroupsArray(sidebar)
                    keepSettingsFooterOpen(sidebar)
                }
            }
        }

        const installSidebarAccordion = () => {
            if (window.__tenantSidebarAccordionInstalled || ! isTenantPanel()) {
                return
            }

            window.__tenantSidebarAccordionInstalled = true

            document.addEventListener('click', (event) => {
                if (! isTenantPanel()) {
                    return
                }

                const toggle = event.target.closest(
                    '.fi-main-sidebar .fi-sidebar-group-button, .fi-main-sidebar .fi-sidebar-group-collapse-button',
                )

                if (! toggle) {
                    return
                }

                const group = toggle.closest('.fi-sidebar-group[data-group-label]')

                if (! group || isSettingsFooterGroup(group)) {
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
                    } else {
                        keepSettingsFooterOpen(sidebar)
                    }
                })
            })

            document.addEventListener('livewire:navigated', () => {
                queueMicrotask(syncActiveSidebarGroup)
            })
        }

        const moveSettingsNavToFooter = () => {
            if (! isTenantPanel()) {
                return
            }

            const navGroups = document.querySelector('.fi-main-sidebar .fi-sidebar-nav-groups')

            if (! navGroups) {
                return
            }

            const settingsLink = navGroups.querySelector('a[href*="settings/v2"]')

            if (! settingsLink) {
                return
            }

            const settingsItem = settingsLink.closest('.fi-sidebar-item')

            if (! settingsItem || settingsItem.dataset.movedToFooter === '1') {
                return
            }

            let footerGroup = navGroups.querySelector('[data-settings-footer="1"]')

            if (! footerGroup) {
                footerGroup = document.createElement('li')
                footerGroup.className = 'fi-sidebar-group flex flex-col gap-y-1'
                footerGroup.dataset.settingsFooter = '1'
                footerGroup.dataset.groupLabel = settingsLink.closest('.fi-sidebar-group[data-group-label]')?.dataset.groupLabel
                    ?? @json(__('fields.settings'))

                const itemsList = document.createElement('ul')
                itemsList.className = 'fi-sidebar-group-items flex flex-col gap-y-1'
                footerGroup.appendChild(itemsList)
                navGroups.appendChild(footerGroup)
            }

            footerGroup.querySelector('.fi-sidebar-group-items')?.appendChild(settingsItem)
            settingsItem.dataset.movedToFooter = '1'

            const sidebar = window.Alpine?.store('sidebar')

            if (sidebar) {
                ensureCollapsedGroupsArray(sidebar)
                keepSettingsFooterOpen(sidebar)
            }
        }

        const bootSidebarAccordion = () => {
            installSidebarAccordion()
            moveSettingsNavToFooter()
            syncActiveSidebarGroup()
            moveSettingsNavToFooter()
        }

        document.addEventListener('alpine:initialized', bootSidebarAccordion)

        document.addEventListener('livewire:navigated', () => {
            queueMicrotask(() => {
                moveSettingsNavToFooter()
                syncActiveSidebarGroup()
                moveSettingsNavToFooter()
            })
        })

        bootSidebarAccordion()
    })()
</script>
