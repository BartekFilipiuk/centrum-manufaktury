#!/usr/bin/env bash
set -e

extensions=("php" "inc" "module" "theme" "install")
folders=("sites/aquafin" "sites/aquaplus" "modules/custom" "modules/features" "themes/custom")

for i in ${!extensions[@]}; do
  for j in ${!folders[@]}; do
    find public/${folders[$j]} -type f -name "*.${extensions[$i]}" -print0 | xargs -0 -L1 -P4 -- php -l
  done
done
