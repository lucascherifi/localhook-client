[WIP]

# Localhook

A simple tool to receive http/https webhook notifications for development purpose.

This tool simplify web developments while using API using webhooks.

1) Install the [server application](https://github.com/localhook/localhook-server) at https://yourdomain.com

2) Configure a new webhook on the server

- go to https://yourdomain.com/admin (default auth: admin / admin)
- Type a webhook path, ex : `webhook_1`
- Store somewhere the generated private key.

3) Install, configure and run the client:

- Install:
```bash
composer global require localhook/localhook
```
- Start the client:
```bash
localhook run
```
At first run, you'll be prompt for:
- The server Socket IO URL, i.e.: `http://yourdomain.com:1337`
- A private key watching a webhook you created, i.e. `webhook_1`
- A local URL to call for each notification, i.e. "http://localhost/notifications"

The client will now redirect all notifications, as this example :

    POST https://yourdomain.com/webhook_1 ==> POST http://localhost/notifications
