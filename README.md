# Sphpera

Sphpera is **S**tatic **ph**p **per**formance **a**nalysis tool designed for finding slowest methods and classes based on functions / methods called in them.

## Installation
This project is not meant to be run as a dependency, but as separate project.

### Composer
The recommended way is to install this library via composer.

```shell script
mkdir sphpera
cd sphpera
composer require lulco/sphpera
``` 

### Usage
Run command
```
vendor/bin/sphpera analyse dir1 dir2
```
