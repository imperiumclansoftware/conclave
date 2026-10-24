using System.Text.Json;

namespace Conclave.Desktop.Core;

/// <summary>
/// Message of the Conclave page to the application (window.chrome.webview.postMessage):
/// a notification to show, the number of unread messages for the taskbar badge.
/// </summary>
public abstract record BridgeMessage
{
    private const int TitleMax = 120;
    private const int BodyMax = 300;

    /// <summary>The message, or null when it is not one the application knows (ignored).</summary>
    public static BridgeMessage? Parse(string? json)
    {
        if (string.IsNullOrWhiteSpace(json))
        {
            return null;
        }

        try
        {
            using var document = JsonDocument.Parse(json);
            var root = document.RootElement;
            if (root.ValueKind != JsonValueKind.Object)
            {
                return null;
            }

            return Text(root, "type") switch
            {
                "notification" when Text(root, "title") is { Length: > 0 } title => new NotificationMessage(
                    Cut(title, TitleMax),
                    Cut(Text(root, "body") ?? string.Empty, BodyMax),
                    Text(root, "tag"),
                    Text(root, "link")),
                "badge" when root.TryGetProperty("count", out var count) && count.ValueKind == JsonValueKind.Number && count.TryGetInt32(out var n) => new BadgeMessage(Math.Max(0, n)),
                _ => null,
            };
        }
        catch (JsonException)
        {
            return null;
        }
    }

    private static string? Text(JsonElement root, string name) =>
        root.TryGetProperty(name, out var value) && value.ValueKind == JsonValueKind.String ? value.GetString() : null;

    private static string Cut(string text, int max) => text.Length <= max ? text : text[..(max - 1)] + "…";
}

/// <summary>A Windows notification; a click opens <see cref="Link"/> in the application.</summary>
public sealed record NotificationMessage(string Title, string Body, string? Tag, string? Link) : BridgeMessage;

/// <summary>Unread messages, shown on the taskbar icon.</summary>
public sealed record BadgeMessage(int Count) : BridgeMessage;
