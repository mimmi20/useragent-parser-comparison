#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )

ruby $parent_path/scripts/parse.rb "$@"
