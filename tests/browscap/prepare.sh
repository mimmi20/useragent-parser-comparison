#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"

echo "browscap/browscap requires php 7.* to install with composer, if this fails you can try cloning instead"

composer_command="composer"

command -v "$composer_command" >/dev/null 2>&1 || {
    composer_command="composer.phar"
}

$composer_command install --ignore-platform-reqs

