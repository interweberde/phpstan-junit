## PHPStan JUnit error reporter

Generate error reporter used to create JUnit output

Include as dependency by:

```
composer require --dev interweberde/phpstan-junit
```

And enable on your `phpstan.neon` config file by including

```
services:
    errorFormatter.junit:
        class: Interweberde\PHPStan\ErrorFormatter\JunitErrorFormatter
```

than execute it by running:

```
vendor/bin/phpstan --configuration=phpstan.neon --errorFormat=junit --level=7 --no-progress --no-interaction analyse SOURCE_CODE_DIR
```
