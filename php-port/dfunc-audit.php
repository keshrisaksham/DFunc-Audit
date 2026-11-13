#!/usr/bin/env php
<?php

$known_long_opts = ["url", "file", "help", "gen-checker", "functions", "include-defaults", "enabled-only"];
$longopts = ["url:", "file:", "help", "gen-checker:", "functions:", "include-defaults", "enabled-only"];
$options = getopt("h", $longopts);

// getopt() silently drops unrecognized long options instead of erroring,
// which just looks like the tool produced no output. Catch typos here.
foreach ($argv as $arg) {
    if (substr($arg, 0, 2) === "--") {
        $name = substr($arg, 2, strcspn($arg, "=", 2));
        if (!in_array($name, $known_long_opts, true)) {
            fwrite(STDERR, "Unknown option: --$name\n");
            fwrite(STDERR, "Known options: --" . implode(", --", $known_long_opts) . "\n\n");
            exit(1);
        }
    }
}

function print_help() {
    echo "Usage: php dfunc-audit.php [--url URL] [--file PATH] [--gen-checker OUTPUT_FILE]\n";
    echo "                           [--functions LIST] [--include-defaults] [--enabled-only]\n\n";
    echo "Checks whether disable_functions in a phpinfo() dump still leaves room for\n";
    echo "LD_PRELOAD-based exploitation, given the PHP modules installed on the server.\n\n";
    echo "where to read the phpinfo from:\n";
    echo "  --url URL             PHPinfo URL, e.g. https://example.com/phpinfo.php\n";
    echo "  --file PATH           Local phpinfo file, e.g. dir/phpinfo\n\n";
    echo "generate a standalone checker script instead:\n";
    echo "  --gen-checker OUTPUT_FILE\n";
    echo "                        Write a self-contained PHP script to OUTPUT_FILE. Run it\n";
    echo "                        directly on a server to see, per function, whether it is\n";
    echo "                        actually ENABLED or DISABLED right now, rather than\n";
    echo "                        guessing from a phpinfo dump.\n";
    echo "  --functions LIST      Comma-separated function names to test instead of the\n";
    echo "                        built-in dangerous-functions list.\n";
    echo "  --include-defaults    Test the built-in dangerous-functions list as well as\n";
    echo "                        --functions, instead of replacing it.\n";
    echo "  --enabled-only        Only print functions that come back ENABLED; hide the\n";
    echo "                        disabled/unavailable ones.\n\n";
    echo "  -h, --help            show this help message and exit\n\n";
    echo "examples:\n";
    echo "  Audit a live phpinfo page:\n";
    echo "    php dfunc-audit.php --url https://example.com/phpinfo.php\n\n";
    echo "  Audit a phpinfo dump you saved locally:\n";
    echo "    php dfunc-audit.php --file dir/phpinfo\n\n";
    echo "  Generate a standalone checker script to run directly on the server:\n";
    echo "    php dfunc-audit.php --gen-checker check.php\n\n";
    echo "  ...testing your own functions as well as the built-in list:\n";
    echo "    php dfunc-audit.php --gen-checker check.php --functions mymodule_func --include-defaults\n";
}

if (isset($options['h']) || isset($options['help'])) {
    print_help();
    exit();
}

$reset  = "\033[0m";
$border = "\033[1;36m"; // bold cyan
$tag    = "\033[2;35m"; // dim violet

$banner_art = [
    '    ____  ________  ___   ________   ___   __  ______  __________',
    '   / __ \/ ____/ / / / | / / ____/  /   | / / / / __ \/  _/_  __/',
    '  / / / / /_  / / / /  |/ / /      / /| |/ / / / / / // /  / /   ',
    ' / /_/ / __/ / /_/ / /|  / /___   / ___ / /_/ / /_/ // /  / /    ',
    '/_____/_/    \____/_/ |_/\____/  /_/  |_\____/_____/___/ /_/     ',
];
$tagline = "disable_functions security auditor";

$width = 0;
foreach ($banner_art as $l) {
    $width = max($width, strlen($l));
}

$grad_start = [0, 229, 255];   // cyan
$grad_end   = [178, 102, 255]; // violet

function rgb($c) {
    return "\033[38;2;{$c[0]};{$c[1]};{$c[2]}m";
}

function lerp($a, $b, $t) {
    $out = [];
    for ($i = 0; $i < 3; $i++) {
        $out[] = (int) round($a[$i] + ($b[$i] - $a[$i]) * $t);
    }
    return $out;
}

function banner_row($border, $reset, $inner) {
    return $border . "║ " . $reset . $inner . $border . " ║" . $reset . "\n";
}

$blank_row = banner_row($border, $reset, str_repeat(" ", $width));

echo $border . "╔" . str_repeat("═", $width + 2) . "╗" . $reset . "\n";
echo $blank_row;

$n = count($banner_art);
foreach ($banner_art as $i => $line) {
    $t = $n > 1 ? $i / ($n - 1) : 0;
    $colored = rgb(lerp($grad_start, $grad_end, $t)) . str_pad($line, $width) . $reset;
    echo banner_row($border, $reset, $colored);
}

echo $blank_row;
echo banner_row($border, $reset, $tag . str_pad($tagline, $width, " ", STR_PAD_BOTH) . $reset);
echo $blank_row;
echo $border . "╚" . str_repeat("═", $width + 2) . "╝" . $reset . "\n\n";

