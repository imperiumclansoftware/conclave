namespace Conclave.Desktop.Core;

/// <summary>
/// Pages of the server stay in the application; any other address (Discord, a game's page, a
/// link in a message) opens in the member's browser.
/// </summary>
public static class NavigationPolicy
{
    public static bool StaysInside(Uri server, Uri target)
    {
        if (target.Scheme is "about" or "blob" or "data")
        {
            return true;
        }

        if (target.Scheme != Uri.UriSchemeHttp && target.Scheme != Uri.UriSchemeHttps)
        {
            return false;
        }

        return string.Equals(target.Scheme, server.Scheme, StringComparison.OrdinalIgnoreCase)
            && string.Equals(target.Host, server.Host, StringComparison.OrdinalIgnoreCase)
            && target.Port == server.Port
            && (target.AbsolutePath + "/").StartsWith(server.AbsolutePath, StringComparison.Ordinal);
    }

    /// <summary>
    /// Page of the application for a link sent by the page or a notification ("/evenements/4"):
    /// only paths of the server, never another site.
    /// </summary>
    public static Uri? PageOf(Uri server, string? link)
    {
        if (string.IsNullOrEmpty(link) || !link.StartsWith('/') || link.StartsWith("//", StringComparison.Ordinal) || link.Contains('\\'))
        {
            return null;
        }

        var page = new Uri(server, link.TrimStart('/'));
        return StaysInside(server, page) ? page : null;
    }
}
