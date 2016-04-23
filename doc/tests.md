Run tests
=========

Run all tests:

```bash
phpunit -c app
```

Run only the workflow test:

```bash
phpunit -c app src/AppBundle/Tests/Functional/WorkflowTest.php
```

Watch a notification:

```bash
php app/console app:client:watch-notification webhook_1
```
