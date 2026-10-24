namespace Conclave.Desktop;

/// <summary>Where the application keeps its files: the member's local data, never next to the program.</summary>
internal static class AppPaths
{
    public static string Data { get; } = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Conclave");

    public static string Settings { get; } = Path.Combine(Data, "settings.json");

    /// <summary>Cookies, session and cache of the page</summary>
    public static string WebView { get; } = Path.Combine(Data, "WebView2");

    public static string Asset(string name) => Path.Combine(AppContext.BaseDirectory, "Assets", name);

    public static string Version { get; } =
        typeof(AppPaths).Assembly.GetName().Version is { } v ? $"{v.Major}.{v.Minor}.{v.Build}" : "0.0.0";
}
