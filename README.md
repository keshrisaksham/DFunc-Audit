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
There are two options to input the disable_functions list:
1. For help on the parameters:
`python3 python-3-port/dfunc-audit.py -h`
2. Provide the phpinfo url:
`python3 python-3-port/dfunc-audit.py --url https://example.com/phpinfo.php`
3. Provide the local phpinfo file:
`python3 python-3-port/dfunc-audit.py --file dir/phpinfo`

The PHP port uses the same flags, e.g. `php php-port/dfunc-audit.php --file dir/phpinfo`.

## Generating a standalone checker script
`--gen-checker` writes a small, self-contained PHP script to a file. That script calls
`function_exists()` on a list of function names and prints whether each one is ENABLED or
DISABLED — a live, authoritative check rather than a guess from parsing a phpinfo dump.
This is useful for running directly on a server you control, to confirm what the static
phpinfo-based analysis above suggested.

- Default list (the built-in dangerous functions):
`python3 python-3-port/dfunc-audit.py --gen-checker check.php`
- Custom list:
`python3 python-3-port/dfunc-audit.py --gen-checker check.php --functions system,exec,shell_exec`
- Then on the server: `php check.php`

The PHP port supports the same flags: `php php-port/dfunc-audit.php --gen-checker check.php`.
