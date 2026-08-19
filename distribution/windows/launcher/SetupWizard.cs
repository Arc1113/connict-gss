using System.Diagnostics;
using System.Net.Mail;
using System.Security.Cryptography;
using System.Text.Json;

internal sealed class SetupWizard : Form
{
    private readonly string installRoot;
    private readonly string dataRoot;
    private readonly string statusPath;
    private readonly Panel content = new() { Dock = DockStyle.Fill, Padding = new Padding(30, 22, 30, 18) };
    private readonly Label stepLabel = new() { AutoSize = true, ForeColor = Color.FromArgb(93, 106, 120) };
    private readonly Label heading = new() { AutoSize = false, Height = 48, Font = new Font(SystemFonts.MessageBoxFont.FontFamily, 17, FontStyle.Bold), ForeColor = Color.FromArgb(20, 37, 59) };
    private readonly Label description = new() { AutoSize = false, Height = 54, ForeColor = Color.FromArgb(93, 106, 120) };
    private readonly Panel fields = new() { AutoScroll = true };
    private readonly Button back = new() { Text = "Back", Width = 92, Height = 36 };
    private readonly Button next = new() { Text = "Continue", Width = 116, Height = 36 };
    private readonly Button cancel = new() { Text = "Cancel", Width = 92, Height = 36 };
    private readonly TextBox institution = new() { Width = 460, MaxLength = 255 };
    private readonly TextBox administrator = new() { Width = 460, MaxLength = 255 };
    private readonly TextBox email = new() { Width = 460, MaxLength = 255 };
    private readonly TextBox password = new() { Width = 460, MaxLength = 255, UseSystemPasswordChar = true };
    private readonly Label validation = new() { AutoSize = false, Height = 44, ForeColor = Color.FromArgb(155, 48, 43) };
    private int step;
    private bool installed;

    public SetupWizard(string installRoot, string dataRoot, string statusPath)
    {
        this.installRoot = installRoot;
        this.dataRoot = dataRoot;
        this.statusPath = statusPath;
        Text = "Connict GSS setup";
        Width = 660;
        Height = 500;
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        BackColor = Color.FromArgb(242, 243, 244);

        var footer = new Panel { Dock = DockStyle.Bottom, Height = 64, Padding = new Padding(30, 12, 30, 12), BackColor = Color.White };
        var buttons = new FlowLayoutPanel { Dock = DockStyle.Right, AutoSize = true, FlowDirection = FlowDirection.LeftToRight, WrapContents = false };
        buttons.Controls.AddRange([cancel, back, next]);
        footer.Controls.Add(buttons);
        fields.Dock = DockStyle.Fill;
        content.Controls.Add(fields);
        content.Controls.Add(description);
        content.Controls.Add(heading);
        content.Controls.Add(stepLabel);
        Controls.Add(content);
        Controls.Add(footer);

        stepLabel.Dock = DockStyle.Top;
        heading.Dock = DockStyle.Top;
        description.Dock = DockStyle.Top;
        fields.Dock = DockStyle.Fill;
        back.Click += (_, _) => { if (step > 0) { step--; Render(); } };
        next.Click += async (_, _) => await AdvanceAsync();
        cancel.Click += (_, _) => Close();
        FormClosing += (_, eventArgs) => {
            if (step == 4) eventArgs.Cancel = true;
        };
        AcceptButton = next;
        CancelButton = cancel;
        Render();
    }

    private async Task AdvanceAsync()
    {
        validation.Text = "";
        if (step == 1 && string.IsNullOrWhiteSpace(institution.Text)) {
            validation.Text = "Enter the institution name.";
            institution.Focus();
            return;
        }
        if (step == 2 && !ValidateAdministrator()) return;
        if (step == 3) {
            step = 4;
            Render();
            await InstallAsync();
            return;
        }
        if (step == 5) {
            if (installed) Process.Start(new ProcessStartInfo(ServerAddress()) { UseShellExecute = true });
            Close();
            return;
        }
        step++;
        Render();
    }

    private bool ValidateAdministrator()
    {
        if (string.IsNullOrWhiteSpace(administrator.Text)) {
            validation.Text = "Enter the first administrator's name.";
            administrator.Focus();
            return false;
        }
        try { _ = new MailAddress(email.Text.Trim()); }
        catch {
            validation.Text = "Enter a valid administrator email address.";
            email.Focus();
            return false;
        }
        if (password.Text.Length < 12) {
            validation.Text = "Use an administrator password of at least 12 characters.";
            password.Focus();
            return false;
        }
        return true;
    }

