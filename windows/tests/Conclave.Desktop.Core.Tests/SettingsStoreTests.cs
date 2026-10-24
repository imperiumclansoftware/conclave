using Conclave.Desktop.Core;

namespace Conclave.Desktop.Core.Tests;

public sealed class SettingsStoreTests : IDisposable
{
    private readonly string directory = Path.Combine(Path.GetTempPath(), "conclave-tests-" + Guid.NewGuid());

    [Fact]
    public void KeepsTheChoices()
    {
        var store = new SettingsStore(Path.Combine(directory, "settings.json"));
        var settings = new DesktopSettings
        {
            ServerUrl = "http://nas.local:8080/",
            StartWithWindows = true,
            Window = new WindowPlacement(10, 20, 1200, 800, false),
        };

        store.Save(settings);

        Assert.Equal(settings, store.Load());
    }

    [Fact]
    public void StartsFromTheDefaultsWithoutAFile()
    {
        var settings = new SettingsStore(Path.Combine(directory, "missing.json")).Load();

        Assert.Null(settings.ServerUrl);
        Assert.True(settings.CloseToTray);
        Assert.False(settings.StartWithWindows);
    }

    [Fact]
    public void SurvivesADamagedFile()
    {
        Directory.CreateDirectory(directory);
        var path = Path.Combine(directory, "settings.json");
        File.WriteAllText(path, "{ not json");

        Assert.Equal(new DesktopSettings(), new SettingsStore(path).Load());
    }

    public void Dispose()
    {
        if (Directory.Exists(directory))
        {
            Directory.Delete(directory, recursive: true);
        }
    }
}
