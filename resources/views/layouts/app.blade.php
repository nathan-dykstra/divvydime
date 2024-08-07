<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if (isset($title))
            <title>{!! $title . ' | ' . config('app.name') !!}</title>
        @else
            <title>{{ config('app.name') }}</title>
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts & Styling-->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/datepicker.min.js"></script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body>
        <div id="app">
            <!-- Set app theme before the content loads -->
            <script>
                const availableThemes = ["system", "light", "dark"];
                const theme = window.localStorage.getItem('theme');
                if (availableThemes.includes(theme)) {
                    availableThemes.forEach((availableTheme) => {
                        document.body.classList.remove(availableTheme);
                    });
                    document.body.classList.add(theme);
                }
            </script>

            @include('layouts.search')

            @include('layouts.sidebar')

            @include('layouts.header')

            <div class="main-content-wrapper" id="main-content-wrapper">
                <!-- Set sidebar state before content loads -->
                <script>
                    const isCollapsed = localStorage.getItem('sidebarCollapsed');
                    const sidebarWidth = "250px";        //
                    const autoCloseSidebarWidth = 900;   // These constants must match app.js
                    const mobileWidth = 768;             //
                    const mainContentWrapper = document.getElementById("main-content-wrapper");
                    const headerWrapper = document.getElementById("header-wrapper");
                    const sidebar = document.getElementById("sidebar");
                    const sidebarButton = document.getElementById("show-sidebar-btn");
                    const pinSidebarTooltip = document.getElementById("pin-sidebar-tooltip");
                    const pinSidebarBtn = document.getElementById("pin-sidebar-icon");

                    if (window.innerWidth <= mobileWidth) {
                        // No need to adjust sidebar when it's hidden for mobile
                    } else if (window.innerWidth < autoCloseSidebarWidth) {
                        sidebar.classList.remove("sidebar-expanded");
                        if (headerWrapper) headerWrapper.style.marginLeft = "0";
                        mainContentWrapper.style.marginLeft = "0";
                        sidebarButton.classList.remove("hidden");
                        pinSidebarBtn.classList.add("hidden");
                    } else if (isCollapsed === 'true' || isCollapsed === true) {
                        sidebar.classList.remove("sidebar-expanded");
                        if (headerWrapper) headerWrapper.style.marginLeft = "0";
                        mainContentWrapper.style.marginLeft = "0";
                        sidebarButton.classList.remove("hidden");
                        pinSidebarTooltip.innerHTML = "Pin Sidebar";
                    } else {
                        sidebar.classList.add("sidebar-expanded");
                        if (headerWrapper) headerWrapper.style.marginLeft = sidebarWidth;
                        mainContentWrapper.style.marginLeft = sidebarWidth;
                        sidebarButton.classList.add("hidden");
                        pinSidebarTooltip.innerHTML = "Unpin Sidebar";
                    }
                </script>

                <!-- Page Content -->
                <main class="main-content">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
