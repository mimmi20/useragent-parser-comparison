#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )

php -d memory_limit=4048M $parent_path/scripts/build.php "$@"
