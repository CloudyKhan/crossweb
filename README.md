# CrossWeb WebShells (ASP.NET and PHP)

**CrossWeb** provides two distinct webshells:  
- **`crossweb.aspx`** (C# ASP.NET)  
- **`crossweb.php`** (PHP)  

Each one enables cross‐platform command execution, file transfers (upload/download), and optional password protection. They **automatically detect** whether the server is running on Windows or Linux, then execute commands via **`cmd.exe`** or **`/bin/bash`**.

> **Security Notice**  
> These scripts provide **arbitrary command execution** and powerful file operations.  
> **Never** deploy them publicly or in production. Use **only** in a secure, authorized test environment.

---
![image](https://github.com/user-attachments/assets/d8ba8665-5eac-4764-8ee9-c6cfc4a789d4)

## Key Features

1. **OS Detection**  
   - Windows → `cmd.exe /C`  
   - Linux → `/bin/bash -c`  

2. **Command Execution**  
   - Run system commands (e.g., `ipconfig`, `ifconfig`, `whoami`).

3. **File Upload**  
   - Transfer files from your local machine to the server.

4. **Fetch Remote File**  
   - Download external files (HTTP/HTTPS) onto the server.

5. **Download Local File**  
   - Retrieve files from the server to your local device.

6. **Optional Password Protection**  
   - Define a password variable or constant in the script to require authentication.  
   - Leave it empty to disable password checks entirely.

---

## Usage

### 1. `crossweb.aspx` (C# ASP.NET)

1. **Deployment**  
   - Copy **`crossweb.aspx`** into a folder served by an ASP.NET‐enabled environment (Windows IIS, or Mono on Linux).
   - If you want authentication, set the password in the code:
     ```csharp
     private const string SHELL_PASS = "YourPassword";
     ```
   - Access the page at:  
     ```
     http://yourserver/crossweb.aspx
     ```
   - If a password was set, you’ll be prompted to enter it once.

2. **Interacting with the WebShell**  
   - **Working Directory (CWD)**: Specify where commands and file operations occur (defaults to the script folder if unspecified).  
   - **Command Execution**: Type a command (e.g. `dir`, `ls`, `netstat`) and click “Execute” OR hit enter.   
   - **File Upload**: Select a file from your local machine to upload.  
   - **Fetch Remote File**: Provide a remote HTTP/HTTPS URL (e.g. `http://example.com/tool.exe`) to store on the server.  
   - **Download Local File**: Enter a server‐side path (e.g. `C:\temp\secret.txt`) to download.

---

### 2. `crossweb.php` (PHP)

1. **Deployment**  
   - Place **`crossweb.php`** in a directory served by a PHP‐enabled environment (Apache, Nginx, etc.).  
   - If desired, enable password protection by editing:
     ```php
     define('SHELL_PASS', 'YourPassword');
     ```
   - Navigate to:
     ```
     http://yourserver/crossweb.php
     ```
   - A password prompt will appear if `SHELL_PASS` is not empty.

2. **Interacting with the WebShell**  
   - **Working Directory**: Defaults to the script’s own directory if none is provided.  
   - **Execute Commands**: Enter a command (e.g. `whoami`, `uname -a`) and submit.  
   - **File Upload**: Use the file picker to upload a file directly to the server.  
   - **Fetch Remote**: Download a file from a remote URL onto the server (useful for retrieving tools or scripts).  
   - **Local Download**: Enter a path on the server to receive it in your browser.
---

## Disclaimer

- **High‐Risk Scripts**: They enable full system command execution and file manipulation.  
- **Authorized Use Only**: Confirm you have explicit permission before deploying them.  
- **No Liability**: The authors assume no responsibility for misuse or any resulting damage.  
- **Recommended**: Restrict network access, utilize strong passwords, and deploy solely in test labs or controlled environments.

Use responsibly and stay within legal and ethical boundaries.
