using Conclave.Desktop.Core;

namespace Conclave.Desktop.Core.Tests;

public class NavigationPolicyTests
{
    private static readonly Uri Server = new("http://nas.local:8080/conclave/");

    [Theory]
    [InlineData("http://nas.local:8080/conclave/evenements/4", true)]
    [InlineData("http://nas.local:8080/conclave", true)]
    [InlineData("http://NAS.local:8080/conclave/news", true)]
    [InlineData("about:blank", true)]
    [InlineData("http://nas.local:8080/other/", false)]
    [InlineData("http://nas.local:8080/conclave-other/", false)]
    [InlineData("http://nas.local:9090/conclave/", false)]
    [InlineData("https://nas.local:8080/conclave/", false)]
    [InlineData("https://discord.com/channels/1", false)]
    [InlineData("mailto:papa@example.com", false)]
    public void KeepsTheServerPagesInside(string target, bool inside)
    {
        Assert.Equal(inside, NavigationPolicy.StaysInside(Server, new Uri(target)));
    }

    [Theory]
    [InlineData("/evenements/4", "http://nas.local:8080/conclave/evenements/4")]
    [InlineData("/messages/2", "http://nas.local:8080/conclave/messages/2")]
    [InlineData("//evil.example.com/x", null)]
    [InlineData("https://evil.example.com/", null)]
    [InlineData("/\\evil.example.com", null)]
    [InlineData("", null)]
    [InlineData(null, null)]
    public void OpensOnlyPagesOfTheServer(string? link, string? expected)
    {
        Assert.Equal(expected, NavigationPolicy.PageOf(Server, link)?.AbsoluteUri);
    }
}
