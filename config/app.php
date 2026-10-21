<?php

declare(strict_types=1);

return [
    // The Laraplate icon from the brand repository (CC BY-ND 4.0, used unmodified): the admin brand logo and the welcome page.
    'logo' => env('APP_LOGO', 'https://raw.githubusercontent.com/getlaraplate/brand/master/logos/blueprint/laraplate.svg'),
    // Where the module icons of the Welcome page come from: `<base>/<module>.svg`, unmodified brand files.
    'brand_icons' => 'https://raw.githubusercontent.com/getlaraplate/brand/master/logos/plate',
    // The small-size variant of the same icon, for the browser tab.
    'favicon' => env('APP_FAVICON', 'https://raw.githubusercontent.com/getlaraplate/brand/master/logos/plate/laraplate.svg'),
];
