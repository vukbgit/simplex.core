#!/bin/bash
ver-bump -p origin
git checkout development
last_branch=$(git branch --contains `git rev-list --tags --max-count=1` release*)
git merge $last_branch
git push