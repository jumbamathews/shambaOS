#!/bin/bash
set -e

###
# This script will build the shambaOS codebase in /var/shambaOS.
###

# If /var/shambaOS is not empty, bail.
if [ "$(ls -A /var/shambaOS/)" ]; then
  exit 1
fi

# Make /var/shambaOS the working directory.
cd /var/shambaOS

# Generate an empty Composer project project and checkout a specific version.
git clone ${PROJECT_REPO} project
mv project/.git ./.git
rm -rf project
git checkout ${PROJECT_VERSION}
git reset --hard

# Create a temporary Composer cache directory.
export COMPOSER_HOME="$(mktemp -d)"

# Add the shambaOS repository to composer.json.
composer config repositories.shambaOS git ${shambaOS_REPO}

# Require the correct shambaOS version in composer.json. Defaults to 2.x.
# If shambaOS_VERSION is not a valid semantic versioning string, we assume that
# it is a branch, and prepend it with "dev-".
# Otherwise shambaOS_VERSION is a valid semantic versioning string. We assume
# that it is a tagged version and require that version.
if [ "${shambaOS_VERSION}" = "2.x" ]; then
  shambaOS_COMPOSER_VERSION="2.x-dev"
elif [[ ! "${shambaOS_VERSION}" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(-((0|[1-9][0-9]*|[0-9]*[a-zA-Z-][0-9a-zA-Z-]*)(\.(0|[1-9][0-9]*|[0-9]*[a-zA-Z-][0-9a-zA-Z-]*))*))?(\+([0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*))?$ ]]; then
  shambaOS_COMPOSER_VERSION="dev-${shambaOS_VERSION}"
fi
composer require shambaOS/shambaOS ${shambaOS_COMPOSER_VERSION} --no-install

# Add allow-plugins config.
allowedPlugins=(
  "composer/installers"
  "cweagans/composer-patches"
  "drupal/core-composer-scaffold"
  "oomphinc/composer-installers-extender"
  "wikimedia/composer-merge-plugin"
)
for plugin in ${allowedPlugins[@]}; do
  composer config --no-plugins allow-plugins.$plugin true
done

# Run composer install with optional arguments passed into this script.
if [ $# -eq 0 ]; then
  composer install
else
  composer install "$*"
fi

# Set the version in farm.info.yml.
sed -i "s|version: 2.x|version: ${shambaOS_VERSION}|g" /var/shambaOS/web/profiles/farm/farm.info.yml

# Remove the Composer cache directory.
rm -rf "$COMPOSER_HOME"
