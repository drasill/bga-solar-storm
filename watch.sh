#!/bin/bash
inotifywait -m -e close_write,moved_to --format %e/%f . |
while IFS=/ read -r events file; do
  if [ "$file" = "solarstorm-src.js" ]; then
	  yarn dev
  fi
done