$dangerous_functions = ['pcntl_alarm','pcntl_fork','pcntl_waitpid','pcntl_wait','pcntl_wifexited','pcntl_wifstopped','pcntl_wifsignaled','pcntl_wifcontinued','pcntl_wexitstatus','pcntl_wtermsig','pcntl_wstopsig','pcntl_signal','pcntl_signal_get_handler','pcntl_signal_dispatch','pcntl_get_last_error','pcntl_strerror','pcntl_sigprocmask','pcntl_sigwaitinfo','pcntl_sigtimedwait','pcntl_exec','pcntl_getpriority','pcntl_setpriority','pcntl_async_signals','error_log','system','exec','shell_exec','popen','proc_open','passthru','link','symlink','syslog','ld','mail'];

// Functions contributed by optional modules. Included by default in the
// generated checker since it tests each one live (function_exists), so it's
// harmless to list checks for modules that may not even be installed.
$module_extra_functions = ['mb_send_mail', 'imap_open', 'imap_mail', 'libvirt_connect', 'gnupg_init'];

function generate_checker_script($functions, $output_path, $enabled_only = false) {
    $body = "";
    foreach ($functions as $f) {
        $body .= "    '" . str_replace("'", "\\'", $f) . "',\n";
    }
    $script = "<?php\n";
    $script .= "// Diagnostic script generated by dfunc-audit.\n";
    $script .= "// Run it on a server (CLI or over HTTP) to list, for each function name\n";
    $script .= "// below, whether it is ENABLED or DISABLED on that server right now.\n\n";
    $script .= "\$functions = array(\n" . $body . ");\n\n";
    $script .= "\$enabled_only = " . ($enabled_only ? "true" : "false") . ";\n\n";
    $script .= "\$disabled_ini = ini_get('disable_functions');\n";
    $script .= "\$disabled_list = array_map('trim', explode(',', \$disabled_ini));\n\n";
    $script .= "foreach (\$functions as \$f) {\n";
    $script .= "    if (function_exists(\$f)) {\n";
    $script .= "        \$status = 'ENABLED';\n";
    $script .= "    } elseif (in_array(\$f, \$disabled_list, true)) {\n";
    $script .= "        \$status = 'DISABLED (listed in disable_functions)';\n";
    $script .= "    } else {\n";
    $script .= "        \$status = 'DISABLED / NOT AVAILABLE';\n";
    $script .= "    }\n";
    $script .= "    if (\$enabled_only && \$status !== 'ENABLED') {\n";
    $script .= "        continue;\n";
    $script .= "    }\n";
    $script .= "    printf(\"%-30s %s\\n\", \$f, \$status);\n";
    $script .= "}\n";
    file_put_contents($output_path, $script);
    echo "\nWrote " . count($functions) . " function checks to " . $output_path . "\n";
    echo "Run it on the server with: php " . $output_path . "\n";
}

if (isset($options['gen-checker'])) {
    if (isset($options['functions'])) {
        $check_list = array_filter(array_map('trim', explode(',', $options['functions'])));
        if (isset($options['include-defaults'])) {
            foreach (array_merge($dangerous_functions, $module_extra_functions) as $f) {
                if (!in_array($f, $check_list, true)) {
                    $check_list[] = $f;
                }
            }
        }
    } else {
        $check_list = array_merge($dangerous_functions, $module_extra_functions);
    }
    generate_checker_script($check_list, $options['gen-checker'], isset($options['enabled-only']));
    exit();
}

if (isset($options['url'])) {
    $url = $options['url'];
    $phpinfo = file_get_contents($url);
} elseif (isset($options['file'])) {
    $phpinfofile = $options['file'];
    $phpinfo = file_get_contents($phpinfofile);
} else {
    print_help();
    exit();
}

$modules = [];

$parts = explode('disable_functions</td><td class="v">', $phpinfo);
$inpRaw = explode("</", $parts[1])[0];
$inp = explode(',', $inpRaw);
array_pop($inp);

if (strpos($phpinfo, "mbstring.ini") !== false) {
    $modules[] = 'mbstring';
    $dangerous_functions[] = 'mb_send_mail';
}

if (strpos($phpinfo, "imap.ini") !== false) {
    $modules[] = 'imap';
    $dangerous_functions[] = 'imap_open';
    $dangerous_functions[] = 'imap_mail';
}

if (strpos($phpinfo, "libvirt-php.ini") !== false) {
    $modules[] = 'libvert';
    $dangerous_functions[] = 'libvirt_connect';
}

if (strpos($phpinfo, "gnupg.ini") !== false) {
    $modules[] = 'gnupg';
    $dangerous_functions[] = 'gnupg_init';
}

if (strpos($phpinfo, "imagick.ini") !== false) {
    $modules[] = 'imagick';
}

$exploitable_functions = [];

foreach ($dangerous_functions as $i) {
    if (!in_array($i, $inp)) {
        $exploitable_functions[] = $i;
    }
}

if (count($exploitable_functions) == 0) {
    echo "\nThe disable_functions seems strong\n";
} else {
    echo "\nPlease add the following functions in your disable_functions option: \n";
    echo implode(',', $exploitable_functions) . "\n";
}

if (in_array("imagick", $modules)) {
    echo "\nPHP-imagick module is present. It can be exploited using LD_PRELOAD method\n\n";
}

echo "If PHP-FPM is there stream_socket_sendto,stream_socket_client,fsockopen can also be used to be exploit by poisoning the request to the unix socket\n\n";
