using System.Diagnostics;
using Microsoft.UI.Dispatching;
using Microsoft.UI.Xaml;
using Microsoft.Windows.AppLifecycle;
using Microsoft.Windows.AppNotifications;

namespace Conclave.Desktop;

/// <summary>
/// Entry point: a single Conclave runs at a time. Launching it again (shortcut, notification)
/// brings the running one forward instead.
/// </summary>
public static class Program
{
    private const string InstanceKey = "conclave-main";

    /// <summary>Started with Windows: stays in the notification area</summary>
    public static bool StartMinimized { get; private set; }

    [STAThread]
    public static int Main(string[] args)
    {
        // Called by the uninstaller: forget the notifications and the start with Windows
        if (args.Contains("--uninstall"))
        {
            StartupRegistration.Set(false);
            AppNotificationManager.Default.UnregisterAll();
            return 0;
        }

        StartMinimized = args.Contains("--minimized");
        WinRT.ComWrappersSupport.InitializeComWrappers();

        var current = AppInstance.GetCurrent();
        var main = AppInstance.FindOrRegisterForKey(InstanceKey);
        if (!main.IsCurrent)
        {
            Redirect(current.GetActivatedEventArgs(), main);
            return 0;
        }

        // The page's profile (cookies, session) lives with the member's data
        Environment.SetEnvironmentVariable("WEBVIEW2_USER_DATA_FOLDER", AppPaths.WebView);

        Application.Start(parameters =>
        {
            SynchronizationContext.SetSynchronizationContext(
                new DispatcherQueueSynchronizationContext(DispatcherQueue.GetForCurrentThread()));
            _ = new App(main);
        });
        return 0;
    }

    /// <summary>
    /// Hands the activation to the running instance; the wait lets COM work meanwhile, as the
    /// Windows App SDK documentation requires.
    /// </summary>
    private static void Redirect(AppActivationArguments arguments, AppInstance main)
    {
        var done = NativeMethods.CreateEventW(IntPtr.Zero, true, false, null);
        _ = Task.Run(() =>
        {
            main.RedirectActivationToAsync(arguments).AsTask().Wait();
            NativeMethods.SetEvent(done);
        });
        _ = NativeMethods.CoWaitForMultipleObjects(0, uint.MaxValue, 1, [done], out _);

        try
        {
            NativeMethods.SetForegroundWindow(Process.GetProcessById((int)main.ProcessId).MainWindowHandle);
        }
        catch (ArgumentException)
        {
            // The other instance just ended
        }
    }
}
