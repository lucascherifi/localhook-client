Quick start installation
========================

- [Install the client](client-install.html) documentation.

If you want to quick test this project, you can load "fake data" (fixtures):

```bash
php app/console hautelook:doctrine:fixtures:load -n
```

You can also send a fake notification as following:
```bash
php app/console app:server:simulate-notification webhook_1
```
Note : `webhook_1` is the name of a created webhook.
