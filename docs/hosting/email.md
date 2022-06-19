# Email configuration

shambaOS needs to be able to send emails to users. This is used for password
reset emails, notifications, etc.

Depending on how you have shambaOS deployed, there are a few ways to configure
your server to allow shambaOS to send emails.

By default, shambaOS will attempt to send emails via an SMTP server installed on
the same system. If you have [Postfix](http://www.postfix.org) installed, email
should work without any additional configuration, although they will most likely
be filtered as spam. [This StackOverflow topic](https://stackoverflow.com/questions/371/how-do-you-make-sure-email-you-send-programmatically-is-not-automatically-marked)
provides guidance for avoiding this.

## Docker

The [shambaOS Docker images](/hosting/install#shambaOS-in-docker) do not include an
SMTP server, so you will see this error message when shambaOS tries to send an
email:

> Unable to send e-mail. Contact the site administrator if the problem persists.

There are two potential solutions to this:

1. Install and configure the [SMTP](https://drupal.org/project/smtp) module.
   This is a contributed Drupal module that allows emails to be relayed through
   a third-party SMTP server. This module is not included with shambaOS, but can
   be downloaded into `[shambaOS-codebase]/web/sites/all/modules` and enabled in
   `https://[shambaOS-hostname]/admin/modules`.
2. Create your own Docker image which inherits from the shambaOS image. This
   image can install an SMTP server like Postfix, which can be configured to
   send email directly, or relay it through another SMTP server.
