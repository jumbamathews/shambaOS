#!/bin/bash
set -e

###
# This entrypoint script will check to see if certain directories are empty
# (as is the case when a directory is bind-mounted from the host), and will
# populate them from the pre-built shambaOS codebase in the image.
###

# If the Drupal directory is empty, populate it from pre-built files.
if [ -d /opt/drupal ] && ! [ "$(ls -A /opt/drupal/)" ]; then
  echo "shambaOS codebase not detected. Copying from pre-built files in the Docker image."
  cp -rp /var/shambaOS/. /opt/drupal
fi

# If the sites directory is empty, populate it from pre-built files.
if [ -d /opt/drupal/web/sites ] && ! [ "$(ls -A /opt/drupal/web/sites/)" ]; then
  echo "shambaOS sites directory not detected. Copying from pre-built files in the Docker image."
  cp -rp /var/shambaOS/web/sites/. /opt/drupal/web/sites
fi

if [ -n "$shambaOS_FS_READY_SENTINEL_FILENAME" ]; then
  echo "ready" > "$shambaOS_FS_READY_SENTINEL_FILENAME"
fi

# Execute the arguments passed into this script.
echo "Attempting: $@"
exec "$@"
