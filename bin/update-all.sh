#!/bin/bash
private/share/packagist/vukbgit/simplex/bin/clean-cache.sh
git pull
if [ -f ./composer.sh ]; then
    ./composer.sh update
else
    composer update
fi
./yarn.sh install
./yarn.sh upgrade
