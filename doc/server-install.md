Server installation guide
=========================

This project use Symfony Standard 2.8 LTS Version.
For DB purpose, it uses SQLite. But thanks to Doctrine, you can use the database system you need (mySQL, postgreSQL, etc).

- On a server environment, init the project :

```bash
git clone git@github.com:localhook/localhook-server.git
composer install # Fill the asked values with your needs.
```

- Init the database:

```bash
php app/console doctrine:database:create
php app/console doctrine:schema:update --force
```

- Install socket IO dependencies:

```bash
cd src/AppBundle/Resources/SocketIo && npm install
```

Web server configuration
------------------------
Nginx example :

```nginx
server {
    notitications.yourserver.com
...
}
```

Websockets server configuration
-------------------------------

```bash
cd src/AppBundle/Resources/SocketIo && npm install && cd -
php app/console app:server:run-socket-io
```

Verify
------

Visit `http://notitications.yourserver.com` url.

The page is protected, use the login/password values you just set up in the above `composer install` step.
