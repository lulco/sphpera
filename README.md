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

Generate coverage-like HTML report:
```shell script
vendor/bin/sphpera analyse dir1 dir2 --format=html --output=build/sphpera-report
```

Generate JSON report:
```shell script
vendor/bin/sphpera analyse dir1 dir2 --format=json
```

Generate JSON baseline snapshot:
```shell script
vendor/bin/sphpera analyse dir1 dir2 --format=json --output=build/sphpera/baseline.json
```

## Configuration
Create your own configuration file where you set the score for functions / methods and default score. Then use this configuration file as option in `analyse` command.

```shell script
vendor/bin/sphpera analyse dir1 dir2 --config=path_to_custom_config_file
```

Sphpera has built-in scoring defaults (HTTP/DB/FS/process/CPU-heavy operations).
Your config has priority over built-ins (first match wins).
If you define a matching pattern, that score is used and evaluation for that node stops.

## Output formats
- `--format=text` (default): top N scored methods
- `--format=json`: structured result with class/method/line contributions
- `--format=html`: report with index, class detail, and line heatmap
- `--compare-baseline=...`: compares current run against JSON baseline and shows deltas in reports

## HTML navigation
HTML report has two tabs:
- `Dashboard`: top classes/methods and deltas
- `Index`: directory/file navigation with aggregated metrics (`min`, `avg`, `median`, `max`)

Index uses risk coloring (green/yellow/orange/red) based on normalized score mix:
- 50% median score
- 35% average score
- 15% maximum score

## Type inference
Sphpera uses PHPStan ReflectionProvider for method return type inference where available
(for example chained calls like `$pdo->query(...)->fetchAll()`), with AST heuristics
as fallback.

## Scope
Report contains only files from input dirs (for example `src`), but analysis also parses
`vendor/` (if present) to improve external-call scoring context.
