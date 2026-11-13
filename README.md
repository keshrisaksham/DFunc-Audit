<p align="center"><img src="images/banner.png" /></p>

# Dfunc-Audit
This is a tool that can be used by developers to check if exploitation using LD_PRELOAD is still possible given the current disable_functions in the php.ini file and taking into consideration the PHP modules installed on the server.

Fork of [teambi0s/dfunc-bypasser](https://github.com/teambi0s/dfunc-bypasser).

## Installation
`git clone https://github.com/keshrisaksham/DFunc-Audit`

## Versions
This repo has three equivalent implementations:
- `original/dfunc-bypasser.py` — the original Python 2 script (unmodified, kept for reference)
- `python-3-port/dfunc-audit.py` — Python 3 port
- `php-port/dfunc-audit.php` — PHP port

## Usage
Run with `-h` for the full help, including examples:
```
$ python3 python-3-port/dfunc-audit.py -h
usage: dfunc-audit.py [-h] [--url URL] [--file PATH]
                      [--gen-checker OUTPUT_FILE] [--functions LIST]
                      [--include-defaults] [--enabled-only]

Checks whether disable_functions in a phpinfo() dump still leaves room for
LD_PRELOAD-based exploitation, given the PHP modules installed on the server.

options:
  -h, --help            show this help message and exit

where to read the phpinfo from:
  --url URL             PHPinfo URL, e.g. https://example.com/phpinfo.php
  --file PATH           Local phpinfo file, e.g. dir/phpinfo

generate a standalone checker script instead:
  --gen-checker OUTPUT_FILE
                        Write a self-contained PHP script to OUTPUT_FILE. Run
                        it directly on a server to see, per function, whether
                        it is actually ENABLED or DISABLED right now, rather
                        than guessing from a phpinfo dump.
  --functions LIST      Comma-separated function names to test instead of the
                        built-in dangerous-functions list.
  --include-defaults    Test the built-in dangerous-functions list as well as
                        --functions, instead of replacing it.
  --enabled-only        Only print functions that come back ENABLED; hide the
                        disabled/unavailable ones.

examples:
  Audit a live phpinfo page:
    dfunc-audit.py --url https://example.com/phpinfo.php

  Audit a phpinfo dump you saved locally:
    dfunc-audit.py --file dir/phpinfo

  Generate a standalone checker script to run directly on the server:
    dfunc-audit.py --gen-checker check.php

  ...testing your own functions as well as the built-in list:
    dfunc-audit.py --gen-checker check.php --functions mymodule_func --include-defaults
```

The PHP port supports the same flags and prints the same help, e.g.
`php php-port/dfunc-audit.php -h` or `php php-port/dfunc-audit.php --file dir/phpinfo`.

## Generating a standalone checker script
`--gen-checker` writes a small, self-contained PHP script to a file. That script calls
`function_exists()` on a list of function names and prints whether each one is ENABLED or
DISABLED — a live, authoritative check rather than a guess from parsing a phpinfo dump.
This is useful for running directly on a server you control, to confirm what the static
phpinfo-based analysis above suggested. See the `-h` output above for the available
`--functions`, `--include-defaults` and `--enabled-only` flags. Once generated, run it on
the server with `php check.php`.
