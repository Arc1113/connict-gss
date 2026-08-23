#define AppName "Connict GSS"
#define AppVersion GetEnv('CONNICT_GSS_VERSION')
#if AppVersion == ""
  #define AppVersion "0.1.0-dev"
#endif

[Setup]
AppId={{2A598A65-E840-4CFA-B18C-B6D1640B6106}
AppName={#AppName}
AppVersion={#AppVersion}
DefaultDirName={autopf}\Connict GSS
DefaultGroupName=Connict GSS
OutputBaseFilename=ConnictGSS-Setup-{#AppVersion}
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
PrivilegesRequired=admin
Compression=lzma2/ultra64
SolidCompression=yes
DisableProgramGroupPage=yes
UninstallDisplayName=Connict GSS

[Files]
Source: "..\payload\app\*"; DestDir: "{app}\app"; Flags: recursesubdirs ignoreversion
Source: "..\payload\runtime\*"; DestDir: "{app}\runtime"; Flags: recursesubdirs ignoreversion
Source: "..\payload\config\*"; DestDir: "{app}\config"; Flags: recursesubdirs ignoreversion
Source: "..\config\php.ini"; DestDir: "{app}\runtime\frankenphp"; Flags: ignoreversion
Source: "..\payload\runtime-manifest.json"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\payload\release-manifest.json"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\services\*"; DestDir: "{app}\services"; Flags: recursesubdirs ignoreversion
Source: "..\scripts\*"; DestDir: "{app}\scripts"; Flags: recursesubdirs ignoreversion
Source: "..\launcher\publish\ConnictGSS.exe"; DestDir: "{app}"; Flags: ignoreversion

[Dirs]
Name: "{commonappdata}\ConnictGSS\database"
Name: "{commonappdata}\ConnictGSS\storage"
Name: "{commonappdata}\ConnictGSS\backups"
Name: "{commonappdata}\ConnictGSS\certificates"
Name: "{commonappdata}\ConnictGSS\configuration"
Name: "{commonappdata}\ConnictGSS\logs"

[Icons]
Name: "{group}\Connict GSS"; Filename: "{app}\ConnictGSS.exe"
Name: "{autodesktop}\Connict GSS"; Filename: "{app}\ConnictGSS.exe"; Tasks: desktopicon

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Shortcuts:"; Flags: unchecked

[Run]
Filename: "{app}\runtime\vcredist\vc_redist.x64.exe"; Parameters: "/install /quiet /norestart"; StatusMsg: "Installing the Microsoft Visual C++ runtime..."; Flags: runhidden waituntilterminated
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\scripts\Install-ConnictGssServices.ps1"" -InstallRoot ""{app}"""; Flags: runhidden waituntilterminated
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\scripts\Set-ConnictGssFirewallRules.ps1"""; Flags: runhidden waituntilterminated
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\scripts\Upgrade-ConnictGss.ps1"" -InstallRoot ""{app}"" -Mode Apply"; Flags: runhidden waituntilterminated
Filename: "{app}\ConnictGSS.exe"; Description: "Open Connict GSS"; Flags: nowait postinstall skipifsilent

[UninstallRun]
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\scripts\Stop-ConnictGssServices.ps1"""; Flags: runhidden waituntilterminated
Filename: "sc.exe"; Parameters: "delete ConnictGSS-PostgreSQL"; Flags: runhidden waituntilterminated
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\scripts\Set-ConnictGssFirewallRules.ps1"" -Remove"; Flags: runhidden waituntilterminated
Filename: "{app}\services\ConnictGSS-web.exe"; Parameters: "uninstall"; Flags: runhidden waituntilterminated skipifdoesntexist
Filename: "{app}\services\ConnictGSS-reverb.exe"; Parameters: "uninstall"; Flags: runhidden waituntilterminated skipifdoesntexist
Filename: "{app}\services\ConnictGSS-queue.exe"; Parameters: "uninstall"; Flags: runhidden waituntilterminated skipifdoesntexist
Filename: "{app}\services\ConnictGSS-tts.exe"; Parameters: "uninstall"; Flags: runhidden waituntilterminated skipifdoesntexist

[Code]
function InitializeSetup(): Boolean;
begin
  if not IsX64 then begin
    MsgBox('Connict GSS requires 64-bit Windows 10 or Windows 11.', mbError, MB_OK);
    Result := False;
  end else Result := True;
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  ResultCode: Integer;
begin
  Result := '';
  if DirExists(ExpandConstant('{app}\app')) and FileExists(ExpandConstant('{commonappdata}\ConnictGSS\configuration\runtime.env')) then begin
    if not Exec('powershell.exe', '-NoProfile -ExecutionPolicy Bypass -File "' + ExpandConstant('{app}\scripts\Upgrade-ConnictGss.ps1') + '" -InstallRoot "' + ExpandConstant('{app}') + '" -Mode Prepare', '', SW_HIDE, ewWaitUntilTerminated, ResultCode) then begin
      Result := 'The existing Connict GSS services could not be prepared for upgrade.';
    end else if ResultCode <> 0 then begin
      Result := 'Upgrade stopped safely. Your data and latest verified backup were preserved.';
    end;
  end;
end;

function IsDataDirectoryEmpty(Param: String): Boolean;
begin
  Result := False;
end;

// ProgramData is deliberately not deleted during uninstall. Institutional
// deletion is a separate support-assisted procedure with confirmation.
