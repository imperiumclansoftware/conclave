namespace Conclave.Desktop.Core;

/// <summary>Versions "0.4.0" of the application and of the server.</summary>
public static class AppVersion
{
    /// <summary>
    /// True when the server offers a newer application. A server built without a version ("dev")
    /// never asks for an update.
    /// </summary>
    public static bool IsNewer(string? offered, string current) =>
        TryRead(offered, out var o) && TryRead(current, out var c) && o > c;

    private static bool TryRead(string? text, out Version version)
    {
        version = new Version(0, 0);
        if (string.IsNullOrWhiteSpace(text))
        {
            return false;
        }

        // "0.4.0+abc" or "0.4.0-beta": the numbers only
        var numbers = text.Trim().TrimStart('v').Split('+', '-')[0];
        if (!Version.TryParse(numbers, out var parsed))
        {
            return false;
        }

        version = parsed;
        return true;
    }
}
