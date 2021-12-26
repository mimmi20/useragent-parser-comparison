<?php

declare(strict_types = 1);
file_put_contents(
    __DIR__ . '/../version.txt',
    mb_substr(
        hash('sha512', file_get_contents(__DIR__ . '/../vendor/ua-parser/uap-core/regexes.yaml')),
        0,
        7
    )
);
