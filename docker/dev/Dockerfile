# Inherit from the shambaOS 2.x image.
FROM shambaOS/shambaOS:2.x

# Set the shambaOS and composer project repository URLs and versions.
ARG shambaOS_REPO=https://github.com/shambaOS/shambaOS.git
ARG shambaOS_VERSION=2.x
ARG PROJECT_REPO=https://github.com/shambaOS/composer-project.git
ARG PROJECT_VERSION=2.x

# Set OPcache's revalidation frequency to 0 seconds for development.
# See https://www.php.net/manual/en/opcache.configuration.php#ini.opcache.revalidate-freq
RUN sed -i 's|opcache.revalidate_freq=60|opcache.revalidate_freq=0|g' /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install and configure XDebug.
RUN yes | pecl install xdebug \
	&& echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini

# Change the user/group IDs of www-data inside the image to match the ID of the
# developer's user on the host machine. This allows Composer to create files
# owned by www-data inside the container, while keeping those files editable by
# the developer outside of the container.
# This defaults to 1000, based on the assumption that the developer is running
# as UID 1000 on the host machine. It can be overridden at image build time with:
# --build-arg WWW_DATA_ID=$(id -u)
ARG WWW_DATA_ID=1000
RUN usermod -u ${WWW_DATA_ID} www-data && groupmod -g ${WWW_DATA_ID} www-data

# Create a fresh /var/shambaOS directory owned by www-data.
# We do this in two steps because of a known issue with Moby.
# @see https://github.com/shambaOS/shambaOS/pull/440
RUN rm -r /var/shambaOS
RUN mkdir /var/shambaOS && chown www-data:www-data /var/shambaOS

# Change to the www-data user.
USER www-data

# Build the shambaOS codebase in /var/shambaOS.
RUN /usr/local/bin/build-shambaOS.sh

# Configure PHP CodeSniffer.
RUN { \
    echo '<?xml version="1.0" encoding="UTF-8"?>'; \
    echo '<ruleset name="shambaOS">'; \
    echo '  <description>PHP CodeSniffer configuration for shambaOS development.</description>'; \
    echo '  <file>.</file>'; \
    echo '  <arg name="extensions" value="php,module,inc,install,test,profile,theme,css,info,txt,yml"/>'; \
    echo '  <config name="drupal_core_version" value="9"/>'; \
    echo '  <rule ref="Drupal">'; \
    echo '    <exclude name="Drupal.InfoFiles.AutoAddedKeys.Version"/>'; \
    echo '    <exclude name="Drupal.Arrays.Array.LongLineDeclaration"/>'; \
    # @todo https://www.drupal.org/project/coder/issues/2159253
    echo '    <exclude name="Drupal.Commenting.InlineComment.SpacingAfter"/>'; \
    echo '  </rule>'; \
    echo '  <rule ref="DrupalPractice">'; \
    # @todo https://www.drupal.org/project/coder/issues/2159253
    echo '    <exclude name="DrupalPractice.Commenting.CommentEmptyLine.SpacingAfter"/>'; \
    echo '  </rule>'; \
    echo '  <rule ref="Internal.Tokenizer.Exception"><severity>0</severity></rule>'; \
    echo '</ruleset>'; \
  } > /var/shambaOS/phpcs.xml \
  && /var/shambaOS/vendor/bin/phpcs --config-set installed_paths /var/shambaOS/vendor/drupal/coder/coder_sniffer,/var/shambaOS/vendor/slevomat/coding-standard

# Configure PHPUnit.
RUN cp -p /var/shambaOS/web/core/phpunit.xml.dist /var/shambaOS/phpunit.xml \
  && sed -i 's|bootstrap="tests/bootstrap.php"|bootstrap="web/core/tests/bootstrap.php"|g' /var/shambaOS/phpunit.xml \
  && sed -i '/failOnWarning="true"/a \         failOnIncomplete="true"' /var/shambaOS/phpunit.xml \
  && sed -i '/failOnWarning="true"/a \         failOnSkipped="true"' /var/shambaOS/phpunit.xml \
  && sed -i 's|name="SIMPLETEST_BASE_URL" value=""|name="SIMPLETEST_BASE_URL" value="http://www"|g' /var/shambaOS/phpunit.xml \
  && sed -i 's|name="SIMPLETEST_DB" value=""|name="SIMPLETEST_DB" value="pgsql://farm:farm@db/farm"|g' /var/shambaOS/phpunit.xml \
  && sed -i 's|name="BROWSERTEST_OUTPUT_DIRECTORY" value=""|name="BROWSERTEST_OUTPUT_DIRECTORY" value="/var/www/html/sites/simpletest/browser_output"|g' /var/shambaOS/phpunit.xml \
  && sed -i 's|name="MINK_DRIVER_ARGS_WEBDRIVER" value='\'''\''|name="MINK_DRIVER_ARGS_WEBDRIVER" value='\''["chrome", { "chromeOptions": { "w3c": false, "args": ["--disable-gpu","--headless", "--no-sandbox"] } }, "http://chrome:4444/wd/hub"]'\''|g' /var/shambaOS/phpunit.xml \
  && sed -i 's|\./|\./web/core/|g' /var/shambaOS/phpunit.xml \
  && sed -i 's|\.\./web/core/|\./web/|g' /var/shambaOS/phpunit.xml \
  && sed -i 's|  </php>|    <env name="SYMFONY_DEPRECATIONS_HELPER" value="quiet[]=indirect"/>'"\n"'  </php>|g' /var/shambaOS/phpunit.xml \
  && mkdir -p /var/shambaOS/web/sites/simpletest/browser_output

# Change back to the root user.
USER root

# Copy the shambaOS codebase into /opt/drupal.
RUN rm -r /opt/drupal && cp -rp /var/shambaOS /opt/drupal

# Create a Composer config directory for the www-data user.
RUN mkdir /var/www/.composer && chown www-data:www-data /var/www/.composer
