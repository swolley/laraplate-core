<x-filament-panels::page>
    <div class="fi-page-content p-6">
        <style>
            /* PHPInfo specific styling */
            #phpinfo {
                padding: 1.5rem;
                position: relative;
                overflow-x: auto;
                max-width: 100%;
            }

            #phpinfo img, #phpinfo svg {
                display: none;
            }

            #phpinfo table {
                width: 100%;
                max-width: 100%;
                margin-bottom: 1.5rem;
                border-radius: 0.5rem;
                overflow: hidden;
                background-color: rgb(24 24 27);
                box-shadow: 0px 14px 34px 0px rgba(0, 0, 0, 0.08);
                transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
                transition-duration: 300ms;
                transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
                border-collapse: collapse;
                border-spacing: 0;
                table-layout: fixed;
            }

            #phpinfo table:hover {
                --tw-ring-color: rgb(63 63 70);
                color: rgb(255 255 255 / 0.7);
            }

            #phpinfo td, 
            #phpinfo th {
                padding: 0.75rem 1rem;
                background-color: transparent;
                margin: 0;
            }

            #phpinfo td {
                max-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                word-break: break-all;
            }

            #phpinfo td:hover {
                white-space: normal;
                word-break: break-word;
                overflow: visible;
                position: relative;
                z-index: 10;
                background-color: rgb(39 39 42);
                max-width: none;
            }

            #phpinfo th {
                background-color: rgb(39 39 42);
                color: rgb(255 255 255 / 0.7);
                font-weight: 600;
            }

            #phpinfo a {
                color: inherit;
                text-decoration: none;
                font-weight: inherit;
                font-size: inherit;
            }

            #phpinfo h1 {
                font-size: 1.5rem;
                font-weight: 600;
                margin: 2rem 0;
                color: rgb(255 255 255 / 0.7);
            }

            #phpinfo h2 {
                font-size: 1.25rem;
                font-weight: 600;
                margin: 1.5rem 0;
                color: rgb(255 255 255 / 0.7);
            }

            #phpinfo hr {
                border: 0;
                border-top: 1px solid rgb(63 63 70);
                margin: 2rem 0;
            }

            #phpinfo .center {
                text-align: center;
            }

            #phpinfo .v {
                color: rgb(255 255 255 / 0.5);
            }

            #phpinfo .e {
                background-color: rgb(39 39 42);
                color: rgb(255 255 255 / 0.7);
                font-weight: 600;
                width: 30%;
                min-width: 300px;
            }

            /* #phpinfo td.e {
                width: 30%;
                min-width: 200px;
            } */

            /* Light mode support */
            .light #phpinfo table {
                background-color: rgb(255 255 255);
            }

            .light #phpinfo th {
                background-color: rgb(249 250 251);
                color: rgb(17 24 39);
            }

            .light #phpinfo h1,
            .light #phpinfo h2 {
                color: rgb(17 24 39);
            }

            .light #phpinfo .v {
                color: rgb(107 114 128);
            }

            .light #phpinfo .e {
                background-color: rgb(249 250 251);
                color: rgb(17 24 39);
            }

            .light #phpinfo hr {
                border-top-color: rgb(229 231 235);
            }
        </style>

        <div id="phpinfo">
            @php
                ob_start();
                phpinfo();
                $pinfo = ob_get_contents();
                ob_end_clean();
                
                // Extract only the body content
                echo str_replace(
                    "module_Zend Optimizer",
                    "module_Zend_Optimizer",
                    preg_replace(
                        '%^.*<body>(.*)</body>.*$%ms',
                        '$1',
                        $pinfo
                    )
                );
            @endphp
        </div>
    </div>
</x-filament-panels::page>
