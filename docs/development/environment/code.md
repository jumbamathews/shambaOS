# Coding standards

shambaOS follows [Drupal coding standards](https://www.drupal.org/docs/develop/standards).

The shambaOS development Docker image comes pre-installed with
[PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) for detecting
code standard violations.

The following command will run PHP CodeSniffer on all shambaOS code:

    docker exec -it -u www-data shambaOS_www_1 phpcs /opt/drupal/web/profiles/farm

If you see no output, then there are no issues.

In some cases, code standard violations can be fixed automatically with
`phpcbf`:

    docker exec -it -u www-data shambaOS_www_1 phpcbf /opt/drupal/web/profiles/farm
