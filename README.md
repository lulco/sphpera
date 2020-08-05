# Sphpera

Sphpera is **S**tatic **ph**p **per**formance **a**nalysis tool designed for finding potentially the slowest methods and classes based on functions / methods called in them.

## Features

### Implemented
- detection of global function calls
- multiplication for calls in cycles
- custom configuration

### Planned
- detection of class methods calls
- HTML output similar to PHPUnit
- detection of multiple implementations - when some interface or class has multiple implementations and there is only this interface injected, we have to decide which implementation will be used for analysis (default the slowest, can be overridden via configuration)
- multiplication for calls in array_map and similar cycle-style functions

## Installation
This project should not be run as a dependency, but as separate project. Create some directory for it:

```shell script
mkdir sphpera
cd sphpera
```
and follow one of next steps:
 
### use composer
The recommended way is to install this project via composer.

```shell script
composer require lulco/sphpera
``` 

### git clone
You can also clone this project directly. Use this for contribution.
```shell script
git clone git@github.com:lulco/sphpera.git .
composer install 
```

## Usage
Note: Following examples describe how to use sphpera when it is installed via composer.

Run command:
```shell script
vendor/bin/sphpera analyse dir1 dir2
```

## Configuration
Create your own configuration file where you set the score for functions / methods and default score. Then use this configuration file as option in `analyse` command.

```shell script
vendor/bin/sphpera analyse dir1 dir2 --config=path_to_custom_config_file
```
