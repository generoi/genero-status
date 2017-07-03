# genero-status.php

> A basic status script to query the status of a Drupal/Wordpress site from a remote dashboard. 

### Installation

Recommended way is to install the script in the root of the project using composer. To move it from the `vendor/` directory you can use the following configuration using `composer-dropin-installer`:

```
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://packagist.minasithil.genero.fi/"
    }
  ],
  "require": {
    "php": ">=5.6",
    "composer/installers": "~1.2.0",
    "koodimonni/composer-dropin-installer": "*",
    "generoi/genero-status": "*"
  },
  "extra": {
    "dropin-paths": {
      "web/": ["package:generoi/genero-status:genero-status.php"]
    }
  }

}
```