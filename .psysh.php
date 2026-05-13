<?php

use Psy\Output\Theme;

return [
    'theme' => new Theme([
        'compact' => false,
        'grams' => [
            'class' => ['cyan'],        // was blue+underscore
            'const' => ['yellow'],
            'number' => ['magenta'],
            'string' => ['green'],
            'default' => ['default'],
        ],
    ]),
];
