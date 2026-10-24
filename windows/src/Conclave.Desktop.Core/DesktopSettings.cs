using System.Text.Json;
using System.Text.Json.Serialization;

namespace Conclave.Desktop.Core;

/// <summary>Choices of the member on this computer.</summary>
public sealed record DesktopSettings
{
    /// <summary>Address of the server, null until the first connection</summary>
    public string? ServerUrl { get; init; }

    /// <summary>Starts with Windows, in the notification area</summary>
    public bool StartWithWindows { get; init; }

    /// <summary>Closing the window keeps Conclave in the notification area (notifications go on)</summary>
    public bool CloseToTray { get; init; } = true;

    /// <summary>The notification area tip was shown once</summary>
    public bool TrayTipShown { get; init; }

    public WindowPlacement? Window { get; init; }
}

/// <summary>Position and size of the window, in pixels.</summary>
public sealed record WindowPlacement(int X, int Y, int Width, int Height, bool Maximized);

/// <summary>Settings saved as JSON; a missing or damaged file gives the defaults.</summary>
public sealed class SettingsStore(string path)
{
    private static readonly JsonSerializerOptions Options = new()
    {
        WriteIndented = true,
        DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull,
    };

    public string Path { get; } = path;

    public DesktopSettings Load()
    {
        try
        {
            return File.Exists(Path)
                ? JsonSerializer.Deserialize<DesktopSettings>(File.ReadAllText(Path), Options) ?? new DesktopSettings()
                : new DesktopSettings();
        }
        catch (Exception e) when (e is JsonException or IOException or UnauthorizedAccessException)
        {
            return new DesktopSettings();
        }
    }

    public void Save(DesktopSettings settings)
    {
        Directory.CreateDirectory(System.IO.Path.GetDirectoryName(Path)!);
        // Written next to it then moved: a crash never leaves half a file
        var temporary = Path + ".tmp";
        File.WriteAllText(temporary, JsonSerializer.Serialize(settings, Options));
        File.Move(temporary, Path, overwrite: true);
    }
}
