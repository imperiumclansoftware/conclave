using System.Net;
using System.Text;
using Conclave.Desktop.Core;

namespace Conclave.Desktop.Core.Tests;

public class ServerClientTests
{
    private static readonly Uri Server = new("http://nas.local:8080/");

    [Fact]
    public async Task RecognisesAConclaveServerAndItsRelease()
    {
        var client = new ServerClient(new HttpClient(new FakeServer(new()
        {
            ["/api/health"] = (HttpStatusCode.OK, """{"status":"ok","timestamp":"2026-10-24T10:00:00+00:00"}"""),
            ["/api/desktop/windows"] = (HttpStatusCode.OK, """{"version":"0.4.0","downloadUrl":"http://nas.local:8080/api/desktop/windows/download"}"""),
        })));

        var check = await client.CheckAsync(Server, TestContext.Current.CancellationToken);

        Assert.True(check.Ok);
        Assert.Equal(new DesktopRelease("0.4.0", new Uri("http://nas.local:8080/api/desktop/windows/download")), check.Release);
    }

    [Fact]
    public async Task AcceptsAnOlderServerWithoutRelease()
    {
        var client = new ServerClient(new HttpClient(new FakeServer(new()
        {
            ["/api/health"] = (HttpStatusCode.OK, """{"status":"ok"}"""),
        })));

        var check = await client.CheckAsync(Server, TestContext.Current.CancellationToken);

        Assert.True(check.Ok);
        Assert.Null(check.Release);
    }

    [Fact]
    public async Task RefusesAnotherSite()
    {
        var client = new ServerClient(new HttpClient(new FakeServer(new()
        {
            ["/api/health"] = (HttpStatusCode.OK, "<html>Box internet</html>"),
        })));

        var check = await client.CheckAsync(Server, TestContext.Current.CancellationToken);

        Assert.False(check.Ok);
        Assert.Contains("pas un serveur Conclave", check.Error);
    }

    [Fact]
    public async Task SaysWhenNothingAnswers()
    {
        var check = await new ServerClient(new HttpClient(new FakeServer(new()))).CheckAsync(Server, TestContext.Current.CancellationToken);

        Assert.False(check.Ok);
        Assert.Contains("Aucun serveur ne répond", check.Error);
    }

    /// <summary>Answers the known paths; any other request fails like an unreachable host.</summary>
    private sealed class FakeServer(Dictionary<string, (HttpStatusCode Status, string Body)> pages) : HttpMessageHandler
    {
        protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
        {
            if (!pages.TryGetValue(request.RequestUri!.AbsolutePath, out var page))
            {
                throw new HttpRequestException("Connection refused");
            }

            return Task.FromResult(new HttpResponseMessage(page.Status)
            {
                Content = new StringContent(page.Body, Encoding.UTF8, page.Body.StartsWith('<') ? "text/html" : "application/json"),
            });
        }
    }
}