    private void Render()
    {
        fields.Controls.Clear();
        stepLabel.Text = $"STEP {Math.Min(step + 1, 6)} OF 6";
        back.Visible = step is > 0 and < 4;
        cancel.Visible = step < 4 || !installed;
        next.Enabled = step != 4;
        next.Text = step == 3 ? "Install" : step == 5 ? (installed ? "Open Connict GSS" : "Close") : "Continue";

        switch (step) {
            case 0:
                heading.Text = "Set up this ceremony server";
                description.Text = "Connict GSS will install its local database, web application, live updates, and offline voice service on this Windows computer.";
                AddCopy("Before continuing", "Keep this computer connected to power. Setup requests Windows administrator approval once and preserves institutional data if it cannot finish.");
                break;
            case 1:
                heading.Text = "Institution details";
                description.Text = "This name identifies the local installation and appears in system configuration.";
                AddField("Institution name", institution);
                AddValidation();
                institution.Focus();
                break;
            case 2:
                heading.Text = "First administrator";
                description.Text = "Create the account used to finish ceremony configuration. Additional role-specific accounts can be added after sign-in.";
                AddField("Administrator name", administrator);
                AddField("Email used to sign in", email);
                AddField("Password (at least 12 characters)", password);
                var show = new CheckBox { Text = "Show password", AutoSize = true, Top = 230, Left = 0 };
                show.CheckedChanged += (_, _) => password.UseSystemPasswordChar = !show.Checked;
                fields.Controls.Add(show);
                AddValidation(265);
                administrator.Focus();
                break;
            case 3:
                heading.Text = "Review setup";
                description.Text = "Confirm these details before Windows requests administrator approval.";
                AddCopy("Institution", institution.Text.Trim());
                AddCopy("Administrator", administrator.Text.Trim(), 78);
                AddCopy("Sign-in email", email.Text.Trim(), 156);
                AddCopy("Password", "Stored securely and not displayed", 234);
                break;
            case 4:
                heading.Text = "Installing local services";
                description.Text = "Approve the Windows prompt, then keep this window open while Connict GSS verifies its services.";
                AddCopy("Setup in progress", "The application, database, live updates, and offline voice service are being prepared. This can take several minutes.");
                break;
            default:
                heading.Text = installed ? "Connict GSS is ready" : "Setup needs attention";
                description.Text = installed
                    ? "Local services are installed. Open the application and sign in with the administrator account you created."
                    : "Setup did not finish. Your institutional data was preserved. Retry setup or export diagnostics for support.";
                AddCopy(installed ? "Local services" : "Recovery", installed ? BuildReadiness() : "Select Retry setup, re-enter the administrator password, and review the details before trying again.");
                if (!installed) {
                    var retry = new Button { Text = "Retry setup", Width = 120, Height = 36, Top = 92, Left = 0 };
                    retry.Click += (_, _) => { step = 2; Render(); };
                    fields.Controls.Add(retry);
                }
                var diagnostics = new Button { Text = "Export diagnostics", Width = 145, Height = 36, Top = 140, Left = 0 };
                diagnostics.Click += (_, _) => ExportDiagnostics();
                fields.Controls.Add(diagnostics);
                break;
        }
    }

    private async Task InstallAsync()
    {
        next.Enabled = false;
        cancel.Enabled = false;
        back.Enabled = false;
        var requestPath = Path.Combine(Path.GetTempPath(), $"connict-gss-setup-{Guid.NewGuid():N}.bin");
        try {
            byte[]? request = null;
            byte[]? protectedRequest = null;
            try {
                request = JsonSerializer.SerializeToUtf8Bytes(new {
                    institutionName = institution.Text.Trim(),
                    administratorName = administrator.Text.Trim(),
                    administratorEmail = email.Text.Trim(),
                    administratorPassword = password.Text,
                });
                protectedRequest = ProtectedData.Protect(request, null, DataProtectionScope.CurrentUser);
                File.WriteAllBytes(requestPath, protectedRequest);
            } finally {
                if (request is not null) CryptographicOperations.ZeroMemory(request);
                if (protectedRequest is not null) CryptographicOperations.ZeroMemory(protectedRequest);
            }
            var exitCode = await Task.Run(() => {
                var setup = new ProcessStartInfo("powershell.exe", $"-NoProfile -ExecutionPolicy Bypass -File \"{Path.Combine(installRoot, "scripts", "Initialize-ConnictGss.ps1")}\" -SetupRequest \"{requestPath}\"") { UseShellExecute = true, Verb = "runas", WindowStyle = ProcessWindowStyle.Hidden };
                using var process = Process.Start(setup);
                if (process is null) return -1;
                process.WaitForExit();
                return process.ExitCode;
            });
            installed = exitCode == 0 && IsInitialized() && await Task.Run(WaitForServices);
        } catch {
            installed = false;
        } finally {
            password.Clear();
            try { if (File.Exists(requestPath)) File.Delete(requestPath); } catch { }
            cancel.Enabled = true;
            back.Enabled = true;
            step = 5;
            Render();
        }
    }

