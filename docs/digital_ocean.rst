Digital Ocean
===================

Invoice Ninja has created a Digital Ocean Marketplace image with Invoice Ninja pre-installed.

To use this image, when creating a Droplet, choose **Marketplace** under the **Choose an image section**, and select the **Invoice Ninja on Ubuntu 18.04** image.

This image includes Nginx, PHP 7.2, MySQL, Certbot, and Postfix, and it will automatically download the latest version of Invoice Ninja on the first boot. An auto-update script is also included to keep your Invoice Ninja installation up to date.

After the server boots and installation has finished, you can visit your server's URL in a web browser to complete the Invoice Ninja setup process. The **Database Connection** section is pre-filled with connection information for the database and user that was created for you. We recommend installing an HTTPS certificate (see below) and setting the application URL, as well as configuring an external mail server before continuing.

HTTPS Certificate
""""""""""""""""""""""""

Once the server has been created, you can connect to the server via SSH. Run ``sudo certbot -d <domain name>`` to generate and install an HTTPS certificate using Let's Encrypt.

The image includes a cron job to automatically renew any certificates installed using Certbot.

Mail
""""""""""""""""""""""""

The image includes a Postfix server; however without additional configuration, your emails will likely be rejected by most email providers.

See the following guide for more information: https://www.digitalocean.com/community/tutorials/how-to-install-and-configure-postfix-on-ubuntu-18-04

.. Note:: Due to the difficulties of configuring and maintaining a mail server for high deliverability rates, we recommend using an external email service such as Mailgun.

FAQ:
""""

Q: My emails are not being delivered.

A: Most likely you have not configured a mail service and have set up the built in Postfix server. We creating a Mailgun account and entering your credentials under Settings -> System Settings -> Email Settings.

Q: Do I need to create a database on the server?

A: No, this is not required. The server automatically creates a MySQL database and user on the first boot.
