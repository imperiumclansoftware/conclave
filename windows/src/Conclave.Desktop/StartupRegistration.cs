using Microsoft.Win32;

namespace Conclave.Desktop;

/// <summary>Start with Windows (for this user), in the notification area.</summary>
internal static class StartupRegistration
{
    private const string RunKey = @"Software\Microsoft\Windows\CurrentVersion\Run";
    private const string ValueName = "Conclave";

    public static void Set(bool enabled)
    {
        try
        {
            using var key = Registry.CurrentUser.CreateSubKey(RunKey, writable: true);
            if (enabled)
            {
                key.SetValue(ValueName, $"\"{Environment.ProcessPath}\" --minimized");
            }
            else
            {
                key.DeleteValue(ValueName, throwOnMissingValue: false);
            }
        }
        catch (Exception e) when (e is UnauthorizedAccessException or IOException or System.Security.SecurityException)
        {
            Log.Error("Unable to change the start with Windows", e);
        }
    }
}