    private void AddField(string label, TextBox input)
    {
        var top = fields.Controls.OfType<TextBox>().Count() * 76;
        var caption = new Label { Text = label, AutoSize = true, Left = 0, Top = top };
        input.Left = 0;
        input.Top = top + 24;
        fields.Controls.Add(caption);
        fields.Controls.Add(input);
    }

    private void AddValidation(int top = 90)
    {
        validation.Left = 0;
        validation.Top = top;
        validation.Width = 520;
        fields.Controls.Add(validation);
    }

    private void AddCopy(string title, string body, int top = 0)
    {
        fields.Controls.Add(new Label { Text = title, AutoSize = true, Font = new Font(SystemFonts.MessageBoxFont, FontStyle.Bold), Left = 0, Top = top });
        fields.Controls.Add(new Label { Text = body, AutoSize = false, Width = 530, Height = 58, ForeColor = Color.FromArgb(93, 106, 120), Left = 0, Top = top + 24 });
    }

    private bool IsInitialized()
    {
        try {
            using var document = JsonDocument.Parse(File.ReadAllText(statusPath));
            return document.RootElement.TryGetProperty("initialized", out var state) && state.GetBoolean();
        } catch { return false; }
    }

    private string ServerAddress()
    {
        try {
            using var document = JsonDocument.Parse(File.ReadAllText(statusPath));
            return document.RootElement.GetProperty("server_address").GetString() ?? "https://127.0.0.1";
        } catch { return "https://127.0.0.1"; }
    }

    private static readonly string[] ServiceNames = [
        "ConnictGSS-PostgreSQL",
        "ConnictGSS-Web",
        "ConnictGSS-Reverb",
        "ConnictGSS-Queue",
        "ConnictGSS-TTS",
    ];

    private static bool ServiceReady(string name)
    {
        try {
            using var process = Process.Start(new ProcessStartInfo("sc.exe", $"query \"{name}\"") { RedirectStandardOutput = true, UseShellExecute = false, CreateNoWindow = true });
            if (process is null) return false;
            process.WaitForExit(3000);
            return process.ExitCode == 0 && process.StandardOutput.ReadToEnd().Contains("RUNNING");
        } catch { return false; }
    }

    private static bool WaitForServices()
    {
        for (var attempt = 0; attempt < 20; attempt++) {
            if (ServiceNames.All(ServiceReady)) return true;
            Thread.Sleep(1000);
        }
        return false;
    }

    private string BuildReadiness()
    {
        var ready = ServiceNames.Select(name => name.Replace("ConnictGSS-", "") + ": " + (ServiceReady(name) ? "Ready" : "Needs attention"));
        return string.Join("  ·  ", ready) + Environment.NewLine + ServerAddress();
    }

    private void ExportDiagnostics()
    {
        try {
            var destination = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
            var script = Path.Combine(installRoot, "scripts", "Export-ConnictGssDiagnostics.ps1");
            using var process = Process.Start(new ProcessStartInfo("powershell.exe", $"-NoProfile -ExecutionPolicy Bypass -File \"{script}\" -DataRoot \"{dataRoot}\" -Destination \"{destination}\"") { UseShellExecute = true, Verb = "runas", WindowStyle = ProcessWindowStyle.Hidden });
            process?.WaitForExit();
            MessageBox.Show($"Diagnostics were exported to {destination}.", "Connict GSS setup", MessageBoxButtons.OK, MessageBoxIcon.Information);
        } catch {
            MessageBox.Show("Diagnostics could not be exported.", "Connict GSS setup", MessageBoxButtons.OK, MessageBoxIcon.Warning);
        }
    }
}
