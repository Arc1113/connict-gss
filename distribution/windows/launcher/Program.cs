using System.Diagnostics;
using System.Text.Json;

ApplicationConfiguration.Initialize();
var installRoot = AppContext.BaseDirectory.TrimEnd('\\');
var dataRoot = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "ConnictGSS");
var statusPath = Path.Combine(dataRoot, "status.json");
var initialized = IsInitialized(statusPath);
if (!initialized) {
    using var wizard = new SetupWizard(installRoot, dataRoot, statusPath);
    wizard.ShowDialog();
    return;
}

ShowDashboard(installRoot, dataRoot, statusPath);

static bool ServiceReady(string name) {
    try { using var process = Process.Start(new ProcessStartInfo("sc.exe", $"query \"{name}\"") { RedirectStandardOutput = true, UseShellExecute = false }); process!.WaitForExit(3000); return process.ExitCode == 0 && process.StandardOutput.ReadToEnd().Contains("RUNNING"); } catch { return false; }
}

static bool IsInitialized(string statusPath) {
    try {
        using var document = JsonDocument.Parse(File.ReadAllText(statusPath));
        return document.RootElement.TryGetProperty("initialized", out var status) && status.GetBoolean();
    } catch {
        return false;
    }
}

static void ShowDashboard(string installRoot, string dataRoot, string statusPath) {
    using var form = new Form { Width = 620, Height = 430, Text = "Connict GSS", StartPosition = FormStartPosition.CenterScreen, FormBorderStyle = FormBorderStyle.FixedDialog, MaximizeBox = false };
    var heading = new Label { Left = 24, Top = 22, Width = 550, Height = 46, Font = new Font(SystemFonts.MessageBoxFont.FontFamily, 16, FontStyle.Bold), Text = "CONNICT GSS\nGraduation Stage System" };
    var details = new Label { Left = 24, Top = 86, Width = 550, Height = 165, Font = new Font(SystemFonts.MessageBoxFont.FontFamily, 10), AutoSize = false };
    var open = new Button { Text = "Open Connict GSS", Left = 24, Top = 275, Width = 170, Height = 34 };
    var backup = new Button { Text = "Backup now", Left = 206, Top = 275, Width = 115, Height = 34 };
    var diagnostics = new Button { Text = "Export diagnostics", Left = 333, Top = 275, Width = 135, Height = 34 };
    var restart = new Button { Text = "Restart services", Left = 24, Top = 321, Width = 145, Height = 34 };
    var network = new Button { Text = "Refresh network address", Left = 181, Top = 321, Width = 180, Height = 34 };
    var refresh = new Button { Text = "Refresh status", Left = 373, Top = 321, Width = 130, Height = 34 };

    void Refresh() => details.Text = BuildStatus(statusPath);
    open.Click += (_, _) => Process.Start(new ProcessStartInfo(ServerAddress(statusPath)) { UseShellExecute = true });
    backup.Click += (_, _) => RunElevated(installRoot, "Invoke-ConnictGssBackup.ps1", $"-DataRoot \"{dataRoot}\"", "Backup", "Backup completed.");
    diagnostics.Click += (_, _) => {
        var destination = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
        RunElevated(installRoot, "Export-ConnictGssDiagnostics.ps1", $"-DataRoot \"{dataRoot}\" -Destination \"{destination}\"", "Diagnostics", $"Diagnostics were exported to {destination}.");
    };
    restart.Click += (_, _) => RunElevated(installRoot, "Restart-ConnictGssServices.ps1", "", "Services", "Connict GSS services were restarted.");
    network.Click += (_, _) => { RunElevated(installRoot, "Update-ConnictGssNetworkConfiguration.ps1", $"-InstallRoot \"{installRoot}\" -DataRoot \"{dataRoot}\"", "Network", "Network configuration was refreshed."); Refresh(); };
    refresh.Click += (_, _) => Refresh();

    form.Controls.AddRange([heading, details, open, backup, diagnostics, restart, network, refresh]);
    Refresh();
    form.ShowDialog();
}

static string BuildStatus(string statusPath) {
    var url = ServerAddress(statusPath);
    var states = new[] { "ConnictGSS-PostgreSQL", "ConnictGSS-Web", "ConnictGSS-Reverb", "ConnictGSS-Queue", "ConnictGSS-TTS" }
        .Select(name => new { Name = name, Ready = ServiceReady(name) }).ToArray();
    var summary = string.Join(Environment.NewLine, states.Select(s => $"{(s.Ready ? "●" : "○")} {s.Name.Replace("ConnictGSS-", "")}: {(s.Ready ? "Ready" : "Needs attention")}"));
    return $"System status\n{summary}\n\nServer address\n{url}";
}

static string ServerAddress(string statusPath) {
    try {
        using var document = JsonDocument.Parse(File.ReadAllText(statusPath));
        return document.RootElement.GetProperty("server_address").GetString() ?? "https://127.0.0.1";
    } catch {
        return "https://127.0.0.1";
    }
}

static void RunElevated(string installRoot, string script, string arguments, string title, string success) {
    try {
        var path = Path.Combine(installRoot, "scripts", script);
        using var process = Process.Start(new ProcessStartInfo("powershell.exe", $"-NoProfile -ExecutionPolicy Bypass -File \"{path}\" {arguments}") { UseShellExecute = true, Verb = "runas" })!;
        process.WaitForExit();
        MessageBox.Show(process.ExitCode == 0 ? success : "The action did not complete. Export diagnostics or contact support.", title, MessageBoxButtons.OK, process.ExitCode == 0 ? MessageBoxIcon.Information : MessageBoxIcon.Error);
    } catch {
        MessageBox.Show("The action was cancelled or could not start.", title, MessageBoxButtons.OK, MessageBoxIcon.Warning);
    }
}
