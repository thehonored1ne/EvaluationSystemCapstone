<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark {{ request()->cookie('admin_sidebar_collapsed') === 'true' ? 'sidebar-is-collapsed' : '' }}">
    <head>
        @include('partials.head')
        <style>
            /* Lock viewport scrollbar gutter to prevent layout twitch / horizontal shift */
            html {
                scrollbar-gutter: stable;
            }

            /* Active Sidebar Item Dark Red (#9b0000) Styling */
            [data-flux-sidebar] [data-flux-navlist-item][data-current] {
                background-color: #9b0000 !important;
                color: #ffffff !important;
                border-color: #9b0000 !important;
            }
            [data-flux-sidebar] [data-flux-navlist-item][data-current] svg,
            [data-flux-sidebar] [data-flux-navlist-item][data-current] span,
            [data-flux-sidebar] [data-flux-navlist-item][data-current] div {
                color: #ffffff !important;
            }
            [data-flux-sidebar] [data-flux-navlist-item][data-current]:hover {
                background-color: #7a0000 !important;
                color: #ffffff !important;
            }

            .dark [data-flux-sidebar] [data-flux-navlist-item][data-current] {
                background-color: rgba(224, 122, 122, 0.12) !important;
                color: #e07a7a !important;
                border-color: rgba(224, 122, 122, 0.25) !important;
            }
            .dark [data-flux-sidebar] [data-flux-navlist-item][data-current] svg,
            .dark [data-flux-sidebar] [data-flux-navlist-item][data-current] span,
            .dark [data-flux-sidebar] [data-flux-navlist-item][data-current] div {
                color: #e07a7a !important;
            }
            .dark [data-flux-sidebar] [data-flux-navlist-item][data-current]:hover {
                background-color: rgba(224, 122, 122, 0.20) !important;
                color: #ea8c8c !important;
            }

            /* Sidebar Group Headings High-Contrast Accessibility */
            [data-flux-sidebar] [data-flux-navlist-group-heading],
            [data-flux-sidebar] [data-flux-navlist-group-heading] div {
                color: #52525b !important;
                font-weight: 700 !important;
            }
            .dark [data-flux-sidebar] [data-flux-navlist-group-heading],
            .dark [data-flux-sidebar] [data-flux-navlist-group-heading] div {
                color: #d4d4d8 !important;
                font-weight: 700 !important;
            }

            /* Sublist and Navigation item boundary constraints */
            [data-flux-sidebar] .sidebar-sublist {
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            [data-flux-sidebar] .sidebar-sublist [data-flux-navlist-item] {
                max-width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
            }
            [data-flux-sidebar] [data-flux-navlist-item] {
                box-sizing: border-box !important;
            }

            /* Livewire navigation progress bar */
            .livewire-progress-bar {
                background-color: #9b0000 !important;
                height: 2.5px !important;
            }

            /* Mini Icon-Only Collapsed Sidebar & Pure CSS Hover Flyout Styles (Desktop Only) */
            @media (min-width: 1024px) {
                [data-flux-sidebar] {
                    position: fixed !important;
                    top: 0 !important;
                    bottom: 0 !important;
                    left: 0 !important;
                    height: 100vh !important;
                    height: 100dvh !important;
                    z-index: 35 !important;
                    overflow-x: hidden !important;
                    width: 16rem !important;
                    min-width: 16rem !important;
                    box-sizing: border-box !important;
                    transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1),
                                padding 200ms ease,
                                box-shadow 200ms ease !important;
                }

                #sidebar-rail-wrapper {
                    width: 16rem;
                    flex-shrink: 0;
                }



                [data-flux-sidebar] [data-flux-navlist-item] {
                    white-space: nowrap !important;
                }

                /* When pinned-collapsed: the flex rail spacer shrinks to 4.25rem */
                :is(html.sidebar-is-collapsed, .sidebar-is-collapsed) #sidebar-rail-wrapper {
                    width: 4.25rem !important;
                    min-width: 4.25rem !important;
                    max-width: 4.25rem !important;
                }

                /* When pinned-collapsed: default mini rail state */
                :is(html.sidebar-is-collapsed, .sidebar-is-collapsed) [data-flux-sidebar] {
                    width: 4.25rem !important;
                    min-width: 4.25rem !important;
                    padding-left: 0.75rem !important;
                    padding-right: 0.75rem !important;
                    align-items: stretch !important;
                    transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1) 250ms,
                                box-shadow 200ms ease 250ms !important;
                }

                /* Invariant Logo Section: Equal height and vertical positioning across both states */
                [data-flux-sidebar] .sidebar-logo-link {
                    position: relative !important;
                    height: 5.25rem !important;
                    min-height: 5.25rem !important;
                    max-height: 5.25rem !important;
                    padding: 0 !important;
                    margin-top: 0 !important;
                    margin-bottom: 0.5rem !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    box-sizing: border-box !important;
                    flex-shrink: 0 !important;
                }

                [data-flux-sidebar] .sidebar-logo-link .sidebar-big-logo {
                    height: 100% !important;
                    width: 100% !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                }

                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) .sidebar-logo-link .sidebar-big-logo {
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                    transition: opacity 150ms ease 250ms, visibility 150ms ease 250ms !important;
                }

                html.sidebar-is-collapsed [data-flux-sidebar]:hover .sidebar-logo-link .sidebar-big-logo,
                html:not(.sidebar-is-collapsed) [data-flux-sidebar] .sidebar-logo-link .sidebar-big-logo {
                    opacity: 1 !important;
                    visibility: visible !important;
                    pointer-events: auto !important;
                    transition: opacity 150ms ease 250ms, visibility 150ms ease 250ms !important;
                }

                :is(html.sidebar-hover-active.sidebar-is-collapsed, html.sidebar-hover-active .sidebar-is-collapsed) [data-flux-sidebar] .sidebar-logo-link .sidebar-big-logo {
                    opacity: 1 !important;
                    visibility: visible !important;
                    pointer-events: auto !important;
                    transition: none !important;
                }

                [data-flux-sidebar] .sidebar-logo-link .sidebar-small-logo {
                    display: flex !important;
                    position: absolute !important;
                    left: 22px !important;
                    top: 50% !important;
                    transform: translate(-50%, -50%) !important;
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                    margin: 0 auto !important;
                }

                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) .sidebar-logo-link .sidebar-small-logo {
                    display: flex !important;
                    position: absolute !important;
                    left: 22px !important;
                    top: 50% !important;
                    transform: translate(-50%, -50%) !important;
                    opacity: 1 !important;
                    visibility: visible !important;
                    transition: opacity 120ms ease 250ms, visibility 120ms ease 250ms !important;
                }

                html.sidebar-is-collapsed [data-flux-sidebar]:hover .sidebar-logo-link .sidebar-small-logo {
                    display: flex !important;
                    position: absolute !important;
                    left: 22px !important;
                    top: 50% !important;
                    transform: translate(-50%, -50%) !important;
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                    transition: opacity 120ms ease 250ms, visibility 120ms ease 250ms !important;
                }

                :is(html.sidebar-hover-active.sidebar-is-collapsed, html.sidebar-hover-active .sidebar-is-collapsed) [data-flux-sidebar] .sidebar-logo-link .sidebar-small-logo {
                    display: flex !important;
                    position: absolute !important;
                    left: 22px !important;
                    top: 50% !important;
                    transform: translate(-50%, -50%) !important;
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                    transition: none !important;
                }

                /* Shift-Free Navigation Item Geometry (Gmail Model):
                   1. flex-start alignment across both collapsed and expanded states (never toggles justify-content).
                   2. Invariant 2.5rem (40px) leading icon slot centered around X=34px.
                   3. Item expands smoothly to the right with 0px horizontal icon shift. */
                [data-flux-sidebar] [data-flux-navlist-item] {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: flex-start !important;
                    box-sizing: border-box !important;
                    height: 2.5rem !important;
                    margin-left: 2px !important;
                    margin-right: 2px !important;
                    padding: 0 !important;
                    border: none !important;
                    white-space: nowrap !important;
                    transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1) 250ms,
                                border-radius 200ms ease 250ms,
                                background-color 150ms ease !important;
                }

                /* Invariant 2.5rem (40px) Centered Leading Icon Slot */
                [data-flux-sidebar] [data-flux-navlist-item] > .relative {
                    width: 2.5rem !important;
                    min-width: 2.5rem !important;
                    max-width: 2.5rem !important;
                    height: 2.5rem !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    flex-shrink: 0 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                /* Universal ui-tooltip normalization: Prevents inline collapse or sibling line-wrap shifts */
                [data-flux-sidebar] ui-tooltip {
                    display: block !important;
                    width: 100% !important;
                    max-width: 100% !important;
                    min-width: 0 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    box-sizing: border-box !important;
                }

                /* Container Constraints: Prevent any ancestor or custom wrapper from expanding wider than the 4.25rem rail */
                [data-flux-sidebar] [data-flux-navlist],
                [data-flux-sidebar] [data-flux-navlist-group],
                [data-flux-sidebar] .grid,
                [data-flux-sidebar] .grid > div,
                [data-flux-sidebar] .w-full {
                    width: 100% !important;
                    max-width: 100% !important;
                    min-width: 0 !important;
                    box-sizing: border-box !important;
                }


                /* Collapsed Mini Rail: Item width locks to 2.5rem (40px) rounded-lg box */
                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) [data-flux-navlist-item] {
                    width: 2.5rem !important;
                    min-width: 2.5rem !important;
                    border-radius: 0.625rem !important;
                    overflow: hidden !important;
                }

                /* Expanded / Hover Mode: Item expands to fill sidebar width toward the right */
                [data-flux-sidebar]:hover [data-flux-navlist-item],
                html:not(.sidebar-is-collapsed) [data-flux-sidebar] [data-flux-navlist-item],
                html.sidebar-hover-active [data-flux-sidebar] [data-flux-navlist-item] {
                    width: calc(100% - 4px) !important;
                    padding-right: 0.75rem !important;
                    border-radius: 0.5rem !important;
                    transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1) 250ms,
                                border-radius 200ms ease 250ms,
                                background-color 150ms ease !important;
                }

                [data-flux-sidebar]:hover [data-flux-navlist-item] > [data-content],
                html:not(.sidebar-is-collapsed) [data-flux-sidebar] [data-flux-navlist-item] > [data-content],
                html.sidebar-hover-active [data-flux-sidebar] [data-flux-navlist-item] > [data-content],
                [data-flux-sidebar]:hover [data-flux-navlist-item] > div:not(.relative),
                html:not(.sidebar-is-collapsed) [data-flux-sidebar] [data-flux-navlist-item] > div:not(.relative),
                html.sidebar-hover-active [data-flux-sidebar] [data-flux-navlist-item] > div:not(.relative),
                [data-flux-sidebar]:hover [data-flux-badge],
                html:not(.sidebar-is-collapsed) [data-flux-sidebar] [data-flux-badge],
                html.sidebar-hover-active [data-flux-sidebar] [data-flux-badge],
                [data-flux-sidebar]:hover svg.transition-transform,
                html:not(.sidebar-is-collapsed) [data-flux-sidebar] svg.transition-transform,
                html.sidebar-hover-active [data-flux-sidebar] svg.transition-transform {
                    display: flex !important;
                    align-items: center !important;
                    opacity: 1 !important;
                    visibility: visible !important;
                    transition: opacity 150ms ease 250ms, visibility 150ms ease 250ms !important;
                }

                /* Persistent Group Heading Geometry: Preserves vertical spacing in collapsed state */
                [data-flux-sidebar] [data-flux-navlist-group-heading] {
                    height: 1.75rem !important;
                    min-height: 1.75rem !important;
                    display: flex !important;
                    align-items: center !important;
                    padding-left: 0.625rem !important;
                    padding-right: 0.625rem !important;
                    margin-top: 0.5rem !important;
                    box-sizing: border-box !important;
                }

                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) [data-flux-navlist-group-heading] > div {
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                    transition: opacity 150ms ease 250ms, visibility 150ms ease 250ms !important;
                }

                :is(html.sidebar-is-collapsed, .sidebar-is-collapsed) [data-flux-sidebar]:hover [data-flux-navlist-group-heading] > div,
                :not(.sidebar-is-collapsed) [data-flux-sidebar] [data-flux-navlist-group-heading] > div {
                    opacity: 1 !important;
                    visibility: visible !important;
                    transition: opacity 150ms ease 250ms, visibility 150ms ease 250ms !important;
                }

                :is(html.sidebar-hover-active.sidebar-is-collapsed, html.sidebar-hover-active .sidebar-is-collapsed) [data-flux-sidebar] [data-flux-navlist-group-heading] > div {
                    opacity: 1 !important;
                    visibility: visible !important;
                    transition: none !important;
                }

                /* When pinned-collapsed AND NOT HOVERED AND NOT HOVER-ACTIVE: hide text labels, badges, and sublists with delayed fade */
                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) [data-flux-navlist-item] > [data-content],
                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) [data-flux-navlist-item] > div:not(.relative),
                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) .text-zinc-400:not([data-flux-navlist-group-heading] *),
                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) .sidebar-text,
                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) svg.transition-transform,
                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) [data-flux-badge] {
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                    transition: opacity 150ms ease 250ms, visibility 150ms ease 250ms !important;
                }

                html.sidebar-is-collapsed:not(.sidebar-hover-active) [data-flux-sidebar]:not(:hover) .sidebar-sublist {
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                    max-height: 0 !important;
                    overflow: hidden !important;
                    transition: opacity 150ms ease 250ms, visibility 150ms ease 250ms, max-height 200ms ease 250ms !important;
                }

                /* WHEN PINNED-COLLAPSED AND HOVERED: PURE CSS FLOATING FLYOUT OVERLAY! */
                :is(html.sidebar-is-collapsed, .sidebar-is-collapsed) [data-flux-sidebar]:hover {
                    width: 16rem !important;
                    min-width: 4.25rem !important;
                    max-width: 16rem !important;
                    padding-left: 0.75rem !important;
                    padding-right: 0.75rem !important;
                    align-items: stretch !important;
                    z-index: 45 !important;
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
                    transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1) 250ms,
                                box-shadow 200ms ease 250ms !important;
                }

                /* BRIDGE STATE: User was hovering when navigating across pages */
                :is(html.sidebar-hover-active.sidebar-is-collapsed, html.sidebar-hover-active .sidebar-is-collapsed) [data-flux-sidebar] {
                    width: 16rem !important;
                    min-width: 16rem !important;
                    padding-left: 0.75rem !important;
                    padding-right: 0.75rem !important;
                    align-items: stretch !important;
                    z-index: 45 !important;
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
                    transition: none !important;
                }

                :is(html.sidebar-hover-active.sidebar-is-collapsed, html.sidebar-hover-active .sidebar-is-collapsed) [data-flux-sidebar] [data-flux-navlist-item] {
                    width: calc(100% - 4px) !important;
                    padding-right: 0.75rem !important;
                    border-radius: 0.5rem !important;
                    transition: none !important;
                }

                :is(html.sidebar-hover-active.sidebar-is-collapsed, html.sidebar-hover-active .sidebar-is-collapsed) [data-flux-sidebar] [data-flux-navlist-item] > [data-content],
                :is(html.sidebar-hover-active.sidebar-is-collapsed, html.sidebar-hover-active .sidebar-is-collapsed) [data-flux-sidebar] [data-flux-navlist-item] > div:not(.relative) {
                    display: flex !important;
                    align-items: center !important;
                    opacity: 1 !important;
                    transition: none !important;
                }

                .dark :is(html.sidebar-is-collapsed, .sidebar-is-collapsed) [data-flux-sidebar]:hover,
                :is(html.sidebar-hover-active.sidebar-is-collapsed, html.sidebar-hover-active .sidebar-is-collapsed).dark [data-flux-sidebar],
                .dark :is(html.sidebar-hover-active.sidebar-is-collapsed, html.sidebar-hover-active .sidebar-is-collapsed) [data-flux-sidebar] {
                    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(255, 255, 255, 0.05) !important;
                }

                /* Suppress floating tooltip popups in normal expanded OR hovered mode */
                html:not(.sidebar-is-collapsed) ui-tooltip > [popover],
                [data-flux-sidebar]:hover ui-tooltip > [popover],
                html.sidebar-hover-active ui-tooltip > [popover],
                html:not(.sidebar-is-collapsed) [data-flux-tooltip-popup],
                [data-flux-sidebar]:hover [data-flux-tooltip-popup],
                html.sidebar-hover-active [data-flux-tooltip-popup] {
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                }

                /* MANUAL TOGGLE BUTTON CLICK ANIMATION (0ms Delay Override) */
                body.sidebar-animating #sidebar-rail-wrapper {
                    transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1) 0ms !important;
                }

                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover),
                body.sidebar-animating :is(html.sidebar-is-collapsed, .sidebar-is-collapsed) [data-flux-sidebar],
                body.sidebar-animating [data-flux-sidebar] {
                    transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1) 0ms,
                                box-shadow 200ms ease 0ms,
                                padding 200ms ease 0ms !important;
                }

                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover) .sidebar-logo-link .sidebar-big-logo,
                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover) .sidebar-logo-link .sidebar-small-logo,
                body.sidebar-animating [data-flux-sidebar] .sidebar-logo-link .sidebar-big-logo,
                body.sidebar-animating [data-flux-sidebar] .sidebar-logo-link .sidebar-small-logo,
                body.sidebar-animating :is(html.sidebar-is-collapsed, .sidebar-is-collapsed) .sidebar-logo-link .sidebar-big-logo,
                body.sidebar-animating :is(html.sidebar-is-collapsed, .sidebar-is-collapsed) .sidebar-logo-link .sidebar-small-logo {
                    transition: opacity 150ms ease 0ms, visibility 150ms ease 0ms !important;
                }

                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover) [data-flux-navlist-group-heading] > div,
                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover) [data-flux-navlist-item],
                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover) [data-flux-navlist-item] > [data-content],
                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover) [data-flux-navlist-item] > div:not(.relative),
                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover) [data-flux-badge],
                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover) svg.transition-transform,
                html.sidebar-is-collapsed:not(.sidebar-hover-active) body.sidebar-animating [data-flux-sidebar]:not(:hover) .sidebar-sublist,
                body.sidebar-animating [data-flux-navlist-group-heading] > div,
                body.sidebar-animating [data-flux-navlist-item],
                body.sidebar-animating [data-flux-navlist-item] > [data-content],
                body.sidebar-animating [data-flux-navlist-item] > div:not(.relative),
                body.sidebar-animating [data-flux-badge],
                body.sidebar-animating svg.transition-transform,
                body.sidebar-animating .sidebar-sublist {
                    transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1) 0ms,
                                border-radius 200ms ease 0ms,
                                opacity 150ms ease 0ms,
                                visibility 150ms ease 0ms,
                                max-height 200ms ease 0ms !important;
                }
            }
        </style>
        <script>
            let lastMouseX = -1;
            let lastMouseY = -1;
            window.addEventListener('mousemove', function(e) {
                lastMouseX = e.clientX;
                lastMouseY = e.clientY;

                if (document.documentElement.classList.contains('sidebar-hover-active')) {
                    const sidebar = document.querySelector('[data-flux-sidebar]');
                    if (sidebar) {
                        const rect = sidebar.getBoundingClientRect();
                        if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
                            document.documentElement.classList.remove('sidebar-hover-active');
                        }
                    } else {
                        document.documentElement.classList.remove('sidebar-hover-active');
                    }
                }
            }, { passive: true });

            // Lock the bridge state on click/navigation inside the sidebar to prevent flicker
            document.addEventListener('pointerdown', function(e) {
                if (window.innerWidth >= 1024 && localStorage.getItem('admin_sidebar_collapsed') === 'true') {
                    const sidebar = document.querySelector('[data-flux-sidebar]');
                    if (sidebar && sidebar.contains(e.target)) {
                        document.documentElement.classList.add('sidebar-hover-active');
                    }
                }
            }, { passive: true });

            document.addEventListener('mouseout', function(e) {
                if (document.documentElement.classList.contains('sidebar-hover-active')) {
                    const sidebar = document.querySelector('[data-flux-sidebar]');
                    if (sidebar && (!e.relatedTarget || !sidebar.contains(e.relatedTarget))) {
                        document.documentElement.classList.remove('sidebar-hover-active');
                    }
                }
            }, { passive: true });

            (function() {
                const isCollapsed = localStorage.getItem('admin_sidebar_collapsed') === 'true';
                if (window.innerWidth >= 1024 && isCollapsed) {
                    document.documentElement.classList.add('sidebar-is-collapsed');
                }
                if (isCollapsed && !document.cookie.includes('admin_sidebar_collapsed=')) {
                    document.cookie = 'admin_sidebar_collapsed=true; path=/; max-age=31536000; SameSite=Lax';
                }

                if (window.MutationObserver) {
                    new MutationObserver(() => {
                        if (window.innerWidth >= 1024 && localStorage.getItem('admin_sidebar_collapsed') === 'true') {
                            if (!document.documentElement.classList.contains('sidebar-is-collapsed')) {
                                document.documentElement.classList.add('sidebar-is-collapsed');
                            }
                        }
                    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                }
            })();

            function updateActiveSidebarItem() {
                const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
                const currentUrl = window.location.href;
                const currentSearch = window.location.search;

                document.querySelectorAll('[data-flux-sidebar] [data-flux-navlist-item]').forEach(item => {
                    const href = item.getAttribute('href');
                    if (!href) return;

                    try {
                        const targetUrl = new URL(href, window.location.origin);
                        const targetPath = targetUrl.pathname.replace(/\/$/, '') || '/';
                        
                        let isMatch = false;
                        if (targetUrl.search) {
                            isMatch = (targetPath === currentPath) && (targetUrl.search === currentSearch);
                        } else {
                            isMatch = (targetPath === currentPath) && (!currentSearch || currentPath === targetPath);
                        }

                        if (isMatch) {
                            item.setAttribute('data-current', '');
                        } else {
                            item.removeAttribute('data-current');
                        }
                    } catch (e) {}
                });

                const manageUsersItem = document.querySelector('[data-flux-sidebar] [aria-label="Manage Users"]');
                if (manageUsersItem) {
                    if (currentPath === '/admin/employees' || currentPath === '/admin/students') {
                        manageUsersItem.setAttribute('data-current', '');
                    } else {
                        manageUsersItem.removeAttribute('data-current');
                    }
                }
            }

            document.addEventListener('livewire:navigated', function () {
                if (window.innerWidth >= 1024) {
                    if (localStorage.getItem('admin_sidebar_collapsed') === 'true') {
                        document.documentElement.classList.add('sidebar-is-collapsed');
                        if (document.body) {
                            document.body.classList.add('sidebar-is-collapsed');
                        }

                        // Check if cursor is still resting inside the sidebar bounds after navigation
                        const sidebar = document.querySelector('[data-flux-sidebar]');
                        if (sidebar && lastMouseX >= 0 && lastMouseY >= 0) {
                            const rect = sidebar.getBoundingClientRect();
                            const isInside = (lastMouseX >= rect.left && lastMouseX <= (rect.left + 256) &&
                                              lastMouseY >= rect.top && lastMouseY <= rect.bottom);
                            if (isInside) {
                                document.documentElement.classList.add('sidebar-hover-active');
                            } else {
                                document.documentElement.classList.remove('sidebar-hover-active');
                            }
                        }
                    } else {
                        document.documentElement.classList.remove('sidebar-is-collapsed');
                        document.documentElement.classList.remove('sidebar-hover-active');
                        if (document.body) {
                            document.body.classList.remove('sidebar-is-collapsed');
                        }
                    }
                } else {
                    const sidebar = document.querySelector('[data-flux-sidebar]');
                    if (sidebar && sidebar.hasAttribute('data-open')) {
                        sidebar.removeAttribute('data-open');
                    }
                }

                updateActiveSidebarItem();
            });

            document.addEventListener('livewire:navigating', function () {
                if (window.innerWidth >= 1024 && localStorage.getItem('admin_sidebar_collapsed') === 'true') {
                    const sidebar = document.querySelector('[data-flux-sidebar]');
                    if (sidebar && lastMouseX >= 0 && lastMouseY >= 0) {
                        const rect = sidebar.getBoundingClientRect();
                        const isInside = (lastMouseX >= rect.left && lastMouseX <= (rect.left + 256) &&
                                          lastMouseY >= rect.top && lastMouseY <= rect.bottom);
                        if (isInside) {
                            document.documentElement.classList.add('sidebar-hover-active');
                        }
                    }
                }
            });

            document.addEventListener('livewire:init', function () {
                Livewire.hook('morph.updating', ({ el, toEl }) => {
                    if (el === document.body || el === document.documentElement) {
                        if (localStorage.getItem('admin_sidebar_collapsed') === 'true') {
                            toEl.classList.add('sidebar-is-collapsed');
                        }
                        if (document.documentElement.classList.contains('sidebar-hover-active')) {
                            toEl.classList.add('sidebar-hover-active');
                        }
                    }
                });

                Livewire.hook('request', ({ fail }) => {
                    fail(({ status, preventDefault }) => {
                        if (!navigator.onLine || status === 0 || status === null) {
                            preventDefault();
                        }
                    });
                });
            });
        </script>
    </head>
    <body 
        x-data="{ 
            isOffline: !navigator.onLine,
            sidebarCollapsed: window.innerWidth >= 1024 && localStorage.getItem('admin_sidebar_collapsed') === 'true',
            toggle() {
                if (window.innerWidth >= 1024) {
                    document.body.classList.add('sidebar-animating');
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    localStorage.setItem('admin_sidebar_collapsed', this.sidebarCollapsed);
                    document.cookie = 'admin_sidebar_collapsed=' + this.sidebarCollapsed + '; path=/; max-age=31536000; SameSite=Lax';
                    if (this.sidebarCollapsed) {
                        document.documentElement.classList.add('sidebar-is-collapsed');
                        document.body.classList.add('sidebar-is-collapsed');
                    } else {
                        document.documentElement.classList.remove('sidebar-is-collapsed');
                        document.body.classList.remove('sidebar-is-collapsed');
                    }
                    setTimeout(() => {
                        document.body.classList.remove('sidebar-animating');
                    }, 250);
                }
            }
        }" 
        x-on:online.window="isOffline = false"
        x-on:offline.window="isOffline = true"
        x-on:livewire:navigated.window="isOffline = !navigator.onLine; sidebarCollapsed = (window.innerWidth >= 1024 && localStorage.getItem('admin_sidebar_collapsed') === 'true');"
        @toggle-sidebar.window="toggle()" 
        :class="{
            'sidebar-is-collapsed': sidebarCollapsed
        }"
        class="min-h-screen bg-[#fafafa] dark:bg-[#111113] {{ request()->cookie('admin_sidebar_collapsed') === 'true' ? 'sidebar-is-collapsed' : '' }}"
    >
        <!-- Global Passive Offline Detection Banner -->
        <div 
            x-show="isOffline"
            x-cloak
            role="status"
            aria-live="polite"
            class="fixed top-0 inset-x-0 z-[100] w-full bg-amber-500 text-amber-950 font-bold px-4 py-2.5 text-xs sm:text-sm text-center shadow-lg flex items-center justify-center gap-2 print:hidden"
        >
            <flux:icon icon="wifi" class="size-4 shrink-0 text-amber-950" />
            <span>You are currently offline. Changes and submissions will not sync until your internet connection is restored.</span>
        </div>

        <div class="flex min-h-screen w-full">
            <!-- Desktop Layout Spacer: Holds the flex space without moving on hover -->
            <div 
                id="sidebar-rail-wrapper"
                wire:ignore
                class="max-lg:contents lg:shrink-0 lg:relative print:hidden"
            >
                <flux:sidebar 
                    sticky 
                    stashable 
                    class="border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#18181b] shrink-0 print:hidden max-lg:!z-50"
                >
                    <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

                    <!-- Logo Section: Full logo on mobile and desktop-expanded; Small Icon ONLY when collapsed on desktop (lg:) -->
                    <a href="{{ route('dashboard') }}" class="sidebar-logo-link flex items-center justify-center w-full px-1 py-2 mb-2 shrink-0" aria-label="Dashboard Home" wire:navigate>
                        <!-- Big Logo: Shown by default, hidden when html.sidebar-is-collapsed:not(.sidebar-hover-expanded) -->
                        <div class="sidebar-big-logo w-full flex items-center justify-center">
                            <x-app-logo class="w-full"></x-app-logo>
                        </div>
                        <!-- Small Logo Icon: Desktop only, shown when html.sidebar-is-collapsed:not(.sidebar-hover-expanded) -->
                        <div class="sidebar-small-logo hidden items-center justify-center p-1.5 rounded-xl bg-red-950/10 dark:bg-red-950/30 border border-red-900/20 text-[#9b0000] dark:text-[#e07a7a]">
                            <x-app-logo-icon class="size-7 text-[#9b0000] dark:text-[#e07a7a] fill-current"></x-app-logo-icon>
                        </div>
                    </a>

                <flux:navlist variant="outline">
                    @php
                        $user = auth()->user();
                        $dashboardRoute = match(true) {
                            $user->hasRole('admin') => 'admin.dashboard',
                            $user->hasRole('dean') => 'dean.dashboard',
                            $user->hasRole('department head') => 'department-head.dashboard',
                            $user->hasRole('program head') => 'program-head.dashboard',
                            $user->hasRole('faculty') => 'faculty.dashboard',
                            $user->hasRole('student') => 'student.dashboard',
                            $user->hasRole('staff') => 'staff.dashboard',
                            default => 'dashboard',
                        };
                    @endphp

                    <!-- Dashboard (Admin only) -->
                    @if($user->hasRole('admin'))
                        <flux:navlist.group heading="Overview" class="grid">
                            <flux:tooltip content="Dashboard" position="right">
                                <flux:navlist.item icon="home" :href="route($dashboardRoute)" :current="request()->routeIs($dashboardRoute)" aria-label="Dashboard" wire:navigate>Dashboard</flux:navlist.item>
                            </flux:tooltip>
                        </flux:navlist.group>
                    @endif

                    <!-- Management (Admin only) -->
                    @if($user->hasRole('admin'))
                        <flux:navlist.group heading="Management" class="grid">
                            <div x-data="{ open: {{ request()->routeIs('admin.employees', 'admin.students') ? 'true' : 'false' }} }" class="w-full">
                                <flux:tooltip content="Manage Users" position="right">
                                    <flux:navlist.item 
                                        icon="users" 
                                        as="button"
                                        @click.prevent="open = !open" 
                                        :current="request()->routeIs('admin.employees', 'admin.students')"
                                        aria-label="Manage Users"
                                        class="cursor-pointer w-full text-left"
                                    >
                                        <div class="flex justify-between items-center w-full">
                                            <span>Manage Users</span>
                                            <flux:icon icon="chevron-down" class="size-4 shrink-0 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                        </div>
                                    </flux:navlist.item>
                                </flux:tooltip>

                                <div 
                                    x-cloak 
                                    x-show="open" 
                                    style="display: {{ request()->routeIs('admin.employees', 'admin.students') ? 'flex' : 'none' }};"
                                    class="sidebar-sublist pl-6 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-700 ml-3.5 mt-1 mb-2"
                                >
                                    <flux:navlist.item :href="route('admin.employees')" :current="request()->routeIs('admin.employees')" aria-label="Employees" wire:navigate class="text-xs">Employees</flux:navlist.item>
                                    <flux:navlist.item :href="route('admin.students')" :current="request()->routeIs('admin.students')" aria-label="Students" wire:navigate class="text-xs">Students</flux:navlist.item>
                                </div>
                            </div>
                            <flux:tooltip content="Subjects" position="right">
                                <flux:navlist.item icon="book-open" :href="route('admin.subjects')" :current="request()->routeIs('admin.subjects')" aria-label="Subjects" wire:navigate>Subjects</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Classes" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('admin.classes')" :current="request()->routeIs('admin.classes')" aria-label="Classes" wire:navigate>Classes</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Departments" position="right">
                                <flux:navlist.item icon="building-office-2" :href="route('admin.departments')" :current="request()->routeIs('admin.departments')" aria-label="Departments" wire:navigate>Departments</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Programs" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('admin.programs')" :current="request()->routeIs('admin.programs')" aria-label="Programs" wire:navigate>Programs</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Evaluation Settings" position="right">
                                <flux:navlist.item icon="cog-6-tooth" :href="route('admin.evaluation-settings')" :current="request()->routeIs('admin.evaluation-settings')" aria-label="Evaluation Settings" wire:navigate>Evaluation Settings</flux:navlist.item>
                            </flux:tooltip>
                        </flux:navlist.group>
                    @endif

                    <!-- Evaluations (All roles) -->
                    <flux:navlist.group heading="Evaluations" class="grid">
                        @if($user->hasRole('admin'))
                            <flux:tooltip content="Completion Tracking" position="right">
                                <flux:navlist.item icon="clipboard-document-check" :href="route('manage-evaluations')" :current="request()->routeIs('manage-evaluations')" aria-label="Completion Tracking" wire:navigate>Completion Tracking</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasRole('student'))
                            <flux:tooltip content="Evaluate Professors" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard')" aria-label="Evaluate Professors" wire:navigate>Evaluate Professors</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasRole('faculty'))
                            <flux:tooltip content="Self" position="right">
                                <flux:navlist.item icon="user" :href="route('faculty.dashboard', ['tab' => 'self'])" :current="request()->routeIs('faculty.dashboard') && (request('tab') === 'self' || !request('tab'))" aria-label="Self" wire:navigate>Self</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Peer Professor" position="right">
                                <flux:navlist.item icon="users" :href="route('faculty.dashboard', ['tab' => 'peer'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'peer'" aria-label="Peer Professor" wire:navigate>Peer Professor</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Program Head" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('faculty.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'supervisor'" aria-label="Program Head" wire:navigate>Program Head</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasRole('staff'))
                            <flux:tooltip content="Self" position="right">
                                <flux:navlist.item icon="user" :href="route('staff.dashboard', ['tab' => 'self'])" :current="request()->routeIs('staff.dashboard') && (request('tab') === 'self' || !request('tab'))" aria-label="Self" wire:navigate>Self</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Peer Staff" position="right">
                                <flux:navlist.item icon="users" :href="route('staff.dashboard', ['tab' => 'peer'])" :current="request()->routeIs('staff.dashboard') && request('tab') === 'peer'" aria-label="Peer Staff" wire:navigate>Peer Staff</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Department Head" position="right">
                                <flux:navlist.item icon="building-office-2" :href="route('staff.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('staff.dashboard') && request('tab') === 'supervisor'" aria-label="Department Head" wire:navigate>Department Head</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasRole('program head'))
                            <flux:tooltip content="Self" position="right">
                                <flux:navlist.item icon="user" :href="route('program-head.dashboard', ['tab' => 'self'])" :current="request()->routeIs('program-head.dashboard') && (request('tab') === 'self' || !request('tab'))" aria-label="Self" wire:navigate>Self</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Faculty" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('program-head.dashboard', ['tab' => 'faculty'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'faculty'" aria-label="Faculty" wire:navigate>Faculty</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Dean" position="right">
                                <flux:navlist.item icon="building-library" :href="route('program-head.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'supervisor'" aria-label="Dean" wire:navigate>Dean</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasRole('department head'))
                            <flux:tooltip content="Self" position="right">
                                <flux:navlist.item icon="user" :href="route('department-head.dashboard', ['tab' => 'self'])" :current="request()->routeIs('department-head.dashboard') && (request('tab') === 'self' || !request('tab'))" aria-label="Self" wire:navigate>Self</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Staff" position="right">
                                <flux:navlist.item icon="user-group" :href="route('department-head.dashboard', ['tab' => 'staff'])" :current="request()->routeIs('department-head.dashboard') && request('tab') === 'staff'" aria-label="Staff" wire:navigate>Staff</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Dean" position="right">
                                <flux:navlist.item icon="building-library" :href="route('department-head.dashboard', ['tab' => 'dean'])" :current="request()->routeIs('department-head.dashboard') && request('tab') === 'dean'" aria-label="Dean" wire:navigate>Dean</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasRole('dean'))
                            <flux:tooltip content="Self" position="right">
                                <flux:navlist.item icon="user" :href="route('dean.dashboard', ['tab' => 'self'])" :current="request()->routeIs('dean.dashboard') && (request('tab') === 'self' || !request('tab'))" aria-label="Self" wire:navigate>Self</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Faculty" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('dean.dashboard', ['tab' => 'faculty'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'faculty'" aria-label="Faculty" wire:navigate>Faculty</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Program Heads" position="right">
                                <flux:navlist.item icon="user-group" :href="route('dean.dashboard', ['tab' => 'program-heads'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'program-heads'" aria-label="Program Heads" wire:navigate>Program Heads</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasRole('admin'))
                            <flux:tooltip content="Question Builder" position="right">
                                <flux:navlist.item icon="clipboard-document-list" :href="route('admin.questions')" :current="request()->routeIs('admin.questions')" aria-label="Question Builder" wire:navigate>Question Builder</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasAnyRole(['admin', 'dean']))
                            <flux:tooltip content="Results" position="right">
                                <flux:navlist.item icon="check-badge" :href="route('evaluation-results')" :current="request()->routeIs('evaluation-results')" aria-label="Results" wire:navigate>Results</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                            <flux:tooltip content="Rankings" position="right">
                                <flux:navlist.item icon="trophy" :href="route('rankings')" :current="request()->routeIs('rankings')" aria-label="Rankings" wire:navigate>Rankings</flux:navlist.item>
                            </flux:tooltip>
                        @endif
                    </flux:navlist.group>

                    <!-- Analytics & Reports (Admin, Dean, Program Head) -->
                    @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                        <flux:navlist.group heading="Reports & Tools" class="grid">
                            @if($user->hasRole('admin') && ($user->show_ai_pipeline ?? true))
                                <flux:tooltip content="AI Pipeline" position="right">
                                    <flux:navlist.item icon="beaker" :href="route('admin.ai')" :current="request()->routeIs('admin.ai')" aria-label="AI Pipeline" wire:navigate>AI Pipeline</flux:navlist.item>
                                </flux:tooltip>
                            @endif
                            
                            <flux:tooltip content="Reports" position="right">
                                <flux:navlist.item icon="document-chart-bar" :href="route('reports')" :current="request()->routeIs('reports')" aria-label="Reports" wire:navigate>Reports</flux:navlist.item>
                            </flux:tooltip>

                            @if($user->hasRole('admin'))
                                <flux:tooltip content="Activity Logs" position="right">
                                    <flux:navlist.item icon="clock" :href="route('admin.activity')" :current="request()->routeIs('admin.activity')" aria-label="Activity Logs" wire:navigate>Activity Logs</flux:navlist.item>
                                </flux:tooltip>
                            @endif
                        </flux:navlist.group>
                    @endif
                </flux:navlist>
            </flux:sidebar>
        </div>

            <!-- Main Content Container with Navbar, Page Slot, and Full-Width Footer -->
            <div class="flex-1 flex flex-col min-h-screen min-w-0">
                @if(auth()->check())
                    <x-admin.navbar />
                @endif

                <main id="main-content" class="flex-1">
                    {{ $slot }}
                </main>

                @if(auth()->check())
                    <x-admin.footer />
                @endif
            </div>
        </div>

        <livewire:default-password-modal />
        <x-terms-modal />
        <flux:toast position="top end" />
        @fluxScripts
    </body>
</html>
