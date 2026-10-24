using Conclave.Desktop.Core;
using Microsoft.Windows.AppNotifications;
using Microsoft.Windows.AppNotifications.Builder;
using Microsoft.Windows.BadgeNotifications;

namespace Conclave.Desktop;

/// <summary>
/// Windows notifications (events on the member's games, messages) and the unread badge of the
/// taskbar icon. A click on a notification opens its page in the application.
/// </summary>
internal static class Notifications
{
    private const string Group = "conclave";
    private const string LinkArgument = "link";
    private static bool registered;

    public static void Start(MainWindow window)
    {
        if (!AppNotificationManager.IsSupported())
        {
            Log.Info("Windows notifications are not supported here");
            return;
        }

        // Clicks while Conclave runs; a click that launches it comes as an activation
        AppNotificationManager.Default.NotificationInvoked += (_, args) =>
            window.DispatcherQueue.TryEnqueue(() => window.ShowPage(LinkOf(args.Arguments)));
        try
        {
            AppNotificationManager.Default.Register("Conclave", new Uri(AppPaths.Asset("conclave-256.png")));
            registered = true;
            AppDomain.CurrentDomain.ProcessExit += (_, _) => AppNotificationManager.Default.Unregister();
        }
        catch (Exception e)
        {
            Log.Error("Unable to register the notifications", e);
        }
    }

    public static void Show(NotificationMessage message)
    {
        if (!registered)
        {
            return;
        }

        try
        {
            var builder = new AppNotificationBuilder()
                .AddText(message.Title)
                .SetGroup(Group);
            if (message.Body.Length > 0)
            {
                builder.AddText(message.Body);
            }

            if (!string.IsNullOrEmpty(message.Link))
            {
                builder.AddArgument(LinkArgument, message.Link);
            }

            // The same tag replaces the previous notification (another message of the same person)
            if (!string.IsNullOrEmpty(message.Tag))
            {
                builder.SetTag(message.Tag.Length <= 64 ? message.Tag : message.Tag[..64]);
            }

            AppNotificationManager.Default.Show(builder.BuildNotification());
        }
        catch (Exception e)
        {
            Log.Error("Unable to show a notification", e);
        }
    }

    public static void Badge(int count)
    {
        try
        {
            if (count > 0)
            {
                BadgeNotificationManager.Current.SetBadgeAsCount((uint)count);
            }
            else
            {
                BadgeNotificationManager.Current.ClearBadge();
            }
        }
        catch (Exception e)
        {
            // Without package identity, Windows may refuse the badge: the window title shows the count
            Log.Info($"Badge unavailable: {e.Message}");
        }
    }

    public static string? LinkOf(IDictionary<string, string> arguments) =>
        arguments.TryGetValue(LinkArgument, out var link) ? link : null;
}
