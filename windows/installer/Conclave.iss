; Installer of Conclave for Windows (Inno Setup 6).
; iscc /DAppVersion=0.4.0 /DSourceDir=..\publish Conclave.iss
; Installs for the current user, without administrator rights, in %LOCALAPPDATA%\Programs.

#ifndef AppVersion
  #define AppVersion "0.4.0"
#endif
#ifndef SourceDir
  #define SourceDir "..\publish"
#endif

[Setup]
AppId={{6E0B8C51-3F2A-4C55-9E64-6B1D2A7C9F10}
AppName=Conclave
AppVersion={#AppVersion}
AppVerName=Conclave {#AppVersion}
AppPublisher=Imperium Clan Software
AppPublisherURL=https://github.com/imperiumclansoftware/conclave
AppSupportURL=https://github.com/imperiumclansoftware/conclave/issues
VersionInfoVersion={#AppVersion}
DefaultDirName={autopf}\Conclave
DisableProgramGroupPage=yes
DisableDirPage=yes
PrivilegesRequired=lowest
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
; Windows 10 2004 or later (Windows App SDK)
MinVersion=10.0.19041
OutputDir=..\dist
OutputBaseFilename=Conclave-Setup-{#AppVersion}
SetupIconFile=..\src\Conclave.Desktop\Assets\conclave.ico
UninstallDisplayIcon={app}\Conclave.exe
UninstallDisplayName=Conclave
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
; An update closes the running Conclave, then starts it again
CloseApplications=yes
RestartApplications=yes

[Languages]
Name: "french"; MessagesFile: "compiler:Languages\French.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: unchecked

[Files]
Source: "{#SourceDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[InstallDelete]
; Files of a previous version that no longer exist
Type: filesandordirs; Name: "{app}\*"

[Icons]
Name: "{autoprograms}\Conclave"; Filename: "{app}\Conclave.exe"
Name: "{autodesktop}\Conclave"; Filename: "{app}\Conclave.exe"; Tasks: desktopicon

[Run]
Filename: "{app}\Conclave.exe"; Description: "{cm:LaunchProgram,Conclave}"; Flags: nowait postinstall skipifsilent

[UninstallRun]
; Forgets the notifications and the start with Windows; the member's data stays
Filename: "{app}\Conclave.exe"; Parameters: "--uninstall"; Flags: runhidden waituntilterminated; RunOnceId: "ConclaveCleanup"
