using System.Net.Http.Json;
using System.Text.Json;

namespace Conclave.Desktop.Core;

/// <summary>What the server says about the Windows application.</summary>
public sealed record DesktopRelease(string Version, Uri DownloadUrl);

/// <summary>Answer of a server checked before connecting to it.</summary>
public sealed record ServerCheck(bool Ok, string? Error, DesktopRelease? Release)
{
    public static ServerCheck Failed(string error) => new(false, error, null);
}

/// <summary>Talks to the Conclave server outside the page: is it one, which version it offers.</summary>
public sealed class ServerClient(HttpClient http)
{
    public static readonly TimeSpan Timeout = TimeSpan.FromSeconds(8);

    /// <summary>A Conclave server answers /api/health with {"status": "ok"}.</summary>
    public async Task<ServerCheck> CheckAsync(Uri server, CancellationToken cancellation = default)
    {
        try
        {
            using var timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellation);
            timeout.CancelAfter(Timeout);
            using var response = await http.GetAsync(new Uri(server, "api/health"), timeout.Token);
            if (!response.IsSuccessStatusCode)
            {
                return ServerCheck.Failed($"Le serveur répond, mais pas comme un serveur Conclave (erreur {(int)response.StatusCode}). Vérifiez l'adresse.");
            }

            var health = await response.Content.ReadFromJsonAsync<JsonElement>(timeout.Token);
            if (health.ValueKind != JsonValueKind.Object || !health.TryGetProperty("status", out var status) || status.GetString() != "ok")
            {
                return ServerCheck.Failed("Ce serveur n'est pas un serveur Conclave. Vérifiez l'adresse.");
            }

            return new ServerCheck(true, null, await ReleaseAsync(server, timeout.Token));
        }
        catch (Exception e) when (e is HttpRequestException or TaskCanceledException)
        {
            return ServerCheck.Failed("Aucun serveur ne répond à cette adresse. Vérifiez qu'elle est juste et que le serveur est démarré.");
        }
        catch (JsonException)
        {
            return ServerCheck.Failed("Ce serveur n'est pas un serveur Conclave. Vérifiez l'adresse.");
        }
    }

    /// <summary>Version of the application that goes with the server, if it says (older servers do not).</summary>
    public async Task<DesktopRelease?> ReleaseAsync(Uri server, CancellationToken cancellation = default)
    {
        try
        {
            using var response = await http.GetAsync(new Uri(server, "api/desktop/windows"), cancellation);
            if (!response.IsSuccessStatusCode)
            {
                return null;
            }

            var info = await response.Content.ReadFromJsonAsync<JsonElement>(cancellation);
            return info.TryGetProperty("version", out var version) && version.GetString() is { } v
                && info.TryGetProperty("downloadUrl", out var url) && Uri.TryCreate(url.GetString(), UriKind.Absolute, out var download)
                    ? new DesktopRelease(v, download)
                    : null;
        }
        catch (Exception e) when (e is HttpRequestException or TaskCanceledException or JsonException or InvalidOperationException)
        {
            return null;
        }
    }
}
