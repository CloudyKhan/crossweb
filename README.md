# Cross‐Platform ASP.NET and PHP WebShells

Two webshells in **ASP.NET (C#)** and the other in **PHP** can run arbitrary commands on both Windows and Linux servers. They also support file uploads, fetching remote files, downloading local files, and optional password protection.

> **Security Warning**  
> These scripts allow arbitrary command execution and file operations.  
> **Never deploy them publicly.** Use them **only** in a secure test or lab environment, and **only** with proper authorization.

---

## Features

1. **OS Detection**  
   - Automatically detects Windows vs. Linux.  
   - Executes commands using `cmd.exe` on Windows and `/bin/bash` on Linux.

2. **Command Execution**  
   - Windows: `cmd.exe /C <command>`  
   - Linux: `/bin/bash -c "exec 2>&1; <command>"`

3. **File Upload**  
   - Upload a file from your local machine to the server.

4. **Fetch Remote File**  
   - Download a file from a remote URL (HTTP/HTTPS) directly onto the server.

5. **Download Local File**  
   - Download a file from the server to your local browser (HTTP download).

6. **Optional Password Protection**  
   - Set a password constant (C#) or define (PHP) to require authentication.  
   - Leave it blank to disable authentication.

---

## ASP.NET (C#) WebShell

1. **File**: `CrossPlatformWebShell.aspx` (example filename)  
2. **Setup**:  
   - Place the file in an ASP.NET‐enabled directory on your server (Windows or Linux with Mono).  
   - (Optional) Set `private const string SHELL_PASS = "MySecret";` to enable password protection.  
   - Access it via a browser. If a password is set, enter it when prompted.
3. **Usage**:  
   - **CWD**: Provide a working directory (default is the current script location).  
   - **Command**: Enter a command (e.g., `ipconfig`, `ifconfig`, etc.).  
   - **Upload**: Select a file to upload.  
   - **Fetch**: Provide a remote URL to fetch onto the server.  
   - **Download**: Specify a local server file path to download to your browser.

---

## PHP WebShell

1. **File**: `CrossShell.php`  
2. **Setup**:  
   - Place it on a PHP‐enabled server (Windows or Linux).  
   - (Optional) Edit `define('SHELL_PASS', 'MySecret');` to require a password. Leave blank for no password.  
   - Navigate to `CrossShell.php` in your browser. If password‐protected, you’ll be prompted first.
3. **Usage**:  
   - **CWD**: Provide a working directory (default is the script directory).  
   - **Command**: Enter a shell command (e.g., `whoami`, `dir`, `ls`).  
   - **Upload**: Choose a file from your local machine to upload.  
   - **Fetch**: Provide a remote HTTP/HTTPS URL to download to the server.  
   - **Download**: Enter a server file path to download locally.

---

## Disclaimers & Best Practices

1. **Authorization**  
   - Only use these webshells in environments where you have **explicit permission** to perform security testing.

2. **No Production Use**  
   - These scripts run arbitrary commands and manage files. They are extremely risky if exposed publicly.

3. **Security Logging & Monitoring**  
   - If you’re using them in a test environment, ensure you keep logs and monitor traffic to avoid unintended consequences.

4. **Legal**  
   - The authors and contributors assume **no responsibility** for misuse or damages. Use at your own risk, in compliance with all applicable laws.

---

### Example of ASPX Webshell
![image](https://github.com/user-attachments/assets/b63fd30d-f763-4bbb-9aad-7ea723205a10)

---

**Enjoy responsibly and hack safely!**
