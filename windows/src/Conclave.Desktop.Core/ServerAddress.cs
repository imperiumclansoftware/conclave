namespace Conclave.Desktop.Core;

/// <summary>Address of the clan's Conclave server, as the member types it.</summary>
public static class ServerAddress
{
    /// <summary>
    /// "192.168.1.10:8080" becomes "http://192.168.1.10:8080/": the scheme defaults to HTTP (the
    /// Docker image serves HTTP), the query and fragment are dropped, the path ends with "/".
    /// </summary>
    public static bool TryParse(string? input, out Uri address, out string error)
    {
        address = null!;
        error = string.Empty;
        var text = input?.Trim() ?? string.Empty;
        if (text.Length == 0)
        {
            error = "Indiquez l'adresse de votre serveur Conclave.";
            return false;
        }

        if (!text.Contains("://", StringComparison.Ordinal))
        {
            text = "http://" + text;
        }

        if (!Uri.TryCreate(text, UriKind.Absolute, out var uri) || uri.Host.Length == 0)
        {
            error = "Cette adresse n'est pas valide. Exemple : http://192.168.1.10:8080";
            return false;
        }

        if (uri.Scheme != Uri.UriSchemeHttp && uri.Scheme != Uri.UriSchemeHttps)
        {
            error = "L'adresse doit commencer par http:// ou https://.";
            return false;
        }

        var path = uri.AbsolutePath.EndsWith('/') ? uri.AbsolutePath : uri.AbsolutePath + "/";
        address = new UriBuilder(uri.Scheme, uri.Host, uri.Port, path).Uri;
        return true;
    }

    /// <summary>"192.168.1.10:8080", or "clan.example.com" on the default port: shown in the title bar.</summary>
    public static string Label(Uri address) =>
        address.IsDefaultPort ? address.Host : $"{address.Host}:{address.Port}";
}
