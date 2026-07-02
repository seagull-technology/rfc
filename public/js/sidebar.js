/*
* Version: 5.4.0
* Template: Streamit - Responsive Bootstrap 5 Admin Dashboard Template
* Author: iqonic.design
* Author URL: https://iqonic.design/
* Design and Developed by: iqonic.design
* Description: This file contains the script for initialize & listener Template.
*/

(function(){
    "use strict";
    const responsiveSidebarBreakpoint = 1200

    const getSidebar = () => document.querySelector('[data-toggle="main-sidebar"]')

    function closeResponsiveSidebar(sidebar) {
        sidebar.classList.remove('sidebar-mobile-open')
        sidebar.classList.add('sidebar-mini')
        sidebar.dataset.responsiveMini = 'true'
    }

    function openResponsiveSidebar(sidebar) {
        sidebar.classList.add('sidebar-mobile-open')
        sidebar.classList.remove('sidebar-mini')
        delete sidebar.dataset.responsiveMini
    }

    function setSidebarTypePreference(isMini) {
        if(typeof IQSetting === typeof undefined) {
            return
        }

        const sidebarSetting = IQSetting.options?.setting?.sidebar_type
        if(!sidebarSetting || !Array.isArray(sidebarSetting.value)) {
            return
        }

        const newTypes = [...sidebarSetting.value]
        const indexOf = newTypes.findIndex(x => x == 'sidebar-mini')

        if(isMini && indexOf === -1) {
            newTypes.push('sidebar-mini')
        }

        if(!isMini && indexOf !== -1) {
            newTypes.splice(indexOf, 1)
        }

        IQSetting.sidebar_type(newTypes)
    }

    function setSidebarMini(isMini, persistPreference = true) {
        const sidebar = getSidebar()
        if(sidebar === null) {
            return
        }

        sidebar.classList.toggle('sidebar-mini', isMini)

        if(persistPreference) {
            setSidebarTypePreference(isMini)
        }
    }

    const sidebarInit = () => {
        const sidebarResponsive = document.querySelector('[data-sidebar="responsive"]')
        if (sidebarResponsive === null) {
            return
        }

        if (window.innerWidth < responsiveSidebarBreakpoint) {
            closeResponsiveSidebar(sidebarResponsive)

            return
        }

        sidebarResponsive.classList.remove('sidebar-mobile-open')

        if (sidebarResponsive.dataset.responsiveMini === 'true') {
            setSidebarMini(false, false)
            delete sidebarResponsive.dataset.responsiveMini
        }
    }
    sidebarInit()
    window.addEventListener('resize', function (event) {
        sidebarInit()
    });

    /*-------------Sidebar Toggle Start-----------------*/
    const sidebarToggle = (elem) => {
        elem.addEventListener('click', (e) => {
            const sidebar = getSidebar()
            if (sidebar === null) {
                return
            }

            const shouldMini = !sidebar.classList.contains('sidebar-mini')
            const isResponsiveViewport = window.innerWidth < responsiveSidebarBreakpoint

            if (isResponsiveViewport) {
                if (sidebar.classList.contains('sidebar-mobile-open')) {
                    closeResponsiveSidebar(sidebar)
                } else {
                    openResponsiveSidebar(sidebar)
                }

                return
            }

            sidebar.classList.remove('sidebar-mobile-open')
            setSidebarMini(shouldMini, !isResponsiveViewport)

            delete sidebar.dataset.responsiveMini
        })
    }
    const sidebarToggleBtn = document.querySelectorAll('[data-toggle="sidebar"]')
    const sidebar = document.querySelector('[data-toggle="main-sidebar"]')
    if (sidebar !== null) {
        const sidebarActiveItem = sidebar.querySelectorAll('.active')
        Array.from(sidebarActiveItem, (elem) => {
            elem.classList.add('active')
            if (!elem.closest('ul').classList.contains('iq-main-menu')) {
                const childMenu = elem.closest('ul')
                const parentMenu = childMenu.closest('li').querySelector('.nav-link')
                parentMenu.classList.add('active')
                new bootstrap.Collapse(childMenu, {
                toggle: true
                });
            }
        })
        const collapseElementList = [].slice.call(sidebar.querySelectorAll('.collapse'))
        const collapseList = collapseElementList.map(function (collapseEl) {
            collapseEl.addEventListener('show.bs.collapse', function (elem) {
                collapseEl.closest('li').classList.add('active')
            })
            collapseEl.addEventListener('hidden.bs.collapse', function (elem) {
                collapseEl.closest('li').classList.remove('active')
            })
        })

        const active = sidebar.querySelector('.active')
        if (active !== null) {
            active.closest('li').classList.add('active')
        }
    }
    Array.from(sidebarToggleBtn, (sidebarBtn) => {
        sidebarToggle(sidebarBtn)
    })
    /*-------------Sidebar Toggle End-----------------*/
})()
